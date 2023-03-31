<?php

namespace Violet88\VaultModule\GridField;

use SilverStripe\Forms\GridField\GridField;

class VaultGridField extends GridField
{
    public function getColumnContent($record, $column)
    {
        $dbFields = $record->config()->get('db');

        foreach ($dbFields as $dbField => $dbFieldType) {
            if (str_starts_with($dbFieldType, 'Encrypted')) {
                $fieldInstance = $record->dbObject($dbField);
                $record->$dbField = $fieldInstance->value;
            }
        }

        return parent::getColumnContent($record, $column);
    }
}
