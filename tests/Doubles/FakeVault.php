<?php

namespace Mdma4d\Vault\Tests\Doubles;

/**
 * Lightweight test double that records calls and returns canned values.
 * It is bound to the "vault" container key so VaultEncrypter / VaultHasher
 * can be exercised in isolation without any HTTP traffic.
 */
class FakeVault
{
    /** @var array<int, array> */
    public $calls = [];

    public $encryptReturn = 'vault:v1:encrypted';
    public $decryptReturn = 'decrypted';
    public $hmacReturn = 'vault:v1:hmac';
    public $verifyReturn = true;
    public $encryption = true;
    public $configReturn = null;
    public $dbReturn = null;

    public function encrypt($text)
    {
        $this->calls[] = ['encrypt', $text];

        return $this->encryptReturn;
    }

    public function decrypt($ciphertext)
    {
        $this->calls[] = ['decrypt', $ciphertext];

        return $this->decryptReturn;
    }

    public function hmac($text)
    {
        $this->calls[] = ['hmac', $text];

        return $this->hmacReturn;
    }

    public function verify($text, $hmac)
    {
        $this->calls[] = ['verify', $text, $hmac];

        return $this->verifyReturn;
    }

    public function getConfig()
    {
        return $this->configReturn;
    }

    public function getDatabaseCreds()
    {
        return $this->dbReturn;
    }

    public function hasEncryption()
    {
        return $this->encryption;
    }
}
