<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Reads the public product catalog from the API.
 *
 * Products are never hardcoded: the reseller maps WHMCS/Paymenter products to
 * the categories and products the configured API actually exposes. The catalog
 * is cached (see Config::$catalogTtlSeconds) because it changes rarely.
 */
final class Catalog
{
    private ?array $treeCache = null;

    public function __construct(
        private readonly Api $api,
        private readonly Config $config,
        private readonly ?CacheStore $cache = null,
    ) {
    }

    /** @return list<array<string, mixed>> Raw category forest. */
    public function tree(bool $refresh = false): array
    {
        if (!$refresh && $this->treeCache !== null) {
            return $this->treeCache;
        }

        $cacheKey = 'catalog.tree.' . $this->config->locale;
        if (!$refresh && $this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $this->treeCache = $cached;
            }
        }

        $response = $this->api->client->shop->products($this->config->locale)->toArray();
        $categories = [];
        foreach ($response['categories'] ?? [] as $category) {
            if (is_array($category)) {
                $categories[] = $category;
            }
        }

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $categories, $this->config->catalogTtlSeconds);
        }

        return $this->treeCache = $categories;
    }

    /** @return list<array<string, mixed>> Flattened product entries across all categories. */
    public function flatten(bool $refresh = false): array
    {
        $out = [];
        $this->walk($this->tree($refresh), null, $out);

        return $out;
    }

    /** @param list<array<string, mixed>> $categories @param list<array<string, mixed>> $out */
    private function walk(array $categories, ?string $parentPath, array &$out): void
    {
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $categoryKey = (string) ($category['key'] ?? '');
            $categoryName = (string) ($category['name'] ?? $categoryKey);
            $categoryProvider = is_array($category['provider'] ?? null) ? $category['provider'] : null;
            $categoryPath = $parentPath !== null ? $parentPath . ' / ' . $categoryName : $categoryName;

            foreach ($category['products'] ?? [] as $product) {
                if (!is_array($product)) {
                    continue;
                }

                $slug = (string) ($product['slug'] ?? '');
                $out[] = [
                    'id' => (string) ($product['id'] ?? ''),
                    'slug' => $slug,
                    'name' => (string) ($product['name'] ?? $slug),
                    'key' => $categoryKey !== '' && $slug !== '' ? $categoryKey . '/' . $slug : (string) ($product['id'] ?? ''),
                    'priceMonthly' => $product['priceMonthly'] ?? null,
                    'setupFee' => $product['setupFee'] ?? null,
                    'categoryId' => (string) ($category['id'] ?? ''),
                    'categoryKey' => $categoryKey,
                    'categoryName' => $categoryName,
                    'categoryPath' => $categoryPath,
                    'allowedBillingCycles' => $product['allowedBillingCycles'] ?? [30],
                    'provider' => $categoryProvider,
                    'soldOut' => (bool) ($product['soldOut'] ?? false),
                ];
            }

            if (!empty($category['children']) && is_array($category['children'])) {
                $this->walk($category['children'], $categoryPath, $out);
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function find(string $keyOrId): ?array
    {
        foreach ($this->flatten() as $entry) {
            if ($entry['id'] === $keyOrId || $entry['key'] === $keyOrId || $entry['slug'] === $keyOrId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Full product detail (adds provider, capabilities and customization flags).
     *
     * @return array<string, mixed>
     */
    public function detail(string $category, string $slug): array
    {
        $cacheKey = 'catalog.product.' . $this->config->locale . '.' . $category . '/' . $slug;
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = $this->api->client->shop->get_find($category, $slug, ['lang' => $this->config->locale]);
        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $response, $this->config->catalogTtlSeconds);
        }

        return $response;
    }

    /** @return array<string, mixed>|null */
    public function detailForEntry(array $entry): ?array
    {
        if (($entry['categoryKey'] ?? '') === '' || ($entry['slug'] ?? '') === '') {
            return null;
        }

        try {
            return $this->detail((string) $entry['categoryKey'], (string) $entry['slug']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function providerType(array $entry): ?string
    {
        if (is_array($entry['provider'] ?? null) && !empty($entry['provider']['type'])) {
            return (string) $entry['provider']['type'];
        }

        $detail = $this->detailForEntry($entry);
        $type = $detail['product']['provider']['type'] ?? null;

        return $type !== null ? (string) $type : null;
    }

    public function providerId(array $entry): ?string
    {
        if (is_array($entry['provider'] ?? null) && !empty($entry['provider']['id'])) {
            return (string) $entry['provider']['id'];
        }

        $detail = $this->detailForEntry($entry);
        $id = $detail['product']['provider']['id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

    /** @return list<array<string, mixed>> */
    public function byProviderType(string $type, bool $refresh = false): array
    {
        $matches = [];
        foreach ($this->flatten($refresh) as $entry) {
            if ($this->providerType($entry) === $type) {
                $matches[] = $entry;
            }
        }

        return $matches;
    }

    /** @return array<string, mixed> */
    public function locations(string $productId): array
    {
        return $this->api->client->shop->locations(['productIds' => $productId]);
    }

    /** @return array<string, mixed> */
    public function templates(string $productId): array
    {
        return $this->api->client->shop->templates(['productId' => $productId]);
    }

    /** @return array<string, mixed> */
    public function upgradeConfig(string $categoryId): array
    {
        return $this->api->client->shop->upgrade_config($categoryId);
    }

    /**
     * Options for a "select a product" UI control.
     *
     * @return array<string, string> value => label
     */
    public function options(bool $refresh = false): array
    {
        $options = [];
        foreach ($this->flatten($refresh) as $entry) {
            $label = $entry['categoryPath'] . ' / ' . $entry['name'];
            if (!empty($entry['priceMonthly'])) {
                $label .= ' (' . number_format((float) $entry['priceMonthly'], 2, ',', '.') . ' netto/Monat)';
            }
            $options[$entry['key']] = $label;
        }

        return $options;
    }

    /**
     * Provider types this reseller addon supports for provisioning. Anything
     * outside this list (containers, game servers, …) is intentionally not
     * offered.
     *
     * @return list<string>
     */
    public static function supportedProviderTypes(): array
    {
        return ['proxmox', 'plesk', 'pterodactyl', 'mailcow'];
    }
}
