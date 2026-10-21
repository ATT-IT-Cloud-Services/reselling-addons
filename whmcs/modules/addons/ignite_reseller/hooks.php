<?php

/**
 * Ignite Reseller addon hooks (admin only).
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/lib/Addon.php';

use IgniteReseller\Config;
use IgniteReseller\Reseller;

/**
 * Warm the catalog cache once per day so admin pages stay fast and the
 * reseller's product mapping is validated against the live API.
 */
add_hook('DailyCronJob', 1, static function (): void {
    if (!class_exists('\WHMCS\Database\Capsule')) {
        return;
    }

    try {
        $settings = \WHMCS\Database\Capsule::table('tbladdonmodules')
            ->where('module', 'ignite_reseller')
            ->pluck('value', 'setting')
            ->all();
    } catch (\Throwable) {
        return;
    }

    if (!is_array($settings) || empty($settings['api_base_url']) || empty($settings['api_key'])) {
        return;
    }

    try {
        $reseller = new Reseller(Config::fromArray([
            'apiBaseUrl' => $settings['api_base_url'],
            'apiKey' => $settings['api_key'],
            'locale' => $settings['locale'] ?? 'de',
            'sandbox' => !empty($settings['sandbox']),
            'catalogTtlSeconds' => (int) ($settings['catalog_ttl_seconds'] ?? 900),
        ]));

        $reseller->catalog->tree(true);
    } catch (\Throwable) {
        // API unavailable: next run retries.
    }
});
