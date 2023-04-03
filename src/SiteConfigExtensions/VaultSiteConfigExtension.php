<?php

namespace Violet88\VaultModule\SiteConfigExtensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;
use Violet88\VaultModule\VaultClient;

class VaultSiteConfigExtension extends DataExtension
{
    // For each key in the server status, we want to show an understandable message
    private static $keyDescriptions = [
        'type' => 'Type',
        'initialized' => 'Initialized',
        'sealed' => 'Sealed',
        'n' => 'Key shares',
        't' => 'Key threshold',
        'progress' => 'Unseal Progress',
        'nonce' => 'Unseal Nonce',
        'version' => 'Version',
        'build_date' => 'Build date',
        'cluster_name' => 'Cluster name',
        'cluster_id' => 'Cluster ID',
        'recovery_seal' => 'Recovery seal',
        'storage_type' => 'Storage type',
        'migration' => 'Migration',
    ];

    private static $keyTypes = [
        'initialized' => 'bool',
        'sealed' => 'bool',
    ];

    public function updateCMSFields(FieldList $fields)
    {

        Requirements::css('violet88/silverstripe-vault-module: client/dist/styles.css');
        Requirements::css('violet88/silverstripe-vault-module: client/dist/thirdparty/fontawesome/css/all.min.css');

        try {
            $client = VaultClient::create();
        } catch (\Exception $e) {

            error_log($e->getMessage());

            // Get all tabs
            $tabs = $fields->findOrMakeTab('Root');
            foreach ($tabs->getChildren() as $tab) {
                if (!method_exists($tab, 'Fields')) {
                    continue;
                }

                $firstField = $tab->Fields()->first();
                $fields->addFieldsToTab('Root.' . $tab->getName(), [
                    LiteralField::create('VaultStatus', '<div class="alert alert-danger">Vault or the transit engine are unreachable.</div>'),
                ], $firstField->getName());
            }

            return;
        }

        $status = $client->getStatus();
        $alertType = $status['sealed'] ? 'danger' : 'success';

        $statusMessageLines = [
            '<strong>Vault URL</strong>: ' . $client->getUrl(),
        ];
        foreach ($status as $key => $value) {
            $value = array_key_exists($key, $this::$keyTypes) && $this::$keyTypes[$key] === 'bool' ? ($value ? 'Yes' : 'No') : $value;
            $key = array_key_exists($key, $this::$keyDescriptions) ? $this::$keyDescriptions[$key] : $key;
            $statusMessageLines[] = '<strong>' . $key . '</strong>: ' . $value;
        }

        $fields->addFieldsToTab('Root.Vault Status', [
            LiteralField::create('VaultStatus', '<div class="alert alert-' . $alertType . '">Vault is ' . ($status['sealed'] ?
                'sealed, please unseal it before using the module' :
                'unsealed') . '.</div>'),
            LiteralField::create('VaultStatusLines', '<div class="alert">' . implode('<br>', $statusMessageLines) . '</div>'),
        ]);
    }
}
