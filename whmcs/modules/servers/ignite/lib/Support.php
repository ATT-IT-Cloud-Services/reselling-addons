<?php

declare(strict_types=1);

namespace IgniteReseller\Whmcs;

use IgniteReseller\Catalog;
use IgniteReseller\Config;
use IgniteReseller\Reseller;

/**
 * WHMCS glue: resolves the API connection from either the assigned WHMCS server
 * or the addon module settings, maps WHMCS module config options to catalog
 * entries, and stores the remote Ignite service id per WHMCS service.
 */
final class Support
{
    public const ADDON = 'ignite_reseller';
    public const MAP_TABLE = 'mod_ignite_services';

    /**
     * Build the reseller config for a module call.
     *
     * @param array<string, mixed> $params WHMCS module params
     */
    public static function reseller(array $params): Reseller
    {
        return new Reseller(self::configFromParams($params));
    }

    /** @param array<string, mixed> $params */
    public static function configFromParams(array $params): Config
    {
        $addon = self::addonSettings();

        $base = trim((string) ($params['serverhostname'] ?? ''));
        $key = trim((string) ($params['serverpassword'] ?? ''));
        if ($key === '' && !empty($params['serveraccesshash'])) {
            $key = trim((string) $params['serveraccesshash']);
        }

        if ($base === '') {
            $base = (string) ($addon['api_base_url'] ?? '');
        }
        if ($key === '') {
            $key = (string) ($addon['api_key'] ?? '');
        }

        return Config::fromArray([
            'apiBaseUrl' => $base,
            'apiKey' => $key,
            'webAppBaseUrl' => $addon['web_app_base_url'] ?? null,
            'locale' => $addon['locale'] ?? 'de',
            'sandbox' => !empty($addon['sandbox']),
            'catalogTtlSeconds' => (int) ($addon['catalog_ttl_seconds'] ?? 900),
        ]);
    }

