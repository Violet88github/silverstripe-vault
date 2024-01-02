<?php

namespace Violet88\VaultModule;

use CurlHandle;
use Exception;
use SilverStripe\Core\Config\Configurable;

/**
 * The VaultKey class serves as a wrapper for a Vault key.
 *
 * @package violet88/silverstripe-vault
 * @author  Violet88 @violet88github <info@violet88.nl>
 * @author  Roël Couwenberg @PixNyb <contact@roelc.me>
 *
 * @access  public
 */
class VaultKey
{
    use Configurable;

    /**
     * The key.
     *
     * @var mixed
     */
    private $key = null;

    /**
     * The key version.
     *
     * @var int
     */
    private $key_version = null;

    /**
     * The name of the key.
     *
     * @config
     * @var string
     */
    private $name = 'default';

    /**
     * The type of key.
     *
     * @config
     * @var mixed
     */
    private $type = 'aes256-gcm96';

    /**
     * The valid types of keys.
     *
     * @see https://www.vaultproject.io/api/secret/transit/index.html#create-key
     * @var array
     */
    private static $valid_types = [
        'aes256-gcm96',
        'ecdsa-p256',
        'ecdsa-p384',
        'ecdsa-p521',
        'rsa-2048',
        'rsa-3072',
        'rsa-4096',
        'hmac'
    ];

    /**
     * VaultKey constructor.
     *
     * @param string $name The name of the key.
     */
    public function __construct($name = null)
    {
        $this->name = $name ?? self::config()->get('name') ?? 'default';
        $this->type = self::config()->get('type') ?? 'aes256-gcm96';

        if (!in_array($this->type, self::config()->get('valid_types')))
            throw new Exception('Invalid key type specified.');

        try {
            $this->get_key();
        } catch (Exception $e) {
            $this->create_key();
            $this->get_key();
        }
    }

    /**
     * Create a new VaultKey instance with the default configuration, allows for method chaining.
     *
     * @return VaultKey
     */
    public static function create($name = null): self
    {
        return new self($name);
    }

    /**
     * Get the key.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->key;
    }

    /**
     * Get the key name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the key type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the key.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the key version.
     *
     * @return int
     */
    public function getKeyVersion(): int
    {
        return $this->key_version;
    }

    /**
     * Rotate the key.
     *
     * @return VaultKey
     */
    public function rotate(): self
    {
        $transit_path = VaultClient::getTransitPath();
        $url = VaultClient::getUrl();
        $url .= $transit_path . '/keys/' . $this->name . '/rotate';

        $data = $this->post($url);

        $this->get_key();

        return $this;
    }

    /**
     * Create a new vault key.
     *
     * @return void
     */
    private function create_key(): void
    {
        $transit_path = VaultClient::getTransitPath();
        $url = VaultClient::getUrl();
        $url .= $transit_path . '/keys/' . $this->name;

        // Add the request body
        $data = [
            'type' => $this->type,
            'exportable' => false,
        ];

        $data = $this->post($url, $data);
    }

    /**
     * Get the key.
     *
     * @return string The key.
     */
    private function get_key(): string
    {
        $transit_path = VaultClient::getTransitPath();
        $url = VaultClient::getUrl();
        $url .= $transit_path . '/keys/' . $this->name;

        $data = $this->get($url);

        if (isset($data['type']) && $data['type'] !== $this->type)
            throw new Exception(sprintf('The key type is not \'%s\'.', $this->type));

        if (isset($data['data']['keys'])) {
            if (isset($data['latest_version'])) {
                $this->key = $data['data']['keys'][$data['latest_version']];
                $this->key_version = $data['latest_version'];
            } else {
                $latest = max(array_keys($data['data']['keys']));
                $this->key = $data['data']['keys'][$latest];
                $this->key_version = $latest;
            }

            return $this->key;
        } else if (isset($data['errors']) && count($data['errors']) > 0)
            throw new Exception('The following error occurred while retrieving key: ' . implode("\r\n", $data['errors']));

        else
            throw new Exception('Unknown error occurred while retrieving key.');
    }

    /**
     * An arbitrary authenticated GET request.
     *
     * @param string $url The URL to request.
     * @param bool $return_transfer Whether to return the transfer or not.
     *
     * @return array The response.
     */
    private function get(string $url, bool $return_transfer = true): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return_transfer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . VaultClient::getToken()
        ]);

        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);

        // Check the status code
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (!str_starts_with($status_code, '2'))
            throw new Exception('The following error occurred: ' . $status_code . ' - ' . $response);

        return json_decode($response, true);
    }

    /**
     * An arbitrary authenticated POST request.
     *
     * @param string $url The URL to request.
     * @param array $data The data to send.
     * @param bool $return_transfer Whether to return the transfer or not.
     *
     * @return array The response.
     */
    private function post(string $url, array $data = null, bool $return_transfer = true): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return_transfer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . VaultClient::getToken(),
        ]);

        curl_setopt($ch, CURLOPT_POST, true);

        if ($data)
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        // Check the status code
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (!str_starts_with($status_code, '2'))
            throw new Exception('The following error occurred: ' . $status_code . ' - ' . $response);

        return json_decode($response, true) ?? [];
    }
}
