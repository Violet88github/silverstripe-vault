# SilverStripe Vault Module

[![wakatime](https://wakatime.com/badge/user/5b442c6a-2bed-4f89-a733-ded69f7fa013/project/807f299d-4a9e-4452-b0d0-e4cc688ec627.svg?style=for-the-badge)](https://wakatime.com/badge/user/5b442c6a-2bed-4f89-a733-ded69f7fa013/project/807f299d-4a9e-4452-b0d0-e4cc688ec627)

This module provides a way to store sensitive data securely using the [Vault](https://www.vaultproject.io/) service (specifically the [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit)).

## Requirements

* SilverStripe 4.0+
* PHP 8.1+
* Vault Server with Transit API enabled

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

The module requires a Vault server to be configured. The server can be configured in the `config.yml` file.

```yaml
---
Name: vault
---
Violet88/VaultModule/VaultClient:
    authorization_token:    # Vault Authorization Token
    vault_url:              # Vault URL
```

Additionally, a default key can be configured in the `config.yml` file.

```yaml
---
Name: vault
---
Violet88/VaultModule/VaultKey:
    name:                   # Key Name
    type:                   # Key Type, e.g. aes256-gcm96
```

Keys will be created automatically if they do not exist, be sure to set Vault permissions accordingly.

### Usage

The module provides an 'Encrypted' field type that automatically encrypts and decrypts data when it is saved and retrieved from the database.

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
