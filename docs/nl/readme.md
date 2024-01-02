# SilverStripe Vault Module

Deze module biedt een manier om gevoelige gegevens veilig op te slaan met behulp van de [Vault](https://www.vaultproject.io/) service (specifiek de [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit)).

## Vereisten

-   SilverStripe ^4 || ^5
-   PHP ^7.4 || ^8.0
-   [Vault Server](https://vaultproject.io) met [Transit API](https://developer.hashicorp.com/vault/api-docs/secret/transit) ingeschakeld

## Installatie

Installeer de module met behulp van composer door de repository en een GitHub OAuth token toe te voegen aan het `composer.json` bestand.

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

Installeer vervolgens de module.

```bash
composer require violet88/silverstripe-vault
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

#### Configuratiebestand

De module vereist een Vault-server die is geconfigureerd. De server kan worden geconfigureerd in het `vault.yml` bestand.

```yaml
---
name: vault
---
Violet88/VaultModule/VaultClient:
    vault_token: # Vault Authorisatietoken
    vault_url: # Vault URL
    vault_transit_path: # Transit locatie, standaard 'transit'
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
    name: "silverstripe"
    type: "aes256-gcm96"
```

Sleutels worden automatisch aangemaakt als ze nog niet bestaan. Stel de rechten hiervoor in.

#### Environment variabelen

Samen met het `vault.yml` bestand ondersteunt de module de volgende omgevingsvariabelen.

```bash
VAULT_TOKEN="s.1234567890abcdef"
VAULT_URL="https://vault.example.com"
VAULT_TRANSIT_PATH="transit"
```

Wanneer de omgevingsvariabelen worden gebruikt, worden de waarden uit het `vault.yml` bestand genegeerd.

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

### Filters

De module biedt een `EncryptedFilter` filtertype dat kan worden gebruikt om gegevens te filteren op basis van de ontsleutelde waarde. Houd er rekening mee dat encrypte velden alleen gefilterd kunnen worden op basis van een exacte match.

```php
<?php

class MyDataObject extends DataObject
{
    private static $searchable_fields = [
        'MyEncryptedField' => [
            'filter' => 'EncryptedFilter',
        ],
    ];
}
```

### Tasks

De module biedt tasks om gegevens te encrypten en te decrypten en de sleutel te rouleren.

```bash
# Encrypt alle data
vendor/bin/sake dev/tasks/EncryptDBTask
```

```bash
# Decrypt alle data
vendor/bin/sake dev/tasks/DecryptDBTask
```

```bash
# Rouleer de sleutel
vendor/bin/sake dev/tasks/RotateKeyTask
```

## Disclaimers

-   Violet88 is niet verantwoordelijk voor verlies van gegevens of andere schade die kan worden veroorzaakt door het gebruik van deze module. Gebruik op eigen risico.
