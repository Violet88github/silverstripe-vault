<?php

namespace Violet88\VaultModule\DataExtensions;

use Exception;
use SilverStripe\Core\CustomMethods;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use Violet88\VaultModule\VaultClient;

class VaultObjectExtension extends DataExtension
{
    use CustomMethods;

    private static $db = [
        'VaultID' => 'Varchar(255)',
        'VaultFields' => 'Text'
    ];

    private static $excluded_fields = [
        'VaultID',
        'VaultFields'
    ];

    public function getDecryptedField($field)
    {
        $vaultClient = VaultClient::create($this->owner->VaultID);

        if (
            in_array(
                $field,
                $this->owner->VaultFields ?
                    json_decode($this->owner->VaultFields) : []
            ) &&
            !empty($this->owner->getField($field)) &&
            !in_array($field, self::$excluded_fields)
        ) {
            try {
                $decrypted = $vaultClient->decrypt($this->owner->getField($field));
                $this->owner->setField($field, $decrypted);
            } catch (Exception $e) {
                error_log($e->getMessage());
                $this->owner
                    ->setField(
                        $field . 'Error',
                        'Could not decrypt field. This field may be irretrievable.'
                    );

                $this->owner->setField($field, '');
            }
        }

        return $this->owner->getField($field);
    }

    public function getEncryptedField($field)
    {
        $vaultClient = VaultClient::create($this->owner->VaultID);

        if (
            in_array(
                $field,
                $this->owner->VaultFields ?
                    json_decode($this->owner->VaultFields) : []
            ) &&
            !empty($this->owner->getField($field)) &&
            !in_array($field, self::$excluded_fields)
        ) {
            $encrypted = $vaultClient->encrypt($this->owner->getField($field));
            $this->owner->setField($field, $encrypted);
        }

        return $this->owner->getField($field);
    }

    public function updateCMSFields(FieldList $fields)
    {
        $dbFields = $this->owner->config()->get('db');

        $fields->addFieldsToTab('Root.Encryption', [
            LiteralField::create(
                'VaultInfo',
                '<p class="message warning">Some functionality might be lost when encrypting data used elsewhere in the SilverStripe CMS.</p>'
            ),
            ReadonlyField::create('VaultID', 'Vault Key ID'),
            CheckboxSetField::create(
                'VaultFields',
                'Encrypted Fields',
                array_filter(
                    array_combine(
                        array_keys($dbFields),
                        array_keys($dbFields)
                    ),
                    function ($key) {
                        return $key !== 'VaultID' && $key !== 'VaultFields';
                    },
                    ARRAY_FILTER_USE_KEY
                )
            )
                ->setDescription('Select the fields that should be encrypted in the vault.')
        ]);

        $hasIrretrievableFields = false;

        foreach ($dbFields as $field => $type) {
            $this->owner->getDecryptedField($field);

            if ($this->owner->hasField($field . 'Error')) {
                $fields->replaceField(
                    $field,
                    ReadonlyField::create($field, $field)
                        ->setRightTitle($this->owner->getField($field . 'Error'))
                );

                $hasIrretrievableFields = true;
            }
        }

        if ($hasIrretrievableFields) {
            // Get all tabs
            $tabs = $fields->findOrMakeTab('Root')->Tabs();

            error_log(print_r(array_map(
                function ($tab) {
                    return $tab->Title();
                },
                $tabs->toArray()
            ), true));

            foreach ($tabs as $tab) {
                $fields->addFieldToTab(
                    'Root.' . $tab->Title(),
                    LiteralField::create(
                        'VaultIrretrievableFields',
                        '<p class="message error">Some fields could not be decrypted.<br/>This may be because the vault was not found or the key was not correct.<br/>If this is the case, the fields will be irretrievable.<br/>Saving the record will clear the fields from the database.</p>'
                    ),
                    $tab->Fields()->first()->getName()
                );
            }
        }

        return $fields;
    }

    public function onBeforeWrite()
    {
        if (empty($this->owner->VaultID))
            $this->owner->VaultID = uniqid();

        $fields = $this->owner->VaultFields;

        if ($fields) {
            $fields = json_decode($fields, true);

            if (is_array($fields))
                foreach ($fields as $field) {
                    $this->owner->getEncryptedField($field);

                    if ($this->owner->hasField($field . 'Error'))
                        $this->owner->$field = null;
                }
        }

        parent::onBeforeWrite();
    }
}
