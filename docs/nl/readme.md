# SilverStripe Vault Module

Deze module biedt een manier om gevoelige gegevens veilig op te slaan met behulp van de [Vault](https://www.vaultproject.io/) service (specifiek de [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit)).

## Vereisten

* SilverStripe 4.0+
* PHP 8.1+
* [Vault Server](https://vaultproject.io) met [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit) ingeschakeld

## Installatie

Installeer de module met behulp van composer

```bash
composer require violet88/silverstripe-vault # this module is closed source
```

## Configuratie

### Vault

De module vereist dat transit is ingeschakeld op de Vault-server. Het volgende beleid kan worden gebruikt om transit in te schakelen.

```hcl
path "transit/*" {
    capabilities = ["create", "read", "update", "delete", "list", "sudo"]
}
```

De transit-engine kan worden ingeschakeld met de volgende opdracht.

```bash
vault secrets enable transit
```

### SilverStripe

De module vereist een Vault-server die is geconfigureerd. De server kan worden geconfigureerd in het `vault.yml` bestand.

```yaml
---
name: vault
---
Violet88/VaultModule/VaultClient:
    authorization_token:    # Vault Authorisatietoken
    vault_url:              # Vault URL
```

Daarnaast kan een standaard sleutel worden geconfigureerd in het `vault.yml` bestand.

```yaml
Violet88/VaultModule/VaultKey:
    name: # Sleutelnaam
    type: # Sleuteltype, bijv. aes256-gcm96
```

Als er geen sleutel is geconfigureerd, zal de module de volgende standaardwaarden gebruiken.

```yaml
Violet88/VaultModule/VaultKey:
    name: 'silverstripe'
    type: 'aes256-gcm96'
```

Sleutels worden automatisch aangemaakt als ze nog niet bestaan. Stel de rechten hiervoor in.

## Gebruik

De module biedt een `Encrypted` veldtype dat automatisch de gegevens versleutelt en ontsleutelt.

```php
<?php

class MyDataObject extends DataObject
{
    private static $db = [
        'MyEncryptedField' => 'Encrypted',
    ];
}
```

Het veldtype ondersteund automatische conversie van de data naar een ander type. Stuur hiervoor het type en eventueel de opties als parameters mee.

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

## Disclaimers

* Violet88 is niet verantwoordelijk voor verlies van gegevens of andere schade die kan worden veroorzaakt door het gebruik van deze module. Gebruik op eigen risico.
