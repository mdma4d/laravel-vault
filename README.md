
# Simple Laravel configuration from Hashicorp Vault.


# Installation

### Add the package via composer

```
composer require mdma4d/laravel-vault
```

## Usage with Laravel

### Add the Service Provider

Add the following to the `providers` array in your application config:

```
Mdma4d\Vault\VaultServiceProvider::class,
```

### Configure Hashicorp Vault

Create approle to access Hashicorp Vault.

Create KV secrets engine with laravel configuration 
```
{
  "app": {
    "key": "base64:cYrLP5mFSK1S5P1OQwk3tA16x2Uwkzf8Wxb5azBhcdE="
  },
  "database.connections.mysql": {
    "database": "laravel",
    "host": "mysql"
  },
  "hashing": {
    "driver": "vault",
    "old": "bcrypt"
  }
}
```
app.key contains old app key to decrypt old cryptograms

Create transit engine for encryption and hashing

Create databases engine, connection and role to connect to mysql


### Set the environment variables

```
VAULT_ADDR=https://vault:8200
VAULT_ROLE_ID=14c64adb-80ff-1d90-da6a-9f991a76b5e0
VAULT_SECRET_ID=a3ba16c5-aec1-965b-e5b3-360acad8b799
VAULT_CONFIG=/v1/kv/laravel
VAULT_TRANSIT_PATH=/v1/laravel
VAULT_TRANSIT_KEY=key
VAULT_DATABASE=/v1/database/creds/laravel
```



