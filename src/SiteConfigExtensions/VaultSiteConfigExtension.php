<?php

namespace Violet88\VaultModule\SiteConfigExtensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;
use Violet88\VaultModule\VaultClient;

/**
 * The VaultSiteConfigExtension class is a SilverStripe extension that adds a tab to the SiteConfig that shows the status of the Vault server, or if it is not reachable an error message across all tabs.
 *
 * @package violet88/silverstripe-vault-module
 * @author  Violet88 @violet88github <info@violet88.nl>
 * @author  Roël Couwenberg @PixNyb <contact@roelc.me>
 * @access  public
 */
class VaultSiteConfigExtension extends DataExtension
{
    /**
     * The friendly names for the Vault status keys.
     *
     * @var array $keyDescriptions
     */
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

    /**
     * The types of the Vault status keys, used to format the values.
     *
     * @var array $keyTypes
     */
    private static $keyTypes = [
        'initialized' => 'bool',
        'sealed' => 'bool',
    ];

    public function updateCMSFields(FieldList $fields)
    {

        Requirements::css('violet88/silverstripe-vault-module: client/dist/styles.css');
        Requirements::css('violet88/silverstripe-vault-module: client/dist/thirdparty/fontawesome/css/all.min.css');

        $status = $this->getVaultStatus();

        if (!$status['reachable']) {
            // Get all tabs
            $tabs = $fields->findOrMakeTab('Root');
            foreach ($tabs->getChildren() as $tab) {
                if (!method_exists($tab, 'Fields')) {
                    continue;
                }

                $firstField = $tab->Fields()->first();
                $fields->addFieldsToTab('Root.' . $tab->getName(), [
                    LiteralField::create(
                        'VaultStatus',
                        "<div class='alert alert-{$status['color']}'><i class='{$status['icon']}'></i> {$status['message']}.</div>"
                    ),
                ], $firstField->getName());
            }

            return;
        }

        $client = VaultClient::create();
        $serverStatus = $client->getStatus();

        $statusMessageLines = [
            '<strong>Vault URL</strong>: ' . $client->getUrl(),
        ];
        foreach ($serverStatus as $key => $value) {
            $value = array_key_exists($key, $this::$keyTypes) && $this::$keyTypes[$key] === 'bool' ? ($value ? 'Yes' : 'No') : $value;
            $key = array_key_exists($key, $this::$keyDescriptions) ? $this::$keyDescriptions[$key] : $key;
            $statusMessageLines[] = '<strong>' . $key . '</strong>: ' . $value;
        }

        $fields->addFieldsToTab('Root.Vault Status', [
            LiteralField::create(
                'VaultStatus',
                "<div class='alert alert-{$status['color']}'><i class='{$status['icon']}'></i> {$status['message']}.</div>"
            ),
            LiteralField::create('VaultStatusLines', '<div class="alert">' . implode('<br>', $statusMessageLines) . '</div>'),
        ]);
    }

    /**
     * Get the status of the vault server and return it as an array.
     * Doesn't use the VaultClient because the errors it throws are not specific enough.
     *
     * @return array
     */
    private function getVaultStatus(): array
    {
        // Check if the vault is reachable
        $url = VaultClient::config()->get('vault_url');
        $auth_token = VaultClient::config()->get('authorization_token');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $auth_token,
        ]);

        $response = json_decode(curl_exec($ch));
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($http_code, [200, 204, 301, 302, 307])) {
            return [
                'reachable' => false,
                'message' => 'Vault is unreachable',
                'color' => 'danger',
                'icon' => 'fa fa-server',
            ];
        }

        // Get the server status
        $url .= '/v1/sys/seal-status';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $auth_token,
        ]);

        $response = json_decode(curl_exec($ch));
        curl_close($ch);

        if ($response->sealed) {
            return [
                'reachable' => false,
                'message' => 'Vault is sealed',
                'color' => 'danger',
                'icon' => 'fa fa-shield',
            ];
        }

        try {
            $client = VaultClient::create();
            $client->getStatus();
        } catch (\Exception $e) {
            return [
                'reachable' => false,
                'message' => 'Vault transit engine is unreachable',
                'color' => 'warning',
                'icon' => 'fa fa-database',
            ];
        }

        return [
            'reachable' => true,
            'message' => 'Vault is reachable',
            'color' => 'success',
            'icon' => 'fa fa-check',
        ];
    }
}
