# Laravel Vault

Simple Laravel configuration, dynamic database credentials, encryption and
hashing backed by [HashiCorp Vault](https://www.vaultproject.io/).


## Compatibility

The package is tested against every supported major Laravel release:

| Laravel | PHP           | Testbench |
|---------|---------------|-----------|
| 8.x     | 8.0 – 8.1     | 6.x       |
| 9.x     | 8.0 – 8.2     | 7.x       |
| 10.x    | 8.1 – 8.3     | 8.x       |
| 11.x    | 8.2 – 8.3     | 9.x       |
| 12.x    | 8.2 – 8.4     | 10.x      |
| 13.x    | 8.3 – 8.5     | 11.x      |

PHP `^8.0` is required. PHP 7.x (end of life) is not supported.

## Installation

```
composer require mdma4d/laravel-vault
```

The service provider is registered automatically via Laravel package
discovery. If you have disabled discovery, add it manually to the `providers`
array (or `bootstrap/providers.php` on Laravel 11+):

```php
Mdma4d\Vault\VaultServiceProvider::class,
```

## Configuration

Publish the configuration file if you want to customise it:

```
php artisan vendor:publish --tag=vault
```

### Configure HashiCorp Vault

Create an AppRole to access HashiCorp Vault.

Create a KV secrets engine holding your Laravel configuration:

```json
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

`app.key` holds the previous application key so that legacy cryptograms can
still be decrypted.

Create a Transit engine for encryption and hashing, and a database engine,
connection and role to obtain dynamic MySQL credentials.

### Environment variables

```
VAULT_ADDR=https://vault:8200
VAULT_ROLE_ID=14c64adb-80ff-1d90-da6a-9f991a76b5e0
VAULT_SECRET_ID=a3ba16c5-aec1-965b-e5b3-360acad8b799
VAULT_CONFIG=/v1/kv/laravel
VAULT_TRANSIT_PATH=/v1/laravel
VAULT_TRANSIT_KEY=key
VAULT_DATABASE=/v1/database/creds/laravel

# Optional: dedicated HMAC mount/key (falls back to the Transit settings above)
VAULT_HMAC_TRANSIT_PATH=/v1/laravel
VAULT_HMAC_KEY=key

# TLS handling
VAULT_CA_CERT_PATH=/etc/ssl/certs/vault-ca.pem   # verify against this CA
VAULT_VERIFY=false                                # or toggle verification on/off
```

If `VAULT_TOKEN` is set it is used directly; otherwise the AppRole
`role_id`/`secret_id` pair is used to obtain a token.

TLS verification is handled explicitly: when `VAULT_CA_CERT_PATH` is provided
the connection is verified against that CA; otherwise verification follows the
`VAULT_VERIFY` flag (disabled by default to preserve the historical behaviour).

## Running the tests

```
composer install
composer test            # full suite
composer test:unit       # unit tests only
composer test:feature    # feature tests only
```

The test-suite mocks all Vault HTTP traffic (via Guzzle's `MockHandler`) and
Orchestra Testbench, so no running HashiCorp Vault instance or network access
is required.

### With Docker

To reproduce the full Laravel 8-13 / PHP matrix locally without installing
several PHP versions, use the bundled Docker setup.

Run the whole matrix (builds one image per PHP version, then runs every row —
lowest and latest dependencies):

```
docker/test-matrix.sh          # all combinations
docker/test-matrix.sh 13       # only Laravel 13 rows
```

Or run a single combination via Docker Compose:

```
# Latest deps resolvable for the composer.json constraints on PHP 8.3
docker compose run --rm test

# A specific combination
PHP_VERSION=8.2 LARAVEL='11.*' TESTBENCH='9.*' docker compose run --rm test

# Lowest dependencies
PHP_VERSION=8.0 LARAVEL='8.*' TESTBENCH='6.*' DEPS='--prefer-lowest' \
    docker compose run --rm test
```

The package source is mounted read-only, so dependencies are installed inside
the container and your working copy (and its `vendor/`) is never modified.
