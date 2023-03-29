<?php

namespace Violet88\VaultModule\FieldType;

use Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use Violet88\VaultModule\VaultClient;

class DBEncrypted extends DBField
{
    protected $cast = 'Varchar';

    public function __construct($name = null, $cast = 'Varchar')
    {
        error_log("DBEncrypted::__construct($name, $cast)");

        $this->cast = $cast ?? 'Varchar';

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

        $field = $class::create($this->name, $title);
        $field = $field->scaffoldFormField($title, $params);
        $field->addExtraClass('encrypted_field')
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
