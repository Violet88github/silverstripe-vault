<?php

namespace Violet88\VaultModule;

use Exception;
use SilverStripe\Core\Config\Configurable;

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
    private $authorization_token = null;

    /**
     * The API URL to use for vault requests.
     *
     * @config
     * @var string
     */
    private $vault_url = null;

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
        $this->vault_url = self::config()->get('vault_url');
        $this->authorization_token = self::config()->get('authorization_token');

        $this->key = VaultKey::create($name);
    }

    /**
     * Create a new VaultClient instance with the default configuration, allows for method chaining.
     *
     * @return VaultClient
     */
    public static function create($name = null): self
    {
        return new self($name);
    }

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
        $url = $this->vault_url . '/transit/encrypt/' . $this->key->getName();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->authorization_token,
        ]);

        error_log('Encoding data: ' . $data);

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
        $url = $this->vault_url . '/transit/decrypt/' . $this->key->getName();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->authorization_token,
        ]);

        error_log('Decoding data: ' . $data);

        $data = [
            'ciphertext' => $data,
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        error_log('Decoded data: ' . $response['data']['plaintext']);

        if (isset($response['errors']))
            throw new Exception($response['errors'][0]);

        return base64_decode($response['data']['plaintext']);
    }

    public function getUrl(): string
    {
        return $this->vault_url;
    }

    public function getAuthorizationToken(): string
    {
        return $this->authorization_token;
    }

    public function getKey(): VaultKey
    {
        return $this->key;
    }

    public function getStatus(): array
    {
        $url = $this->vault_url . '/sys/seal-status';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->authorization_token,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
