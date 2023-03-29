<?php

namespace Violet88\VaultModule\FieldType;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use Violet88\VaultModule\VaultClient;

class DBEncrypted extends DBField
{

    public function requireField()
    {
        DB::require_field($this->tableName, $this->name, "text");
    }

    public function writeToManipulation(&$manipulation)
    {
        $manipulation['fields'][$this->name] = $this->prepValueForDB($this->value);
    }

    public function prepValueForDB($value)
    {
        $vaultClient = VaultClient::create();

        $encryptedValue = $vaultClient->encrypt($value);

        return $encryptedValue;
    }

    public function getValue()
    {
        $vaultClient = VaultClient::create();

        $decryptedValue = $vaultClient->decrypt($this->value);

        return $decryptedValue;
    }
}
