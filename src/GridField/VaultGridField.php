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

        $data = $record->toMap();
        $data = array_intersect_key($data, $encryptedFields);

        try {
            $client = VaultClient::create();
            $decryptData = $client->decrypt($data);

            for ($i = 0; $i < count($decryptData); $i++) {
                $cast = $encryptedFields[$data[$i]];
                $cast = preg_replace('/Encrypted\((.*)\)/', '$1', $cast);
                $cast = explode(',', $cast)[0];
                $cast = trim($cast, "\"'");

                $decryptData[$i] = DBEncrypted::cast($decryptData[$i], $cast);

                $record->setField($data[$i], $decryptData[$i]);
            }
        } catch (\Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->warning($e->getMessage());
        }

        return parent::getColumnContent($record, $column);
    }
}
