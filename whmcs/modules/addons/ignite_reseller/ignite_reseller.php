<?php

/**
 * WHMCS addon module for Ignite.
 *
 * Connection settings, a live catalog browser and an account-wide service
 * overview for the reseller's own WHMCS admin.
 *
 * The reseller is an Ignite customer, not a platform administrator, so this
 * module intentionally has no client-area page: account-wide resources (all
 * services, domains, SSH keys, subscriptions, invoices) must never be exposed
 * to end customers. Each end customer manages only their own service through
 * the provisioning module.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
require_once __DIR__ . '/lib/Addon.php';

use IgniteReseller\Whmcs\Addon;

function ignite_reseller_config(): array
{
    return [
        'name' => 'Ignite Reseller',
        'description' => 'Reselling addon for the Ignite platform: provision and manage Proxmox, Plesk, Pterodactyl, Mailcow and domains. Admin-only.',
        'author' => 'Ignite',
        'version' => '1.0.0',
        'language' => 'german',
        'fields' => [
            'api_base_url' => [
                'FriendlyName' => 'API Base URL',
                'Type' => 'text',
                'Size' => '50',
                'Default' => 'https://api.example.test',
                'Description' => 'Host-root API base URL, e.g. https://api.&lt;platform-domain&gt;',
            ],
            'api_key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Ignite API key (ek_…). Needs read/write and services.read/services.write.',
            ],
            'web_app_base_url' => [
                'FriendlyName' => 'Web App Base URL',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Ignite customer frontend, e.g. https://www.&lt;platform-domain&gt;. Used for admin links only.',
            ],
            'locale' => [
                'FriendlyName' => 'Language',
                'Type' => 'dropdown',
                'Options' => 'de,en',
                'Default' => 'de',
            ],
            'sandbox' => [
                'FriendlyName' => 'Sandbox Mode',
                'Type' => 'yesno',
                'Default' => '',
            ],
            'catalog_ttl_seconds' => [
                'FriendlyName' => 'Catalog Cache TTL (seconds)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '900',
            ],
        ],
    ];
}

function ignite_reseller_activate(): array
{
    return ['status' => 'success', 'description' => 'Ignite Reseller activated.'];
}

function ignite_reseller_deactivate(): array
{
    return ['status' => 'success', 'description' => 'Ignite Reseller deactivated.'];
}

function ignite_reseller_upgrade(array $vars): void
{
    // No schema changes yet.
}

/**
 * Admin page: connection status, catalog browser and an account-wide service
 * overview. The link opens the service in the reseller's own Ignite account;
 * there is no link into a platform admin UI because the reseller is a customer.
 *
 * @param array<string, mixed> $vars
 */
function ignite_reseller_output(array $vars): void
{
    echo '<h2>Ignite Reseller</h2>';

    if (!Addon::isConfigured($vars)) {
        echo '<div class="alert alert-warning">Bitte API Base URL und API Key in den Addon-Einstellungen hinterlegen.</div>';

        return;
    }

    try {
        $reseller = Addon::reseller($vars);
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';

        return;
    }

    $test = $reseller->testConnection();
    echo '<p><strong>Verbindung:</strong> ' . ($test['ok'] ? '<span style="color:#2e7d32">OK</span>' : '<span style="color:#c62828">' . htmlspecialchars($test['message']) . '</span>') . '</p>';
    echo '<p><strong>API:</strong> <code>' . htmlspecialchars($reseller->config->apiBaseUrl) . '</code></p>';

    // Catalog.
    echo '<h3>Katalog</h3>';
    try {
        if (isset($_GET['ignite_refresh'])) {
            $reseller->catalog->tree(true);
            echo '<div class="alert alert-success">Katalog aktualisiert.</div>';
        }
        $products = $reseller->catalog->flatten();
        echo '<p>' . count($products) . ' Produkte in ' . count($reseller->catalog->tree()) . ' Kategorien. '
            . '<a class="btn btn-default btn-xs" href="' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . '&ignite_refresh=1">Aktualisieren</a></p>';
        echo '<table class="table table-striped table-condensed"><thead><tr><th>Kategorie</th><th>Produkt</th><th>Anbieter</th><th>Monatlich</th></tr></thead><tbody>';
        foreach ($products as $entry) {
            $type = $reseller->catalog->providerType($entry) ?? '-';
            if (!in_array($type, \IgniteReseller\Catalog::supportedProviderTypes(), true)) {
                continue;
            }
            echo '<tr><td>' . htmlspecialchars($entry['categoryPath']) . '</td>'
                . '<td>' . htmlspecialchars($entry['name']) . '</td>'
                . '<td>' . htmlspecialchars($type) . '</td>'
                . '<td>' . htmlspecialchars((string) $entry['priceMonthly']) . '</td></tr>';
        }
        echo '</tbody></table>';
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">' . htmlspecialchars(\IgniteReseller\ErrorTranslator::message($e, (string) ($vars['locale'] ?? 'de'))) . '</div>';
    }

    // Services.
    echo '<h3>Services</h3>';
    try {
        $list = $reseller->services->list(50);
        $services = $list['servers'] ?? $list['services'] ?? [];
        echo '<table class="table table-striped table-condensed"><thead><tr><th>Name</th><th>Status</th><th>Produkt</th><th>Panel</th></tr></thead><tbody>';
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $web = $reseller->links->webService((string) ($service['id'] ?? ''));
            echo '<tr><td>' . htmlspecialchars((string) ($service['name'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($service['status'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($service['product'] ?? '')) . '</td>'
                . '<td>' . ($web ? '<a href="' . htmlspecialchars($web) . '" target="_blank" rel="noopener">Im Ignite-Panel öffnen</a>' : '') . '</td></tr>';
        }
        echo '</tbody></table>';
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">' . htmlspecialchars(\IgniteReseller\ErrorTranslator::message($e, (string) ($vars['locale'] ?? 'de'))) . '</div>';
    }
}

/** @param array<string, mixed> $vars */
function ignite_reseller_sidebar(array $vars): string
{
    return '<div class="sidebar-item"><a href="addonmodules.php?module=ignite_reseller">Ignite Katalog &amp; Services</a></div>';
}
