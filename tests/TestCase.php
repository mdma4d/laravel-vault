<?php

namespace Mdma4d\Vault\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mdma4d\Vault\VaultServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Register the package service provider.
     */
    protected function getPackageProviders($app)
    {
        return [VaultServiceProvider::class];
    }

    /**
     * Build a Guzzle client backed by a queue of mocked responses. The client
     * mirrors the production transport (http_errors disabled) so status-code
     * handling can be exercised. Recorded request/response history is written
     * into $history by reference.
     *
     * @param  array<int, \Psr\Http\Message\ResponseInterface>  $responses
     * @param  array<int, array>  $history
     */
    protected function mockClient(array $responses, ?array &$history = null): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }

        return new Client(['handler' => $stack, 'http_errors' => false]);
    }

    /**
     * Convenience helper to build a JSON Vault response.
     */
    protected function vaultResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * (Re)register and boot the package service provider against the current
     * application configuration, injecting the given HTTP client so no real
     * network request is performed.
     */
    protected function bootPackageWith(Client $client): void
    {
        $this->app->instance(VaultServiceProvider::HTTP_CLIENT, $client);

        $provider = new VaultServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }
}
