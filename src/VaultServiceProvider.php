<?php

namespace Mdma4d\Vault;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class VaultServiceProvider extends ServiceProvider
{

    public function register()
    {

        $configPath = __DIR__ . '/config/vault.php';
        $this->mergeConfigFrom($configPath, 'vault');
        $this->publishes([$configPath => config_path('vault.php')], 'vault');
        
        $config = $this->app['config']->get('vault');
       
        
        $vault = new Vault($config);
        
        $config2 = $vault->getConfig();
        Log::debug('Vault config: {data}', ['data' => $config2]);
        foreach($config2 as $key => $value){
            $params = Config::get($key);
            Config::set($key, array_merge($params, $value));
        } 

        $dbParams = $vault->getDatabaseCreds();

        if (!empty($dbParams)) {
            $params = Config::get("database.connections.mysql");
            $params['password'] = $dbParams['password'];
            $params['username'] = $dbParams['username'];
            Config::set("database.connections.mysql", $params);
        }

        $this->app->bind('vault', function ($app) {
            $config = $app['config']->get('vault');
            return new Vault($config);
        });


        $this->app->bind(Vault::class, 'vault');
        
        if($vault->hasEncryption()){
            $this->registerEncryption();
        }
        
    }
    
    
    protected function registerEncryption() 
    {
        $key = Config::get('app.key');
        if (Str::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }
        $cipher = Config::get('app.cipher', 'AES-256-CBC');

        $this->app->singleton('encrypter', function ($app) use ($key, $cipher) {
            return new VaultEncrypter($key, $cipher);
        });
        
        
    }
    
    
    
    public function boot()
    {
       $this->app->make('hash')->extend('vault', function() {
            return new VaultHasher;
        });
    }


}