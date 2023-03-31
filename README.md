# SilverStripe Vault Module

This module provides a way to store sensitive data securely using the [Vault](https://www.vaultproject.io/) service (specifically the [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit)).

## Requirements

* SilverStripe 4.0+
* PHP 8.1+
* [Vault Server](https://vaultproject.io) with [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit) enabled

## Installation

Install the module using composer

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

The module requires a Vault server to be configured. The server can be configured in the `vault.yml` file.

```yaml
---
name: vault
---
Violet88/VaultModule/VaultClient:
    authorization_token:    # Vault Authorization Token
    vault_url:              # Vault URL
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

## Disclaimers

* Violet88 is not responsible for any loss of data or other damages caused by the use of this module. A method to recover data is not yet available. Use at your own risk.
