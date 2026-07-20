<?php

namespace Mdma4d\Vault;

use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class VaultServiceProvider extends ServiceProvider
{
    /**
     * Container key used to (optionally) inject an HTTP client into Vault.
     * Bind a GuzzleHttp\ClientInterface to this key to override the transport
     * (used by the test-suite to mock Vault responses).
     */
    public const HTTP_CLIENT = 'vault.http_client';

    public function register()
    {
        $configPath = __DIR__ . '/config/vault.php';
        $this->mergeConfigFrom($configPath, 'vault');
        $this->publishes([$configPath => config_path('vault.php')], 'vault');

        $this->app->singleton('vault', function ($app) {
            $client = $app->bound(self::HTTP_CLIENT) && $app->make(self::HTTP_CLIENT) instanceof ClientInterface
                ? $app->make(self::HTTP_CLIENT)
                : null;

            return new Vault($app['config']->get('vault'), $client);
        });

        $this->app->alias('vault', Vault::class);
    }

    public function boot()
    {
        // Pull configuration / credentials from Vault (no-op with no network
        // access when the relevant paths are not configured).
        $this->syncVaultConfiguration();

        $this->app->make('hash')->extend('vault', function () {
            return new VaultHasher;
        });
    }

    /**
     * Merge configuration and dynamic database credentials fetched from Vault
     * into the application configuration, and register the Vault encrypter
     * when Transit encryption is configured.
     */
    protected function syncVaultConfiguration()
    {
        /** @var \Mdma4d\Vault\Vault $vault */
        $vault = $this->app->make('vault');

        // getConfig() returns null and performs no HTTP request when
        // VAULT_CONFIG is not set, so an unconfigured app boots cleanly.
        $vaultConfig = $vault->getConfig();

        if (is_array($vaultConfig)) {
            foreach ($vaultConfig as $key => $value) {
                $current = Config::get($key);

                if (is_array($current) && is_array($value)) {
                    Config::set($key, array_merge($current, $value));
                } else {
                    Config::set($key, $value);
                }
            }
        }

        // Dynamic database credentials (no HTTP request when VAULT_DATABASE unset).
        $dbParams = $vault->getDatabaseCreds();

        if (is_array($dbParams) && !empty($dbParams)) {
            $connection = Config::get('database.default', 'mysql');
            $params = Config::get("database.connections.$connection", []);

            if (isset($dbParams['username'])) {
                $params['username'] = $dbParams['username'];
            }
            if (isset($dbParams['password'])) {
                $params['password'] = $dbParams['password'];
            }

            Config::set("database.connections.$connection", $params);
        }

        if ($vault->hasEncryption()) {
            $this->registerEncryption();
        }
    }

    protected function registerEncryption()
    {
        $key = Config::get('app.key');
        if (is_string($key) && Str::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }
        $cipher = Config::get('app.cipher', 'AES-256-CBC');

        $this->app->singleton('encrypter', function ($app) use ($key, $cipher) {
            return new VaultEncrypter($key, $cipher);
        });
    }
}
