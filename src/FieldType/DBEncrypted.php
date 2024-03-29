<?php

namespace Violet88\VaultModule\FieldType;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBComposite;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Filters\SearchFilter;
use SilverStripe\View\Requirements;
use Violet88\VaultModule\SearchFilter\EncryptedSearchFilter;
use Violet88\VaultModule\VaultClient;

/**
 * The DBEncrypted class is a new SilverStripe datatype that allows for encrypting and decrypting data in the database using Vault (may be used with other encryption methods in the future).
 *
 * @package violet88/silverstripe-vault
 * @author  Violet88 @violet88github <info@violet88.nl>
 * @author  Roël Couwenberg @PixNyb <contact@roelc.me>
 * @access  public
 */
class DBEncrypted extends DBField
{
    /**
     * The vault client instance
     *
     * @var VaultClient $client
     */
    private ?VaultClient $client;

    /**
     * The cast types that cannot be encrypted
     *
     * @var array $unavailable_casts
     */
    private array $unavailable_casts = [];

    /**
     * Whether the data is actually encrypted in the database.
     *
     * @var bool $is_encrypted
     */
    private bool $is_encrypted = false;

    /**
     * The key version that was used to encrypt the data, returns null if the data is not encrypted.
     *
     * @var int $key_version
     */
    private ?int $key_version = null;

    /**
     * The cast type to use for displaying the data, can be any SilverStripe datatype (third party datatypes may not work but have not been tested) except for the ones in $unavailable_casts.
     *
     * @var string $cast
     */
    protected string $cast = 'Varchar';

    /**
     * The arguments to pass to the cast type.
     *
     * @var array $cast_args
     */
    protected array $cast_args = [];

    /**
     * The actual data that is stored in the database.
     *
     * @var string|null $db_value
     */
    protected ?string $db_value = null;

    /**
     * The escape type, is only really used to be able to support HTMLText and HTMLVarchar.
     *
     * @var string $escape_type
     */
    private static string $escape_type = 'xml';

    /**
     * VaultClient constructor.
     *
     * @param string $name The name of the field.
     * @param string $cast The cast type to use for displaying the data.
     * @param ... $cast_args The arguments to pass to the cast type.
     *
     * @return void
     */
    public function __construct($name = null, $cast = 'Varchar', ...$cast_args)
    {
        if (in_array($cast, $this->unavailable_casts))
            throw new Exception("Cast type $cast is not available for DBEncrypted");

        $this->cast = $cast ?? 'Varchar';
        $this->cast_args = $cast_args ?? [];

        try {
            $this->client = VaultClient::create();
        } catch (Exception $e) {
            $this->client = null;
            // Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
        }

        Requirements::css('violet88/silverstripe-vault: client/dist/styles.css');
        Requirements::css('violet88/silverstripe-vault: client/dist/thirdparty/fontawesome/css/all.min.css');

        parent::__construct($name);
    }

    public function requireField()
    {
        DB::require_field($this->tableName, $this->name, "text");
        DB::require_field($this->tableName, $this->name . '_bidx', "varchar(512)");

        DB::require_index($this->tableName, $this->name . '_bidx', [
            'type' => 'index',
            'columns' => [$this->name . '_bidx']
        ]);
    }

    public function writeToManipulation(&$manipulation)
    {
        $manipulation['fields'][$this->name] = $this->encrypt($this->value);

        try {
            if ($this->client)
                $manipulation['fields'][$this->name . '_bidx'] = $this->client->hmac($this->value ?? 'null');
        } catch (Exception $e) {
            // Injector::inst()->get(LoggerInterface::class)->error($e->getMessage());
        }
    }

    public function saveInto($dataObject)
    {
        $dataObject->{$this->name} = $this->decrypt($this->value);
    }

    public function setValue($value, $record = null, $markChanged = true)
    {
        $value = $this->decrypt($value);

        $dbValue = null;
        if ($record && $this->tableName) {
            $dbValue = DB::prepared_query("SELECT {$this->name} FROM {$this->tableName} WHERE ID = ?", [$record->ID])->value();
            $record->{$this->name} = $value;
        }

        $this->db_value = $dbValue;
        $this->is_encrypted = ($value != $dbValue);

        parent::setValue($value, $record, $markChanged);

        return $this;
    }

