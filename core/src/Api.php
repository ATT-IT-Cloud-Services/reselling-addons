<?php

declare(strict_types=1);

namespace IgniteReseller;

use Ignite\ApiClient\ClientKind;
use Ignite\ApiClient\HttpTransport;
use Ignite\ApiClient\IgniteClient;
use Ignite\ApiClient\IgniteClientConfig;

/**
 * Single entry point to the official ignite API client.
 *
 * `client` is the typed SDK (catalog, actions with array responses).
 * `transport` is the SDK's own HTTP transport, used for read endpoints whose
 * typed models only expose a small subset of the response (e.g. services, which
 * return network/specs/capabilities nested). Both share one config, so auth,
 * headers and error handling (ApiException with a machine-readable code) are
 * identical.
 *
 * Auth note: the platform accepts the API key only in the X-Api-Key header; the
 * Bearer slot is reserved for 64-hex session tokens, so no token is set.
 */
final class Api
{
    public readonly IgniteClient $client;
    public readonly HttpTransport $transport;

    private readonly ?CacheStore $cache;

    public function __construct(private readonly Config $config)
    {
        $clientConfig = new IgniteClientConfig(
            baseUrl: $config->apiBaseUrl,
            token: null,
            clientKind: ClientKind::Api,
            headers: ['X-Api-Key' => $config->apiKey],
            sandbox: $config->sandbox,
        );

        $this->client = new IgniteClient($clientConfig);
        $this->transport = new HttpTransport($clientConfig);
        $this->cache = $config->catalogTtlSeconds > 0 ? new FileCache($config->cacheDir) : null;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function cache(): ?CacheStore
    {
        return $this->cache;
    }

    /** @param array<string, scalar|null>|null $query @param array<string, string> $headers @return array<string, mixed> */
    public function get(string $path, ?array $query = null, array $headers = []): array
    {
        return $this->transport->get($path, $query, $headers);
    }

    /** @param array<string, mixed>|null $body @param array<string, string> $headers @return array<string, mixed> */
    public function post(string $path, ?array $body = null, array $headers = []): array
    {
        return $this->transport->post($path, $body, $headers);
    }

    /** @param array<string, mixed>|null $body @param array<string, string> $headers @return array<string, mixed> */
    public function put(string $path, ?array $body = null, array $headers = []): array
    {
        return $this->transport->put($path, $body, $headers);
    }

    /** @param array<string, mixed>|null $body @param array<string, string> $headers @return array<string, mixed> */
    public function patch(string $path, ?array $body = null, array $headers = []): array
    {
        return $this->transport->patch($path, $body, $headers);
    }

    /** @param array<string, scalar|null>|null $query @param array<string, mixed>|null $body @param array<string, string> $headers @return array<string, mixed> */
    public function delete(string $path, ?array $query = null, ?array $body = null, array $headers = []): array
    {
        return $this->transport->delete($path, $query, $body, $headers);
    }

    /** Connectivity probe used by the platform "Test connection" buttons. */
    public function ping(): bool
    {
        $result = $this->get('/ping');

        return ($result['success'] ?? false) === true || ($result['ok'] ?? false) === true;
    }

    /** @return array{ok: bool, data: array<string, mixed>, code: string, status: int, message: string} */
    public function guard(callable $call, string $locale = 'de'): array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = $call();

            return ['ok' => true, 'data' => $data, 'code' => '', 'status' => 200, 'message' => ''];
        } catch (\Throwable $e) {
            $described = ErrorTranslator::describe($e, $locale);

            return ['ok' => false, 'data' => [], 'code' => $described['code'], 'status' => $described['status'], 'message' => $described['message']];
        }
    }
}
