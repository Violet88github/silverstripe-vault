<?php

namespace Violet88\VaultModule\GridField;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\GridField\GridField;
use Violet88\VaultModule\FieldType\DBEncrypted;
use Violet88\VaultModule\VaultClient;

class VaultGridField extends GridField
{
    public function getColumnContent($record, $column)
    {
        $dbFields = $record->config()->get('db');
        $encryptedFields = array_filter($dbFields, fn ($dbFieldType) => str_starts_with($dbFieldType, 'Encrypted'));
        $encryptedFieldCasts = array_values(array_map(function ($dbFieldType) {
            $dbFieldType = preg_replace('/Encrypted\((.*)\)/', '$1', $dbFieldType);
            $dbFieldType = explode(',', $dbFieldType)[0];
            $dbFieldType = trim($dbFieldType, "\"'");

            return $dbFieldType;
        }, $encryptedFields));

        $data = $record->toMap();
        $data = array_values(array_intersect_key($data, $encryptedFields));

        try {
            $client = VaultClient::create();
            $decryptData = $client->decrypt($data);

            foreach ($decryptData as $key => $value) {
                $fieldName = array_keys($encryptedFields)[$key];

                $record->{$fieldName} = DBEncrypted::cast($value, $encryptedFieldCasts[$key]);
            }
        } catch (\Exception $e) {
            // Injector::inst()->get(LoggerInterface::class)->warning($e->getMessage());
        }

        return parent::getColumnContent($record, $column);
    }
}