    public function scaffoldFormField($title = null, $params = null)
    {
        $cast = $this->cast;

        $class = Injector::inst()->get($cast);

        if (!$class)
            throw new Exception("Could not find field type $cast");

        $args = array_merge([
            $this->name,
        ], $this->cast_args);

        $class = new $class(...$args);

        try {
            $client = VaultClient::create();
        } catch (Exception $e) {
            $client = null;
        }

        $field = $class->scaffoldFormField($title, $params)
            ->addExtraClass('encrypt-field')
            ->addExtraClass('encrypt-field__' . ($this->is_encrypted ? 'encrypted' : 'decrypted'))
            ->setAttribute('data-encrypted', $this->is_encrypted ? 'true' : 'false')
            ->setAttribute('data-encrypted-type', $cast);

        // Get the null value for the cast type
        $nullValue = null;
        if (method_exists($class, 'nullValue'))
            $nullValue = $class->nullValue();

        if (!$client)
            $field->addExtraClass('encrypt-field__error')
                ->setAttribute('data-encrypted-error', 'Vault transit engine is unreachable.')
                ->setAttribute('data-encrypted-error-type', 'unreachable')
                ->setAttribute('title', 'Vault transit engine is unreachable.');

        if ($this->db_value === $nullValue || $this->db_value === null)
            $field->addExtraClass('encrypt-field__empty');

        return $field;
    }

    public function scaffoldSearchField($title = null, $params = null)
    {
        $field = parent::scaffoldSearchField($title, $params);

        // Remove all the extra classes that are added by the scaffoldFormField method
        $field->removeExtraClass('encrypt-field')
            ->removeExtraClass('encrypt-field__encrypted')
            ->removeExtraClass('encrypt-field__decrypted')
            ->removeExtraClass('encrypt-field__error')
            ->removeExtraClass('encrypt-field__empty');

        $field->setName($this->name . '_bidx');

        return $field;
    }

    public function getSchemaValue()
    {
        return $this->decrypt($this->value);
    }

    public function enumValues()
    {
        if ($this->cast === 'Enum')
            return explode($this->cast_args[0], ", ");

        return null;
    }

    /**
     * Returns the decrypted value of the field. This can be used to display the decrypted value in the CMS by using $MyEncryptedField.Decrypted
     *
     * @return string
     */
    public function Decrypted()
    {
        return $this->decrypt($this->value);
    }

    /**
     * Returns the decrypted value of the field if it is encrypted. If the value is not encrypted, the value will be returned as is.
     *
     * @return string
     */
    private function decrypt($value)
    {
        if (!$this->client)
            return $value;

        try {
            if (str_starts_with($value ?? '', 'vault:')) {
                $decryptValue = $this->client->decrypt($value);
                if ($decryptValue === 'null')
                    $decryptValue = null;

                return $decryptValue;
            }
        } catch (Exception $e) {
            $value ??= '';
            $shortenedValue = substr($value, 0, 10);
            if (strlen($value) > 10)
                $shortenedValue .= '... ';
            // Injector::inst()
            //     ->get(LoggerInterface::class)
            //     ->info("Could not decrypt $shortenedValue: " . $e->getMessage() . ", returning as is.");
        }

        return $value;
    }

    /**
     * Returns the encrypted value of the field if it is not encrypted. If the value is already encrypted, the value will be returned as is.
     *
     * @return string
     */
    private function encrypt($value)
    {
        if (!$this->client)
            return $value;

        try {
            if ($value === null || empty($value))
                $value = 'null';

            if (!str_starts_with($value ?? '', 'vault:'))
                $value = $this->client->encrypt($value);
        } catch (Exception $e) {
            $value ??= '';
            $shortenedValue = substr($value, 0, 10);
            if (strlen($value) > 10)
                $shortenedValue .= '... ';
            // Injector::inst()
            //     ->get(LoggerInterface::class)
            //     ->info("Could not encrypt $shortenedValue: " . $e->getMessage() . ", returning as is.");
        }

        return $value;
    }

    public static function cast($value, $class)
    {
        try {
            $dbField = Injector::inst()->get($class);

            if (str_starts_with($value ?? '', 'vault:') || $value === null || empty($value) || $value === 'null')
                $value = $dbField->nullValue() ?? null;

            $dbField->setValue($value);
            return $dbField->getValue();
        } catch (Exception $e) {
            throw new Exception("Could not find field type $class");
        }
    }
}
