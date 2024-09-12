<?php

namespace Mdma4d\Vault;

use GuzzleHttp\Client;
use Mdma4d\Vault\VaultException;

class Vault
{
    protected $config;
    private $_client;

    public function __construct($config)
    {
        $this->config = $config;
    }

    // Public methods
    public function encrypt($text)
    {
        return $this->processEncryption($text, 'encrypt');
    }

    public function decrypt($ciphertext)
    {
        return $this->processEncryption($ciphertext, 'decrypt', true);
    }

    public function hmac($text)
    {
        return $this->processHmacRequest($text, 'hmac');
    }

    public function verify($text, $hmac)
    {
        return $this->processHmacRequest($text, 'verify', $hmac);
    }

    public function getConfig()
    {
        return $this->fetchVaultData($this->config['config'] ?? null);
    }

    public function getDatabaseCreds()
    {
        return $this->fetchVaultData($this->config['database'] ?? null);
    }

    // Helper methods
    protected function processEncryption($data, $action, $isDecrypt = false)
    {
        $path = $this->getEncryptionPath($action);
        if (empty($path)) {
            return $data;
        }

        $payload = $isDecrypt ? ['ciphertext' => $data] : ['plaintext' => base64_encode($data)];
        $result = $this->post($path, $payload);

        $responseKey = $isDecrypt ? 'plaintext' : 'ciphertext';
        return $this->extractVaultData($result, $responseKey, $isDecrypt);
    }

    protected function processHmacRequest($text, $action, $hmac = null)
    {
        $path = $this->getHmacPath($action);
        $payload = ['input' => base64_encode($text)];

        if ($action === 'verify') {
            $payload['hmac'] = $hmac;
        }

        $result = $this->post($path, $payload);
        $responseKey = $action === 'verify' ? 'valid' : 'hmac';

        return $this->extractVaultData($result, $responseKey);
    }

    protected function fetchVaultData($path)
    {
        if (empty($path)) {
            return null;
        }

        $result = $this->get($path);

        return $result['data'] ?? null;
    }

    protected function extractVaultData($result, $key, $decode = false)
    {
        if (!empty($result['errors'])) {
            throw new VaultException($result['errors'][0]);
        }

        if (!isset($result['data'][$key])) {
            throw new VaultException("No $key in Vault response");
        }

        return $decode ? base64_decode($result['data'][$key]) : $result['data'][$key];
    }

    // Path methods
    protected function getEncryptionPath($type)
    {
        return $this->hasEncryption()
            ? "{$this->config['transit']['path']}/$type/{$this->config['transit']['key']}"
            : null;
    }

    protected function getHmacPath($action)
    {
        $transit = $this->config['hmac']['path'] ?? $this->config['transit']['path'];
        $keyRing = $this->config['hmac']['key'] ?? $this->config['transit']['key'];

        return isset($this->config['address'], $transit, $keyRing)
            ? "$transit/$action/$keyRing/sha3-256"
            : null;
    }

    // Vault API request methods
    protected function post($path, $data)
    {
        $this->ensureLoggedIn();

        return $this->sendRequest('POST', $path, $data);
    }

    protected function get($path)
    {
        $this->ensureLoggedIn();

        return $this->sendRequest('GET', $path);
    }

    protected function sendRequest($method, $path, $data = [])
    {
        $client = new Client(['verify' => false]);
        $headers = [
            'X-Vault-Token' => $this->config['token'],
            'Content-Type' => 'application/json',
        ];

        $options = [
            'headers' => $headers,
            'body' => json_encode($data),
        ];

        $response = $client->request($method, $this->config['address'] . $path, $options);

        if ($response->getStatusCode() !== 200) {
            throw new VaultException('Vault response error: ' . $response->getBody());
        }

        return json_decode($response->getBody(), true);
    }

    // Authentication and utility methods
    protected function ensureLoggedIn()
    {
        if (empty($this->config['token'])) {
            $this->login();
        }
    }

    protected function login()
    {
        try {
            if (!empty($this->config['role_id']) && !empty($this->config['secret_id'])) {
                $data = [
                    "role_id" => $this->config['role_id'],
                    "secret_id" => $this->config['secret_id'],
                ];

                $client = new Client(['verify' => false]);
                $response = $client->post(
                    $this->config['address'] . '/v1/auth/approle/login',
                    [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode($data),
                    ]
                );

                if ($response->getStatusCode() !== 200) {
                    throw new VaultException('Vault login response error');
                }

                $responseData = json_decode($response->getBody(), true);
                $this->config['token'] = $responseData['auth']['client_token'] ?? null;

                if (empty($this->config['token'])) {
                    throw new VaultException('Invalid token in Vault login response');
                }
            }
        } catch (Exception $e) {
            throw new VaultException('Vault error: ' . $e->getMessage());
        }
    }

    public function hasEncryption()
    {
        return isset($this->config['address'], $this->config['transit']['path'], $this->config['transit']['key']);
    }
}
