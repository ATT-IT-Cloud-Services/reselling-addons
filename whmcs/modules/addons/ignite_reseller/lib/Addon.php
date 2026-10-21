<?php

declare(strict_types=1);

namespace IgniteReseller\Whmcs;

use IgniteReseller\Config;
use IgniteReseller\Reseller;

/**
 * WHMCS addon glue: builds the reseller config from the addon settings.
 *
 * Admin-only. The addon intentionally exposes no client-area page, so there is
 * no account-wide view data or dispatcher here.
 */
final class Addon
{
    public const MODULE = 'ignite_reseller';

    /** @param array<string, mixed> $vars */
    public static function reseller(array $vars): Reseller
    {
        return new Reseller(self::config($vars));
    }

    /** @param array<string, mixed> $vars */
    public static function config(array $vars): Config
    {
        return Config::fromArray([
            'apiBaseUrl' => (string) ($vars['api_base_url'] ?? ''),
            'apiKey' => (string) ($vars['api_key'] ?? ''),
            'webAppBaseUrl' => $vars['web_app_base_url'] ?? null,
            'locale' => $vars['locale'] ?? 'de',
            'sandbox' => !empty($vars['sandbox']),
            'catalogTtlSeconds' => (int) ($vars['catalog_ttl_seconds'] ?? 900),
        ]);
    }

    /** @param array<string, mixed> $vars */
    public static function isConfigured(array $vars): bool
    {
        return trim((string) ($vars['api_base_url'] ?? '')) !== '' && trim((string) ($vars['api_key'] ?? '')) !== '';
    }
}
