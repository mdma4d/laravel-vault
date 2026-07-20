<?php

namespace Mdma4d\Vault\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mdma4d\Vault\Vault;
use Mdma4d\Vault\VaultException;
use PHPUnit\Framework\TestCase;

class VaultTest extends TestCase
{
    /** @var array<int, array> */
    private $history = [];

    /**
     * Build a Vault instance whose transport is a queue of mocked responses.
     *
     * @param  array<int, Response>  $responses
     */
    private function vault(array $config, array $responses): Vault
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $client = new Client(['handler' => $stack, 'http_errors' => false]);

        return new Vault($config, $client);
    }

    private function ok(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function baseConfig(array $overrides = []): array
    {
        return array_merge([
            'address' => 'https://vault.test',
            'token' => 'root-token',
            'transit' => ['path' => '/v1/transit', 'key' => 'appkey'],
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------------

    public function test_approle_login_sends_role_id_and_secret_id(): void
    {
        $vault = $this->vault(
            ['address' => 'https://vault.test', 'role_id' => 'r-1', 'secret_id' => 's-1', 'config' => '/v1/kv/app'],
            [
                $this->ok(['auth' => ['client_token' => 'logged-in-token']]),
                $this->ok(['data' => ['foo' => 'bar']]),
            ]
        );

        $vault->getConfig();

        $loginRequest = $this->history[0]['request'];
        $this->assertStringContainsString('/v1/auth/approle/login', (string) $loginRequest->getUri());
        $body = json_decode((string) $loginRequest->getBody(), true);
        $this->assertSame('r-1', $body['role_id']);
        $this->assertSame('s-1', $body['secret_id']);
    }

    public function test_client_token_from_login_is_used_for_subsequent_requests(): void
    {
        $vault = $this->vault(
            ['address' => 'https://vault.test', 'role_id' => 'r-1', 'secret_id' => 's-1', 'config' => '/v1/kv/app'],
            [
                $this->ok(['auth' => ['client_token' => 'logged-in-token']]),
                $this->ok(['data' => ['foo' => 'bar']]),
            ]
        );

        $vault->getConfig();

        $this->assertCount(2, $this->history);
        $this->assertSame('logged-in-token', $this->history[1]['request']->getHeaderLine('X-Vault-Token'));
    }

    public function test_existing_token_skips_approle_login(): void
    {
        $vault = $this->vault(
            $this->baseConfig(['config' => '/v1/kv/app', 'role_id' => 'r', 'secret_id' => 's']),
            [$this->ok(['data' => ['foo' => 'bar']])]
        );

        $vault->getConfig();

        $this->assertCount(1, $this->history);
        $this->assertSame('root-token', $this->history[0]['request']->getHeaderLine('X-Vault-Token'));
        $this->assertStringNotContainsString('approle/login', (string) $this->history[0]['request']->getUri());
    }

    public function test_missing_token_and_approle_credentials_makes_no_login_request(): void
    {
        $vault = $this->vault(
            ['address' => 'https://vault.test', 'config' => '/v1/kv/app'],
            [$this->ok(['data' => ['foo' => 'bar']])]
        );

        $result = $vault->getConfig();

        $this->assertSame(['foo' => 'bar'], $result);
        $this->assertCount(1, $this->history);
        $this->assertStringNotContainsString('approle/login', (string) $this->history[0]['request']->getUri());
        $this->assertSame('', $this->history[0]['request']->getHeaderLine('X-Vault-Token'));
    }

    public function test_login_without_client_token_throws_vault_exception(): void
    {
        $vault = $this->vault(
            ['address' => 'https://vault.test', 'role_id' => 'r', 'secret_id' => 's', 'config' => '/v1/kv/app'],
            [$this->ok(['auth' => []])]
        );

        $this->expectException(VaultException::class);
        $vault->getConfig();
    }

    public function test_login_error_status_is_converted_to_vault_exception(): void
    {
        $vault = $this->vault(
            ['address' => 'https://vault.test', 'role_id' => 'r', 'secret_id' => 's', 'config' => '/v1/kv/app'],
            [new Response(500, [], 'boom')]
        );

        $this->expectException(VaultException::class);
        $vault->getConfig();
    }

    // ---------------------------------------------------------------------
    // Configuration retrieval
    // ---------------------------------------------------------------------

    public function test_get_config_returns_vault_data(): void
    {
        $vault = $this->vault(
            $this->baseConfig(['config' => '/v1/kv/app']),
            [$this->ok(['data' => ['app' => ['name' => 'demo']]])]
        );

        $this->assertSame(['app' => ['name' => 'demo']], $vault->getConfig());
    }

    public function test_empty_config_path_makes_no_http_request(): void
    {
        $vault = $this->vault($this->baseConfig(), []);

        $this->assertNull($vault->getConfig());
        $this->assertCount(0, $this->history);
    }

    public function test_config_error_status_is_converted_to_vault_exception(): void
    {
        $vault = $this->vault(
            $this->baseConfig(['config' => '/v1/kv/app']),
            [new Response(403, [], 'denied')]
        );

        $this->expectException(VaultException::class);
        $vault->getConfig();
    }

    // ---------------------------------------------------------------------
    // Dynamic database credentials
    // ---------------------------------------------------------------------

    public function test_get_database_creds_returns_username_and_password(): void
    {
        $vault = $this->vault(
            $this->baseConfig(['database' => '/v1/database/creds/app']),
            [$this->ok(['data' => ['username' => 'u', 'password' => 'p']])]
        );

        $this->assertSame(['username' => 'u', 'password' => 'p'], $vault->getDatabaseCreds());
    }

    public function test_empty_database_path_makes_no_http_request(): void
    {
        $vault = $this->vault($this->baseConfig(), []);

        $this->assertNull($vault->getDatabaseCreds());
        $this->assertCount(0, $this->history);
    }

    public function test_database_creds_missing_data_returns_null(): void
    {
        $vault = $this->vault(
            $this->baseConfig(['database' => '/v1/database/creds/app']),
            [$this->ok(['lease_id' => 'x'])]
        );

        $this->assertNull($vault->getDatabaseCreds());
    }

    // ---------------------------------------------------------------------
    // Transit encryption
    // ---------------------------------------------------------------------

    public function test_encrypt_sends_base64_plaintext_and_returns_ciphertext(): void
    {
        $vault = $this->vault(
            $this->baseConfig(),
            [$this->ok(['data' => ['ciphertext' => 'vault:v1:cipher']])]
        );

        $result = $vault->encrypt('secret');

        $this->assertSame('vault:v1:cipher', $result);
        $request = $this->history[0]['request'];
        $this->assertStringContainsString('/v1/transit/encrypt/appkey', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(base64_encode('secret'), $body['plaintext']);
    }

    public function test_decrypt_sends_ciphertext_and_returns_decoded_plaintext(): void
    {
        $vault = $this->vault(
            $this->baseConfig(),
            [$this->ok(['data' => ['plaintext' => base64_encode('secret')]])]
        );

        $result = $vault->decrypt('vault:v1:cipher');

        $this->assertSame('secret', $result);
        $request = $this->history[0]['request'];
        $this->assertStringContainsString('/v1/transit/decrypt/appkey', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('vault:v1:cipher', $body['ciphertext']);
    }

    public function test_without_transit_configuration_encrypt_returns_data_unchanged(): void
    {
        $vault = $this->vault(['address' => 'https://vault.test', 'token' => 't'], []);

        $this->assertSame('plain', $vault->encrypt('plain'));
        $this->assertCount(0, $this->history);
    }

    public function test_encrypt_error_status_is_converted_to_vault_exception(): void
    {
        $vault = $this->vault(
            $this->baseConfig(),
            [new Response(400, [], json_encode(['errors' => ['bad request']]))]
        );

        $this->expectException(VaultException::class);
        $vault->encrypt('secret');
    }

    public function test_encrypt_response_without_ciphertext_throws_vault_exception(): void
    {
        $vault = $this->vault(
            $this->baseConfig(),
            [$this->ok(['data' => []])]
        );

        $this->expectException(VaultException::class);
        $vault->encrypt('secret');
    }

    public function test_vault_errors_array_is_converted_to_vault_exception(): void
    {
        $vault = $this->vault(
            $this->baseConfig(),
            [$this->ok(['errors' => ['permission denied']])]
        );

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('permission denied');
        $vault->encrypt('secret');
    }

    // ---------------------------------------------------------------------
    // HMAC
    // ---------------------------------------------------------------------

    public function test_hmac_builds_correct_request(): void
    {
        $vault = $this->vault(
            $this->baseConfig(),
            [$this->ok(['data' => ['hmac' => 'vault:v1:hmac']])]
        );

        $result = $vault->hmac('message');

        $this->assertSame('vault:v1:hmac', $result);
        $request = $this->history[0]['request'];
        $this->assertStringContainsString('/v1/transit/hmac/appkey/sha3-256', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(base64_encode('message'), $body['input']);
    }

    public function test_verify_returns_true_and_false(): void
    {
        $vaultTrue = $this->vault($this->baseConfig(), [$this->ok(['data' => ['valid' => true]])]);
        $this->assertTrue($vaultTrue->verify('message', 'vault:v1:hmac'));

        $vaultFalse = $this->vault($this->baseConfig(), [$this->ok(['data' => ['valid' => false]])]);
        $this->assertFalse($vaultFalse->verify('message', 'vault:v1:hmac'));
    }

    public function test_verify_sends_hmac_in_payload(): void
    {
        $vault = $this->vault($this->baseConfig(), [$this->ok(['data' => ['valid' => true]])]);

        $vault->verify('message', 'vault:v1:the-hmac');

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('vault:v1:the-hmac', $body['hmac']);
        $this->assertStringContainsString('/verify/', (string) $this->history[0]['request']->getUri());
    }

    public function test_hmac_falls_back_to_shared_transit_settings(): void
    {
        // No dedicated "hmac" settings -> transit path/key are used.
        $vault = $this->vault($this->baseConfig(), [$this->ok(['data' => ['hmac' => 'h']])]);

        $vault->hmac('message');

        $this->assertStringContainsString('/v1/transit/hmac/appkey/sha3-256', (string) $this->history[0]['request']->getUri());
    }

    public function test_dedicated_hmac_settings_take_precedence(): void
    {
        $config = $this->baseConfig([
            'hmac' => ['path' => '/v1/hmac-mount', 'key' => 'hmac-key'],
        ]);
        $vault = $this->vault($config, [$this->ok(['data' => ['hmac' => 'h']])]);

        $vault->hmac('message');

        $this->assertStringContainsString('/v1/hmac-mount/hmac/hmac-key/sha3-256', (string) $this->history[0]['request']->getUri());
    }

    public function test_hmac_without_configuration_throws_vault_exception(): void
    {
        $vault = $this->vault(['address' => 'https://vault.test', 'token' => 't'], []);

        $this->expectException(VaultException::class);
        $vault->hmac('message');
    }

    public function test_original_constructor_signature_still_works(): void
    {
        // Backward compatibility: new Vault($config) must still be valid.
        $vault = new Vault(['address' => 'https://vault.test']);

        $this->assertInstanceOf(Vault::class, $vault);
        $this->assertFalse($vault->hasEncryption());
    }
}
