<?php

namespace Violet88\VaultModule;

use CurlHandle;
use Exception;
use SilverStripe\Core\Config\Configurable;

/**
 * The VaultKey class serves as a wrapper for a Vault key.
 *
 * @package violet88/silverstripe-vault-module
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
     * Create a new vault key.
     *
     * @return void
     */
    private function create_key(): void
    {
        $url = VaultClient::config()->get('vault_url') . '/transit/keys/' . $this->name;

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
     * @return void
     */
    private function get_key(): void
    {
        $url = VaultClient::config()->get('vault_url') . '/transit/keys/' . $this->name;

        $data = $this->get($url);

        if (isset($data['type']) && $data['type'] !== $this->type)
            throw new Exception(sprintf('The key type is not \'%s\'.', $this->type));

        if (isset($data['data']['keys']))
            $this->key = array_pop($data['data']['keys']);

        else if (isset($data['errors']) && count($data['errors']) > 0)
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
        $ch = $this->get_authenticated_handle($url, $return_transfer);

        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);
        curl_close($ch);

        // Check the status code
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
    private function post(string $url, array $data, bool $return_transfer = true): array
    {
        $ch = $this->get_authenticated_handle($url, $return_transfer);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        // Check the status code
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!str_starts_with($status_code, '2'))
            throw new Exception('The following error occurred: ' . $status_code . ' - ' . $response);

        return json_decode($response, true) ?? [];
    }

    /**
     * Return a cURL handle with the authorization token set.
     *
     * @param string $url The URL to request.
     * @param bool $return_transfer Whether to return the transfer or not.
     *
     * @return CurlHandle The cURL handle.
     */
    private function get_authenticated_handle(string $url, bool $return_transfer = true): CurlHandle
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return_transfer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . VaultClient::config()->get('authorization_token')
        ]);

        return $ch;
    }
}
