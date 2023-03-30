<?php

namespace Violet88\VaultModule\FieldType;

use Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use Violet88\VaultModule\VaultClient;

/**
 * The DBEncrypted class is a new SilverStripe datatype that allows for encrypting and decrypting data in the database using Vault (may be used with other encryption methods in the future).
 *
 * @package violet88/silverstripe-vault-module
 * @author  Violet88 @violet88github <info@violet88.nl>
 * @author  Roël Couwenberg @PixNyb <contact@roelc.me>
 * @access  public
 */
class DBEncrypted extends DBField
{
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

        Requirements::css('violet88/silverstripe-vault-module: client/dist/styles.css');
        Requirements::css('violet88/silverstripe-vault-module: client/dist/thirdparty/fontawesome/css/all.min.css');

        parent::__construct($name);
    }

    public function requireField()
    {
        DB::require_field($this->tableName, $this->name, "text");
    }

    public function writeToManipulation(&$manipulation)
    {
        $manipulation['fields'][$this->name] = $this->encrypt($this->value);
    }

    public function saveInto($dataObject)
    {
        $dataObject->{$this->name} = $this->decrypt($this->value);
    }

    public function setValue($value, $record = null, $markChanged = true)
    {
        $value = $this->decrypt($value);

        $dbValue = null;
        if ($record) {
            $dbValue = DB::prepared_query("SELECT {$this->name} FROM {$this->tableName} WHERE ID = ?", [$record->ID])->value();
            $record->{$this->name} = $value;
        }

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

        error_log(print_r([
            'nullValue' => $nullValue,
            'value' => $this->value,
        ], true));

        if ($this->value === $nullValue)
            $field->addExtraClass('encrypt-field__empty');

        return $field;
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
        try {
            $vaultClient = VaultClient::create();
            if (str_starts_with($value ?? '', 'vault:'))
                $value = $vaultClient->decrypt($value);
        } catch (Exception $e) {
            error_log($e->getMessage());
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
        try {
            $vaultClient = VaultClient::create();
            if (!str_starts_with($value ?? '', 'vault:'))
                $value = $vaultClient->encrypt($value);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        return $value;
    }
}
