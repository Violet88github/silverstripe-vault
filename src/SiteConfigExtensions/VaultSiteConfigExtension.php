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
 * @package violet88/silverstripe-vault
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

        Requirements::css('violet88/silverstripe-vault: client/dist/styles.css');
        Requirements::css('violet88/silverstripe-vault: client/dist/thirdparty/fontawesome/css/all.min.css');

        $status = $this->getVaultStatus();

        if (!$status['reachable']) {
            $fields->addFieldsToTab('Root.Vault Status', [
                LiteralField::create(
                    'VaultStatusDetails',
                    "<div class='alert'>{$status['details']}</div>"
                )
            ]);

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
        $url = VaultClient::getUrl();
        $auth_token = VaultClient::getToken();

        if (empty($url) || empty($auth_token)) {
            $missingField = empty($url) ? 'URL' : 'authentication token';
            $configField = empty($url) ? 'vault_url' : 'vault_token';
            $envField = empty($url) ? 'VAULT_URL' : 'VAULT_TOKEN';

            return [
                'reachable' => false,
                'message' => "Vault $missingField is not set",
                'details' => "The $missingField for the Vault server is not configured properly.<br/>Please set the <code>$configField</code> config variable for <code>Violet88\VaultModule\VaultClient</code> or the <code>$envField</code> environment variable.<br/><a href='https://github.com/Violet88github/silverstripe-vault/blob/main/docs/en/readme.md' target='_blank'>See the docs</a> for more information.",
                'color' => 'danger',
                'icon' => 'fa fa-file-signature',
            ];
        }

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
                'details' => 'Unable to connect to the Vault server at ' . VaultClient::getUrl() . '. Please check the URL and make sure the server is running.',
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
                'details' => 'The Vault server at ' . VaultClient::getUrl() . ' is sealed. Please unseal the server before continuing.',
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
                'details' => 'Unable to connect to the Vault transit engine at ' .
                    VaultClient::getUrl() .
                    VaultClient::getTransitPath() .
                    '.<br/>Please check the URL and make sure the transit engine is running.',
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
