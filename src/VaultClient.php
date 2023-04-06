<?php

namespace Violet88\VaultModule;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

/**
 * The VaultClient class serves as a SilverStripe configurable wrapper for the Vault API.
 *
 * @package violet88/silverstripe-vault-module
 * @author  Violet88 @violet88github <info@violet88.nl>
 * @author  Roël Couwenberg @PixNyb <contact@roelc.me>
 * @access  public
 */
class VaultClient
{
    use Configurable;

    /**
     * The authorization token to use for vault requests.
     *
     * @config
     * @var string
     */
    private $vault_token = null;

    /**
     * The API version to use for vault requests, don't change this unless you know what you're doing.
     *
     * @var string
     */
    private static $vault_api_version = 'v1';

    /**
     * The API URL to use for vault requests.
     *
     * @config
     * @var string
     */
    private $vault_url = null;

    /**
     * The transit path to use for vault requests.
     *
     * @config
     * @var string
     */
    private $vault_transit_path = null;

    /**
     * The vault key instance.
     *
     * @var mixed
     */
    private ?VaultKey $key = null;

    /**
     * VaultClient constructor.
     *
     * @param string $api_key The API key to use for vault requests.
     *
     * @return void
     */
    public function __construct($name = null)
    {
        $this->vault_url = self::getUrl();
        $this->vault_token = self::getToken();
        $this->vault_transit_path = self::getTransitPath();

        $this->key = VaultKey::create($name);
    }

    /**
     * Create a new VaultClient instance with the default configuration, allows for method chaining.
     *
     * @return VaultClient The new VaultClient instance.
     */
    public static function create($name = null): self
    {
        return new self($name);
    }

    /**
     * Compute the HMAC of the given value using the configured vault key.
     *
     * @param string $value The value to compute the HMAC of.
     *
     * @return string The HMAC of the given value.
     */
    public function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key->getKey());
    }

    /**
     * Encrypt the given data using the configured vault key by making a request to the vault API.
     *
     * @param string $data The data to encrypt.
     *
     * @return string The encrypted data.
     */
    public function encrypt(string $data): string
    {
        $url = $this->vault_url . $this->vault_transit_path . '/encrypt/' . $this->key->getName();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->vault_token,
        ]);

        $data = [
            'plaintext' => base64_encode($data)
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        return $response['data']['ciphertext'];
    }

    /**
     * Decrypt the given data using the configured vault key by making a request to the vault API.
     *
     * @param string $data The data to decrypt.
     *
     * @return string The decrypted data.
     */
    public function decrypt(string $data): string
    {
        $url = $this->vault_url . $this->vault_transit_path . '/decrypt/' . $this->key->getName();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->vault_token,
        ]);

        $data = [
            'ciphertext' => $data,
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        if (isset($response['errors']))
            throw new Exception($response['errors'][0]);

        return base64_decode($response['data']['plaintext']);
    }

    /**
     * Get the vault URL.
     *
     * @return string The vault URL.
     */
    public static function getUrl(): ?string
    {
        return Environment::getEnv('VAULT_URL') ?: self::config()->get('vault_url');
    }

    /**
     * Get the vault transit path.
     *
     * @return string The vault transit path.
     */
    public static function getTransitPath(): ?string
    {
        $path = trim(Environment::getEnv('VAULT_TRANSIT_PATH') ?: self::config()->get('vault_transit_path'), '/');
        return '/' . self::$vault_api_version . '/' . $path;
    }

    /**
     * Get the authorization token.
     *
     * @return string The authorization token.
     */
    public static function getToken(): ?string
    {
        return Environment::getEnv('VAULT_TOKEN') ?: self::config()->get('vault_token');
    }

    /**
     * Get the vault key instance.
     *
     * @return VaultKey The vault key instance.
     */
    public function getKey(): VaultKey
    {
        return $this->key;
    }

    /**
     * Get the vault server status.
     *
     * @return array The vault status.
     */
    public function getStatus(): array
    {
        $url = $this->vault_url . '/' . self::$vault_api_version . '/sys/seal-status';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->vault_token,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