    /**
     * Addon settings from tbladdonmodules. Falls back to an empty set when the
     * addon is not installed, so the server module can run purely on server
     * credentials.
     *
     * @return array<string, mixed>
     */
    public static function addonSettings(): array
    {
        if (!class_exists('\WHMCS\Database\Capsule')) {
            return [];
        }

        try {
            $rows = \WHMCS\Database\Capsule::table('tbladdonmodules')
                ->where('module', self::ADDON)
                ->pluck('value', 'setting')
                ->all();
        } catch (\Throwable) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * Resolve the mapped catalog entry for a product and its module config.
     *
     * @param array<string, mixed> $params
     */
    public static function entry(Reseller $reseller, array $params): ?array
    {
        $key = trim((string) ($params['configoption1'] ?? ''));
        if ($key === '') {
            return null;
        }

        return $reseller->catalog->find($key);
    }

    /**
     * The value stored in config option 1 is the catalog product key
     * ("category-key/product-slug"). It is the stable mapping target.
     *
     * @param array<string, mixed> $params
     */
    public static function productKey(array $params): string
    {
        return trim((string) ($params['configoption1'] ?? ''));
    }

    /** @param array<string, mixed> $params */
    public static function billingCycleDays(array $params): int
    {
        $cycle = (int) ($params['configoption2'] ?? 30);

        return $cycle > 0 ? $cycle : 30;
    }

    /** @param array<string, mixed> $params */
    public static function couponCode(array $params): ?string
    {
        $code = trim((string) ($params['configoption3'] ?? ''));

        return $code === '' ? null : $code;
    }

    /** @param array<string, mixed> $params */
    public static function template(array $params): ?string
    {
        $value = trim((string) ($params['configoption4'] ?? ''));

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $params */
    public static function location(array $params): ?string
    {
        $value = trim((string) ($params['configoption5'] ?? ''));

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $params */
    public static function paymentMethod(array $params): string
    {
        $method = trim((string) ($params['configoption6'] ?? 'guthaben'));

        return in_array($method, ['guthaben', 'businessfund', 'invoice'], true) ? $method : 'guthaben';
    }

    /**
     * Stable per-service idempotency key so a retried provisioning call returns
     * the first result instead of ordering twice.
     *
     * @param array<string, mixed> $params
     */
    public static function idempotencyKey(array $params): string
    {
        return 'whmcs-' . (string) ($params['serviceid'] ?? '0');
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>
     */
    public static function sshKeyIds(array $params): array
    {
        $raw = trim((string) ($params['configoption7'] ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** @param array<string, mixed> $params */

    /** @param array<string, mixed> $params */
    public static function remoteServiceId(array $params): ?string
    {
        $fromModel = self::remoteIdFromProperties($params);
        if ($fromModel !== null) {
            return $fromModel;
        }

        $hostingId = (int) ($params['serviceid'] ?? 0);
        if ($hostingId <= 0 || !self::hasMapTable()) {
            return null;
        }

        try {
            $value = \WHMCS\Database\Capsule::table(self::MAP_TABLE)->where('hosting_id', $hostingId)->value('ignite_service_id');
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $params */
    public static function storeRemoteServiceId(array $params, string $serviceId): void
    {
        self::storeInProperties($params, $serviceId);

        $hostingId = (int) ($params['serviceid'] ?? 0);
        if ($hostingId <= 0 || !class_exists('\WHMCS\Database\Capsule')) {
            return;
        }

        try {
            self::ensureMapTable();
            $exists = \WHMCS\Database\Capsule::table(self::MAP_TABLE)->where('hosting_id', $hostingId)->exists();
            if ($exists) {
                \WHMCS\Database\Capsule::table(self::MAP_TABLE)->where('hosting_id', $hostingId)->update([
                    'ignite_service_id' => $serviceId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                \WHMCS\Database\Capsule::table(self::MAP_TABLE)->insert([
                    'hosting_id' => $hostingId,
                    'ignite_service_id' => $serviceId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable) {
            // Non-fatal: serviceProperties is the primary store.
        }
    }

    /** @param array<string, mixed> $params */
    public static function forgetRemoteServiceId(array $params): void
    {
        $hostingId = (int) ($params['serviceid'] ?? 0);
        if ($hostingId > 0 && self::hasMapTable()) {
            try {
                \WHMCS\Database\Capsule::table(self::MAP_TABLE)->where('hosting_id', $hostingId)->delete();
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    /** @param array<string, mixed> $params */
    private static function remoteIdFromProperties(array $params): ?string
    {
        $model = $params['model'] ?? null;
        if (!is_object($model) || !isset($model->serviceProperties)) {
            return null;
        }

        try {
            $value = $model->serviceProperties->get('ignite_service_id');
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $params */
    private static function storeInProperties(array $params, string $serviceId): void
    {
        $model = $params['model'] ?? null;
        if (!is_object($model) || !isset($model->serviceProperties)) {
            return;
        }

        try {
            $model->serviceProperties->save(['ignite_service_id' => $serviceId]);
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function hasMapTable(): bool
    {
        if (!class_exists('\WHMCS\Database\Capsule')) {
            return false;
        }

        try {
            return \WHMCS\Database\Capsule::schema()->hasTable(self::MAP_TABLE);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function ensureMapTable(): void
    {
        if (self::hasMapTable()) {
            return;
        }

        \WHMCS\Database\Capsule::schema()->create(self::MAP_TABLE, static function ($table): void {
            $table->increments('id');
            $table->integer('hosting_id')->unique();
            $table->string('ignite_service_id');
            $table->timestamps();
        });
    }

    /**
     * Render a product select for the module config page. The loader returns
     * value => label; WHMCS calls it while rendering the product's Module
     * Settings.
     *
     * @param array<string, mixed> $params
     * @return array<string, string>
     */
    public static function productOptions(array $params): array
    {
        try {
            $reseller = self::reseller($params);
            $options = $reseller->catalog->options();
        } catch (\Throwable) {
            return [];
        }

        return $options;
    }

    /** @param array<string, mixed> $params */
    public static function log(string $action, array $params, mixed $request, mixed $response): void
    {
        if (!function_exists('logModuleCall')) {
            return;
        }

        try {
            logModuleCall(
                'ignite',
                $action,
                $request,
                $response,
                '',
                [
                    (string) ($params['serverpassword'] ?? ''),
                    (string) (self::addonSettings()['api_key'] ?? ''),
                ],
            );
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * A human name for the remote service. Falls back to the WHMCS domain.
     *
     * @param array<string, mixed> $params
     */
    public static function serviceName(array $params): string
    {
        $domain = trim((string) ($params['domain'] ?? ''));
        if ($domain !== '') {
            return $domain;
        }

        return 'whmcs-' . (string) ($params['serviceid'] ?? '0');
    }

    /**
     * Filter catalog options to the provider types this addon sells.
     *
     * @return array<string, string>
     */
    public static function supportedProductOptions(array $params, Catalog $catalog): array
    {
        $options = [];
        foreach ($catalog->flatten() as $entry) {
            $type = $catalog->providerType($entry);
            if ($type === null || !in_array($type, Catalog::supportedProviderTypes(), true)) {
                continue;
            }
            $options[$entry['key']] = $entry['categoryPath'] . ' / ' . $entry['name'];
        }

        return $options;
    }
}
