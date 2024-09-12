<?php

namespace Mdma4d\Vault;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class VaultHasher implements HasherContract {

    private $oldDriver;

    public function info($hashedValue) {
        return password_get_info($hashedValue);
    }

    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @return string
     */
    public function make($value, array $options = []) {
        return app('vault')->hmac($value);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     */
    public function check($value, $hashedValue, array $options = []) {
        $verified = false;
        if (Str::startsWith($hashedValue, 'vault:')) {
            $verified = app('vault')->verify($value, $hashedValue);
        } elseif ($this->getOldDriver()) {
            $verified = $this->getOldDriver()->check($value, $hashedValue, $options);
        }
        return $verified;
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @param  array  $options
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = []) {
        if (Str::startsWith($hashedValue, 'vault:')) {
            return false;
        }
        return true;
    }

    private function getOldDriver() {
        if (!$this->oldDriver) {
            $driver = Config::get('hashing.old', '');
            if ($driver) {
                $this->oldDriver = Hash::driver($driver);
            }
        }
        return $this->oldDriver;
    }
}
