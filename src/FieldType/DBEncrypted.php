<?php

namespace Violet88\VaultModule\FieldType;

use SilverStripe\Dev\Debug;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use Violet88\VaultModule\VaultClient;

class DBEncrypted extends DBField
{
    private string $field_type = TextField::class;

    public function __construct(
        string $name = null,
        string $fieldtype = null,
        array $options = []
    ) {
        parent::__construct($name, $options);

        if ($fieldtype)
            $this->field_type = $fieldtype;
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
        error_log('Setting value: ' . $value . ' for field: ' . $this->name);

        error_log(print_r(
            [
                'value' => $value,
                'record' => $record ? $record->toMap() : null,
                'markChanged' => $markChanged
            ],
            true
        ));

        if ($record)
            $record->{$this->name} = $this->decrypt($value);

        parent::setValue($this->decrypt($value), $record, $markChanged);

        error_log('Value set: ' . $this->value . ' for field: ' . $this->name);

        return $this;
    }

    public function scaffoldFormField($title = null, $params = null)
    {
        $field = $this->field_type;

        $field = $field::create($this->name, $title);

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
