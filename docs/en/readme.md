# SilverStripe Vault Module

This module provides a way to store sensitive data securely using the [Vault](https://www.vaultproject.io/) service (specifically the [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit)).

## Requirements

* SilverStripe 4.0+
* PHP >= 7.4, >= 8.0
* [Vault Server](https://vaultproject.io) with [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit) enabled

## Installation

Install the module using composer by adding the module repository and a GitHub OAuth token with repo access to your `composer.json` file.

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Violet88github/silverstripe-vault"
        }
    ],
    "config": {
        "github-oauth": {
            "github.com": "1234567890abcdef1234567890abcdef12345678" // Github OAuth token with repo access
        }
    }
}
```

Then install the module using composer.

```bash
composer require violet88/silverstripe-vault
```

## Configuration

### Vault

The module requires transit to be enabled on the Vault server. The following policy can be used to enable transit.

```hcl
path "transit/*" {
    capabilities = ["create", "read", "update", "delete", "list", "sudo"]
}
```

The transit engine can be enabled using the following command.

```bash
vault secrets enable transit
```

### SilverStripe

#### Configuration File

The module requires a Vault server to be configured. The server can be configured in the `vault.yml` file.

```yaml
---
name: vault
---
Violet88/VaultModule/VaultClient:
    vault_token:            # Vault Authorization Token
    vault_url:              # Vault URL
    vault_transit_path:     # Transit Path, defaults to 'transit'
```

Additionally, a default key can be configured in the `vault.yml` file.

```yaml
Violet88/VaultModule/VaultKey:
    name: # Key Name
    type: # Key Type, e.g. aes256-gcm96
```

If no key is configured, the module will use the following defaults.

```yaml
Violet88/VaultModule/VaultKey:
    name: 'silverstripe'
    type: 'aes256-gcm96'
```

Keys will be created automatically if they do not exist, be sure to set Vault permissions accordingly.

#### Environment Variables

Along with the `vault.yml` file, the module supports the following environment variables.

```bash
VAULT_TOKEN="s.1234567890abcdef"
VAULT_URL="https://vault.example.com"
VAULT_TRANSIT_PATH="transit"
```

Setting these environment variables will override the corresponding values set in the `vault.yml` file.

## Usage

The module provides an `Encrypted` field type that automatically encrypts and decrypts data when it is saved and retrieved from the database.

```php
<?php

class MyDataObject extends DataObject
{
    private static $db = [
        'MyEncryptedField' => 'Encrypted',
    ];
}
```

The datatype supports automatic casting, to use it simply pass the cast type as well as any of it's parameters.

```php
<?php

class MyDataObject extends DataObject
{
    private static $db = [
        'MyEncryptedIntegerField' => 'Encrypted("Int")',
        'MyEncryptedEnumField' => 'Encrypted("Enum", "value1,value2,value3")',
    ];
}
```

### Filtering

The module provides an `EncryptedSearch` that can be used to filter data by encrypted fields. Keep in mind that the filter will only return exact matches.

```php
<?php

class MyDataObject extends DataObject
{
    private static $searchable_fields = [
        'MyEncryptedField' => 'EncryptedSearch',
    ];
}
```

### Tasks

The module provides tasks for encrypting and decrypting all data and rotating the default key.

```bash
# Encrypt all data
vendor/bin/sake dev/tasks/EncryptDBTask
```

```bash
# Decrypt all data
vendor/bin/sake dev/tasks/DecryptDBTask
```

```bash
# Rotate keys
vendor/bin/sake dev/tasks/RotateKeyTask
```

## Disclaimers

* Violet88 is not responsible for any loss of data or other damages caused by the use of this module. Use at your own risk.
