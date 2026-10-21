<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Immutable connection + surface configuration for a reseller integration.
 *
 * The API key belongs to the reseller account on the Ignite platform. The
 * reseller is a customer of the platform, not a platform administrator, so
 * there is no admin-ui target here: the reseller runs everything from their own
 * WHMCS/Paymenter interface. Only the customer frontend URL is needed, and only
 * so the reseller's own staff can open a service in their Ignite account.
 */
final class Config
{
    /**
     * @param string      $apiBaseUrl        Host-root API base URL, e.g. https://api.<platform-domain>
     * @param string      $apiKey            Ignite API key (ek_…). Sent as X-Api-Key.
     * @param string|null $webAppBaseUrl     Ignite customer frontend base URL, e.g. https://www.<platform-domain>
     * @param string      $locale            Catalog language (de|en) and UI language default
     * @param bool        $sandbox           Send x-sandbox and run against placeholder data
     * @param int         $catalogTtlSeconds Catalog cache TTL in seconds (0 disables caching)
     * @param string|null $cacheDir          Directory for the file cache; null uses the system temp dir
     */
    public function __construct(
        public readonly string $apiBaseUrl,
        public readonly string $apiKey,
        public readonly ?string $webAppBaseUrl = null,
        public readonly string $locale = 'de',
        public readonly bool $sandbox = false,
        public readonly int $catalogTtlSeconds = 900,
        public readonly ?string $cacheDir = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $apiBaseUrl = rtrim((string) ($data['apiBaseUrl'] ?? $data['api_base_url'] ?? ''), '/');
        if ($apiBaseUrl === '') {
            throw new \InvalidArgumentException('apiBaseUrl is required');
        }

        $apiKey = (string) ($data['apiKey'] ?? $data['api_key'] ?? '');
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }

        $webAppBaseUrl = rtrim((string) ($data['webAppBaseUrl'] ?? $data['web_app_base_url'] ?? ''), '/');

        return new self(
            apiBaseUrl: $apiBaseUrl,
            apiKey: $apiKey,
            webAppBaseUrl: $webAppBaseUrl === '' ? null : $webAppBaseUrl,
            locale: (string) ($data['locale'] ?? 'de') === 'en' ? 'en' : 'de',
            sandbox: (bool) ($data['sandbox'] ?? false),
            catalogTtlSeconds: (int) ($data['catalogTtlSeconds'] ?? $data['catalog_ttl_seconds'] ?? 900),
            cacheDir: isset($data['cacheDir']) ? (string) $data['cacheDir'] : (isset($data['cache_dir']) ? (string) $data['cache_dir'] : null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'apiBaseUrl' => $this->apiBaseUrl,
            'webAppBaseUrl' => $this->webAppBaseUrl,
            'locale' => $this->locale,
            'sandbox' => $this->sandbox,
            'catalogTtlSeconds' => $this->catalogTtlSeconds,
            'cacheDir' => $this->cacheDir,
        ];
    }
}
