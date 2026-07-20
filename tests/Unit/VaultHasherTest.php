<?php

namespace Mdma4d\Vault\Tests\Unit;

use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Mdma4d\Vault\Tests\Doubles\FakeVault;
use Mdma4d\Vault\Tests\TestCase;
use Mdma4d\Vault\VaultHasher;

class VaultHasherTest extends TestCase
{
    /** @var FakeVault */
    private $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeVault;
        $this->app->instance('vault', $this->fake);
    }

    public function test_it_conforms_to_the_hasher_contract(): void
    {
        $this->assertInstanceOf(HasherContract::class, new VaultHasher);
    }

    public function test_make_delegates_to_vault_hmac(): void
    {
        $this->assertSame($this->fake->hmacReturn, (new VaultHasher)->make('password'));
        $this->assertSame(['hmac', 'password'], $this->fake->calls[0]);
    }

    public function test_check_returns_true_for_valid_vault_hash(): void
    {
        $this->fake->verifyReturn = true;

        $this->assertTrue((new VaultHasher)->check('password', 'vault:v1:hmac'));
        $this->assertSame(['verify', 'password', 'vault:v1:hmac'], $this->fake->calls[0]);
    }

    public function test_check_returns_false_for_invalid_vault_hash(): void
    {
        $this->fake->verifyReturn = false;

        $this->assertFalse((new VaultHasher)->check('password', 'vault:v1:hmac'));
    }

    public function test_needs_rehash_is_false_for_vault_hash(): void
    {
        $this->assertFalse((new VaultHasher)->needsRehash('vault:v1:hmac'));
    }

    public function test_needs_rehash_is_true_for_legacy_hash(): void
    {
        $legacy = Hash::driver('bcrypt')->make('secret');

        $this->assertTrue((new VaultHasher)->needsRehash($legacy));
    }

    public function test_check_falls_back_to_old_driver_for_legacy_hash(): void
    {
        Config::set('hashing.old', 'bcrypt');
        $legacy = Hash::driver('bcrypt')->make('secret');

        $hasher = new VaultHasher;

        $this->assertTrue($hasher->check('secret', $legacy));
        $this->assertFalse($hasher->check('wrong', $legacy));
    }

    public function test_check_returns_false_for_legacy_hash_when_no_old_driver(): void
    {
        Config::set('hashing.old', '');
        $legacy = Hash::driver('bcrypt')->make('secret');

        $this->assertFalse((new VaultHasher)->check('secret', $legacy));
    }

    public function test_info_returns_password_info(): void
    {
        $legacy = Hash::driver('bcrypt')->make('secret');
        $info = (new VaultHasher)->info($legacy);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('algoName', $info);
    }

    public function test_vault_hash_driver_is_registered_by_service_provider(): void
    {
        $this->assertInstanceOf(VaultHasher::class, Hash::driver('vault'));
    }
}
