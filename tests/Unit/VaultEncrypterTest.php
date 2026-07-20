<?php

namespace Mdma4d\Vault\Tests\Unit;

use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter as LaravelEncrypter;
use Mdma4d\Vault\Tests\Doubles\FakeVault;
use Mdma4d\Vault\Tests\TestCase;
use Mdma4d\Vault\VaultEncrypter;

class VaultEncrypterTest extends TestCase
{
    private const CIPHER = 'AES-256-CBC';

    /** @var string */
    private $key;

    /** @var FakeVault */
    private $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = str_repeat('a', 32); // valid 256-bit key
        $this->fake = new FakeVault;
        $this->app->instance('vault', $this->fake);
    }

    private function encrypter(): VaultEncrypter
    {
        return new VaultEncrypter($this->key, self::CIPHER);
    }

    public function test_it_can_be_instantiated_on_current_laravel(): void
    {
        $this->assertInstanceOf(VaultEncrypter::class, $this->encrypter());
    }

    public function test_it_conforms_to_the_laravel_encrypter_contract(): void
    {
        $this->assertInstanceOf(EncrypterContract::class, $this->encrypter());
    }

    public function test_encrypt_delegates_to_vault_and_serializes_by_default(): void
    {
        $value = ['user' => 1];
        $result = $this->encrypter()->encrypt($value);

        $this->assertSame($this->fake->encryptReturn, $result);
        $this->assertSame(['encrypt', serialize($value)], $this->fake->calls[0]);
    }

    public function test_encrypt_without_serialization_passes_raw_value(): void
    {
        $this->encrypter()->encrypt('raw-value', false);

        $this->assertSame(['encrypt', 'raw-value'], $this->fake->calls[0]);
    }

    public function test_decrypt_vault_ciphertext_and_unserializes_by_default(): void
    {
        $original = ['user' => 1];
        $this->fake->decryptReturn = serialize($original);

        $result = $this->encrypter()->decrypt('vault:v1:cipher');

        $this->assertSame($original, $result);
        $this->assertSame(['decrypt', 'vault:v1:cipher'], $this->fake->calls[0]);
    }

    public function test_decrypt_vault_ciphertext_without_unserialization(): void
    {
        $this->fake->decryptReturn = 'plain-decrypted';

        $result = $this->encrypter()->decrypt('vault:v1:cipher', false);

        $this->assertSame('plain-decrypted', $result);
    }

    public function test_decrypt_falls_back_to_standard_encrypter_for_legacy_ciphertext(): void
    {
        // A payload produced by the stock Laravel encrypter (no "vault:" prefix).
        $legacy = new LaravelEncrypter($this->key, self::CIPHER);
        $payload = $legacy->encrypt('legacy-secret');

        $result = $this->encrypter()->decrypt($payload);

        $this->assertSame('legacy-secret', $result);
        // Vault must not be consulted for legacy ciphertext.
        $this->assertSame([], $this->fake->calls);
    }

    public function test_encrypt_string_delegates_to_vault(): void
    {
        $result = $this->encrypter()->encryptString('a-string');

        $this->assertSame($this->fake->encryptReturn, $result);
        $this->assertSame(['encrypt', 'a-string'], $this->fake->calls[0]);
    }

    public function test_decrypt_string_delegates_to_vault_for_vault_ciphertext(): void
    {
        $this->fake->decryptReturn = 'a-string';

        $result = $this->encrypter()->decryptString('vault:v1:cipher');

        $this->assertSame('a-string', $result);
    }

    public function test_get_key_is_inherited_from_parent(): void
    {
        $this->assertSame($this->key, $this->encrypter()->getKey());
    }

    public function test_key_rotation_methods_when_supported(): void
    {
        $encrypter = $this->encrypter();

        // getAllKeys()/getPreviousKeys() only exist on Laravel 11+.
        if (!method_exists($encrypter, 'getAllKeys')) {
            $this->markTestSkipped('Key rotation methods are not available on this Laravel version.');
        }

        $this->assertContains($this->key, $encrypter->getAllKeys());
        $this->assertIsArray($encrypter->getPreviousKeys());
    }
}
