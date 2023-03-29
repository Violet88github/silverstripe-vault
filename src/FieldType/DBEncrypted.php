<?php

namespace Violet88\VaultModule\FieldType;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use Violet88\VaultModule\VaultClient;

class DBEncrypted extends DBField
{
    private $unavailable_casts = [
        'HTMLText',
        'HTMLVarchar',
    ];

    protected $cast = 'Varchar';

    protected $cast_args = [];

    public function __construct($name = null, $cast = 'Varchar', ...$cast_args)
    {
        if (in_array($cast, $this->unavailable_casts))
            throw new Exception("Cast type $cast is not available for DBEncrypted");

        $this->cast = $cast ?? 'Varchar';
        $this->cast_args = $cast_args ?? [];

        Requirements::customCSS(
            '.encrypted_field label::after { content: " (Encrypted)"; color: #aaa; font-size: 0.8em; }'
        );

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
        if ($record)
            $record->{$this->name} = $this->decrypt($value);

        parent::setValue($this->decrypt($value), $record, $markChanged);

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

        $field = new $class(...$args);

        $field = $field->scaffoldFormField($title, $params)
            ->addExtraClass('encrypted_field')
            ->setAttribute('data-encrypted', 'true')
            ->setAttribute('data-encrypted-type', $cast);

        return $field;
    }

    public function Decrypted()
    {
        return $this->decrypt($this->value);
    }

    private function decrypt($value)
    {
        $vaultClient = VaultClient::create();

        if (str_starts_with($value ?? '', 'vault:'))
            $value = $vaultClient->decrypt($value);

        return $value;
    }

    private function encrypt($value)
    {
        $vaultClient = VaultClient::create();

        if (!str_starts_with($value ?? '', 'vault:'))
            $value = $vaultClient->encrypt($value);

        return $value;
    }
}
