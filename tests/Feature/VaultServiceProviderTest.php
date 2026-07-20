<?php

namespace Mdma4d\Vault\Tests\Feature;

use Illuminate\Encryption\Encrypter as LaravelEncrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Mdma4d\Vault\Tests\TestCase;
use Mdma4d\Vault\Vault;
use Mdma4d\Vault\VaultEncrypter;
use Mdma4d\Vault\VaultHasher;
use Mdma4d\Vault\VaultServiceProvider;

class VaultServiceProviderTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', 'base64:AckfSECXIvnK5r28GVIWUAxmbBSjTsmF/0rZoKCflOs=');
        $app['config']->set('app.cipher', 'AES-256-CBC');
        // Ensure no Vault access is configured by default.
        $app['config']->set('vault.config', null);
        $app['config']->set('vault.database', null);
        $app['config']->set('vault.transit', ['path' => null, 'key' => null]);
    }

    public function test_service_provider_is_loaded(): void
    {
        $this->assertArrayHasKey(VaultServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function test_package_configuration_is_merged(): void
    {
        $this->assertIsArray(Config::get('vault'));
        $this->assertArrayHasKey('address', Config::get('vault'));
        $this->assertArrayHasKey('transit', Config::get('vault'));
    }

    public function test_vault_is_registered_in_container(): void
    {
        $this->assertTrue($this->app->bound('vault'));
        $this->assertInstanceOf(Vault::class, $this->app->make('vault'));
    }

    public function test_vault_class_resolves_from_container(): void
    {
        $this->assertInstanceOf(Vault::class, $this->app->make(Vault::class));
    }

    public function test_vault_hash_driver_is_registered(): void
    {
        $this->assertInstanceOf(VaultHasher::class, Hash::driver('vault'));
    }

    public function test_app_boots_without_http_when_unconfigured(): void
    {
        $history = [];
        $client = $this->mockClient([], $history); // empty queue: any request would throw

        $this->bootPackageWith($client);

        $this->assertCount(0, $history);
    }

    public function test_encrypter_is_not_replaced_without_transit_configuration(): void
    {
        $history = [];
        $this->bootPackageWith($this->mockClient([], $history));

        $encrypter = $this->app->make('encrypter');
        $this->assertInstanceOf(LaravelEncrypter::class, $encrypter);
        $this->assertNotInstanceOf(VaultEncrypter::class, $encrypter);
    }

    public function test_encrypter_is_replaced_when_transit_is_configured(): void
    {
        Config::set('vault.address', 'https://vault.test');
        Config::set('vault.transit', ['path' => '/v1/transit', 'key' => 'appkey']);

        $this->bootPackageWith($this->mockClient([]));

        $this->assertInstanceOf(VaultEncrypter::class, $this->app->make('encrypter'));
    }

    public function test_null_config_from_vault_does_not_throw(): void
    {
        Config::set('vault.token', 'root');
        Config::set('vault.config', '/v1/kv/app');

        // Response without a "data" key -> getConfig() returns null.
        $client = $this->mockClient([$this->vaultResponse(['warnings' => []])]);

        $this->bootPackageWith($client);

        $this->assertTrue(true); // reached here without warning/exception
    }

    public function test_vault_configuration_is_merged_into_laravel_config(): void
    {
        Config::set('vault.token', 'root');
        Config::set('vault.config', '/v1/kv/app');

        $client = $this->mockClient([
            $this->vaultResponse(['data' => ['app' => ['name' => 'from-vault']]]),
        ]);

        $this->bootPackageWith($client);

        $this->assertSame('from-vault', Config::get('app.name'));
        // Unrelated application settings are preserved.
        $this->assertSame('AES-256-CBC', Config::get('app.cipher'));
    }

    public function test_dynamic_database_credentials_replace_username_and_password(): void
    {
        Config::set('vault.token', 'root');
        Config::set('vault.database', '/v1/database/creds/app');
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => 'db-host',
            'username' => 'old-user',
            'password' => 'old-pass',
        ]);

        $client = $this->mockClient([
            $this->vaultResponse(['data' => ['username' => 'vault-user', 'password' => 'vault-pass']]),
        ]);

        $this->bootPackageWith($client);

        $this->assertSame('vault-user', Config::get('database.connections.mysql.username'));
        $this->assertSame('vault-pass', Config::get('database.connections.mysql.password'));
        // Existing connection settings are not corrupted.
        $this->assertSame('db-host', Config::get('database.connections.mysql.host'));
    }

    public function test_merged_vault_config_is_serializable_for_config_caching(): void
    {
        // The package configuration must not contain closures so that
        // `php artisan config:cache` keeps working.
        $this->assertIsArray(Config::get('vault'));
        $this->assertNotEmpty(serialize(Config::get('vault')));
    }
}
