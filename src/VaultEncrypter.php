<?php

namespace Mdma4d\Vault;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

class VaultEncrypter extends Encrypter
{

    public function encrypt($value, $serialize = true)
    {
        return app('vault')->encrypt($serialize ? serialize($value) : $value);
    }


    public function decrypt($value, $unserialize = true) 
    {
        
        if (Str::startsWith($value, 'vault:')) {
            $decryptedValue = app('vault')->decrypt($value);
            $decryptedValue = $unserialize ? unserialize($decryptedValue) : $decryptedValue;
        } else {
            $decryptedValue = parent::decrypt($value, $unserialize);
        }

        return $decryptedValue;
    }
    
}
