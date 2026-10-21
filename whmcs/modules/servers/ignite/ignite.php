<?php

/**
 * WHMCS provisioning server module for Ignite.
 *
 * Plain reselling only: Proxmox, Plesk, Pterodactyl, Mailcow and domains.
 *
 * The reseller is an Ignite customer, not a platform administrator. The
 * end-customer client area therefore only ever shows data that belongs to the
 * one mapped service; account-wide resources (all services, all domains, SSH
 * keys, subscriptions, invoices) never reach the client area. Account-wide work
 * belongs to the reseller's own WHMCS admin.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$igniteAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($igniteAutoload)) {
    require_once $igniteAutoload;
}
require_once __DIR__ . '/lib/Support.php';

use IgniteReseller\Reseller;
use IgniteReseller\Whmcs\Support;

function ignite_MetaData(): array
{
    return [
        'DisplayName' => 'Ignite',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

/**
 * Module config options. Order maps to configoption1…7.
 *
 * The product select is filled live from the configured API so a reseller only
 * ever maps to categories/products that actually exist on the platform.
 */
function ignite_ConfigOptions(): array
{
    return [
        'API Product' => [
            'FriendlyName' => 'API-Produkt',
            'Type' => 'dropdown',
            'Loader' => 'ignite_ProductLoader',
            'SimpleMode' => true,
            'Description' => 'Produkt aus dem Ignite-Katalog (Kategorie/Produkt).',
        ],
        'Billing Cycle' => [
            'FriendlyName' => 'Abrechnungszeitraum (Tage)',
            'Type' => 'dropdown',
            'Options' => '7,14,30,60,90,120,365',
            'Default' => '30',
        ],
        'Coupon' => [
            'FriendlyName' => 'Gutscheincode (Preisreduktion)',
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Optional. Wird bei der Buchung angewendet.',
        ],
        'Template' => [
            'FriendlyName' => 'OS/Template',
            'Type' => 'text',
            'Size' => '35',
        ],
        'Location' => [
            'FriendlyName' => 'Standort-ID',
            'Type' => 'text',
            'Size' => '35',
        ],
        'Payment Method' => [
            'FriendlyName' => 'Zahlungsart',
            'Type' => 'dropdown',
            'Options' => 'guthaben,businessfund,invoice',
            'Default' => 'guthaben',
            'Description' => 'Nicht-interaktive Zahlungsart für den automatischen Checkout (Guthaben, BusinessFund oder Rechnung).',
        ],
        'SSH Keys' => [
            'FriendlyName' => 'SSH-Schlüssel-IDs',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Kommagetrennte Ignite-SSH-Key-IDs, die dem Service zugewiesen werden.',
        ],
    ];
}

/**
 * Loader for the API product dropdown.
 *
 * @param array<string, mixed> $params
 * @return array<string, string>
 */
function ignite_ProductLoader(array $params): array
{
    try {
        $reseller = Support::reseller($params);

        return Support::supportedProductOptions($params, $reseller->catalog);
    } catch (\Throwable $e) {
        return [];
    }
}

/** @param array<string, mixed> $params */
function ignite_TestConnection(array $params): array
{
    try {
        $reseller = Support::reseller($params);
        $result = $reseller->testConnection();
        Support::log('TestConnection', $params, [], $result);

        return ['success' => $result['ok'], 'error' => $result['ok'] ? '' : $result['message']];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/** @param array<string, mixed> $params */
function ignite_CreateAccount(array $params): string
{
    try {
        $reseller = Support::reseller($params);
        $entry = Support::entry($reseller, $params);
        if ($entry === null) {
            return 'Kein API-Produkt zugeordnet. Bitte die Modulkonfiguration prüfen.';
        }

        $providerId = $reseller->catalog->providerId($entry);
        if ($providerId === null) {
            return 'Für das gewählte Produkt konnte kein Anbieter ermittelt werden.';
        }

        $options = [
            'productId' => (string) $entry['id'],
            'providerId' => $providerId,
            'billingCycleDays' => Support::billingCycleDays($params),
            'paymentMethod' => Support::paymentMethod($params),
            'idempotencyKey' => Support::idempotencyKey($params),
            'couponCode' => Support::couponCode($params),
        ];

        $result = $reseller->services->create($options);
        Support::log('CreateAccount', $params, $options, $result['raw']);

        if ($result['serviceId'] === null) {
            return 'Es wurde kein Service zurückgegeben. Bitte den API-Schlüssel und die Berechtigungen prüfen.';
        }

        Support::storeRemoteServiceId($params, $result['serviceId']);

        $sshKeyIds = Support::sshKeyIds($params);
        if ($sshKeyIds !== []) {
            try {
                $reseller->sshKeys->assignToService($result['serviceId'], $sshKeyIds);
            } catch (\Throwable $e) {
                Support::log('CreateAccount.sshKeys', $params, $sshKeyIds, $e->getMessage());
            }
        }

        return 'success';
    } catch (\Throwable $e) {
        Support::log('CreateAccount.error', $params, [], $e->getMessage());

        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/** @param array<string, mixed> $params */
function ignite_SuspendAccount(array $params): string
{
    return ignite_serviceAction($params, 'suspend');
}

/** @param array<string, mixed> $params */
function ignite_UnsuspendAccount(array $params): string
{
    return ignite_serviceAction($params, 'resume');
}

/** @param array<string, mixed> $params */
function ignite_TerminateAccount(array $params): string
{
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return 'success';
    }

    try {
        $reseller = Support::reseller($params);
        try {
            $reseller->subscriptions->cancel($serviceId, ['immediate' => true]);
        } catch (\Throwable $e) {
            Support::log('TerminateAccount.cancel', $params, [], $e->getMessage());
        }
        $result = $reseller->services->delete($serviceId);
        Support::log('TerminateAccount', $params, ['serviceId' => $serviceId], $result);
        Support::forgetRemoteServiceId($params);

        return 'success';
    } catch (\Throwable $e) {
        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/** @param array<string, mixed> $params */
function ignite_ChangePassword(array $params): string
{
    $serviceId = Support::remoteServiceId($params);
    $password = (string) ($params['password'] ?? '');
    if ($serviceId === null || $password === '') {
        return 'Kein neues Passwort angegeben.';
    }

    try {
        Support::reseller($params)->services->changePassword($serviceId, $password);

        return 'success';
    } catch (\Throwable $e) {
        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/** @param array<string, mixed> $params */
function ignite_ChangePackage(array $params): string
{
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return 'Der Service ist noch nicht bereitgestellt.';
    }

    try {
        Support::reseller($params)->services->upgradeOptions($serviceId);

        return 'Paketwechsel (Upgrade) sind über die Abo-Schnittstelle zu buchen, nicht direkt über den Modul-API-Schlüssel.';
    } catch (\Throwable $e) {
        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/** @param array<string, mixed> $params */
function ignite_Renew(array $params): string
{
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return 'Der Service ist noch nicht bereitgestellt.';
    }

    try {
        Support::reseller($params)->services->renew($serviceId, Support::billingCycleDays($params));

        return 'success';
    } catch (\Throwable $e) {
        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/**
 * Client area: strictly per-service. No account-wide lists, no Ignite links.
 *
 * @param array<string, mixed> $params
 */
function ignite_ClientArea(array $params): array
{
    $serviceId = Support::remoteServiceId($params);
    $vars = [
        'ignite' => [
            'hasService' => $serviceId !== null,
            'service' => null,
            'capabilities' => [],
            'ipEntries' => [],
            'backups' => [],
            'dnsRecords' => [],
            'domainName' => null,
            'domainId' => null,
            'providerType' => null,
            'notice' => null,
        ],
        'ignite_serviceid' => (string) ($params['serviceid'] ?? ''),
    ];

    if ($serviceId === null) {
        $vars['ignite']['notice'] = 'Der Service wird noch bereitgestellt.';

        return [
            'tabOverviewReplacementTemplate' => 'templates/manage.tpl',
            'templateVariables' => $vars,
        ];
    }

    try {
        $reseller = Support::reseller($params);
        $detail = $reseller->services->get($serviceId);
        $server = is_array($detail['server'] ?? null) ? $detail['server'] : [];
        $provider = $server['provider']['type'] ?? null;

        $vars['ignite']['service'] = $server;
        $vars['ignite']['capabilities'] = is_array($detail['capabilities'] ?? null) ? $detail['capabilities'] : [];
        $vars['ignite']['providerType'] = is_string($provider) ? $provider : null;
        $vars['ignite']['ipEntries'] = $reseller->reverseDns->forService($serviceId);

        try {
            $backupList = $reseller->backups->list($serviceId);
            $vars['ignite']['backups'] = is_array($backupList['backups'] ?? null)
                ? $backupList['backups']
                : (is_array($backupList['data'] ?? null) ? $backupList['data'] : []);
        } catch (\Throwable $e) {
            Support::log('ClientArea.backups', $params, ['serviceId' => $serviceId], $e->getMessage());
        }

        // DNS records only for the service's own domain. The reseller account
        // holds other customers' zones, so this must never be account-wide.
        $domainName = $server['domainName'] ?? null;
        if (is_string($domainName) && $domainName !== '') {
            $vars['ignite']['domainName'] = $domainName;
            try {
                foreach ($reseller->domains->owned() as $zone) {
                    $zoneDomain = (string) ($zone['domain'] ?? $zone['name'] ?? '');
                    if (strcasecmp($zoneDomain, $domainName) !== 0) {
                        continue;
                    }
                    $vars['ignite']['domainId'] = (string) ($zone['id'] ?? '');
                    $records = $reseller->domains->records($vars['ignite']['domainId']);
                    $vars['ignite']['dnsRecords'] = $records['data'] ?? $records['records'] ?? [];
                    break;
                }
            } catch (\Throwable $e) {
                Support::log('ClientArea.records', $params, ['domain' => $domainName], $e->getMessage());
            }
        }
    } catch (\Throwable $e) {
        $vars['ignite']['notice'] = \IgniteReseller\ErrorTranslator::message($e, 'de');
    }

    return [
        'tabOverviewReplacementTemplate' => 'templates/manage.tpl',
        'templateVariables' => $vars,
    ];
}

/**
 * Dispatcher for the client-area forms. Only per-service actions are allowed;
 * the handler re-checks that every referenced resource belongs to the mapped
 * service so a crafted POST cannot touch another customer's data.
 *
 * @param array<string, mixed> $params
 */
function ignite_Action(array $params): string
{
    $action = (string) ($_POST['ignite_action'] ?? '');
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return 'Der Service ist noch nicht bereitgestellt.';
    }

    try {
        $reseller = Support::reseller($params);
    } catch (\Throwable $e) {
        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }

    try {
        switch ($action) {
            case 'service.start':
            case 'service.stop':
            case 'service.restart':
            case 'service.reset':
                $reseller->services->power($serviceId, substr($action, 8));

                return 'success';

            case 'dns.record.create':
                if (!ignite_serviceOwnsDomain($reseller, $serviceId, (string) ($_POST['domain_id'] ?? ''))) {
                    return 'Diese Domain gehört nicht zu diesem Service.';
                }
                $reseller->domains->createRecord((string) ($_POST['domain_id'] ?? ''), [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'type' => strtoupper((string) ($_POST['type'] ?? 'A')),
                    'content' => (string) ($_POST['content'] ?? ''),
                    'ttl' => (int) ($_POST['ttl'] ?? 3600),
                ]);

                return 'success';

            case 'dns.record.delete':
                if (!ignite_serviceOwnsDomain($reseller, $serviceId, (string) ($_POST['domain_id'] ?? ''))) {
                    return 'Diese Domain gehört nicht zu diesem Service.';
                }
                $reseller->domains->deleteRecord((string) ($_POST['domain_id'] ?? ''), (string) ($_POST['record_id'] ?? ''));

                return 'success';

            case 'rdns.set':
                if (!ignite_serviceOwnsRdns($reseller, $serviceId, (string) ($_POST['entry_id'] ?? ''))) {
                    return 'Dieser IP-Eintrag gehört nicht zu diesem Service.';
                }
                $reseller->reverseDns->setReverse((string) ($_POST['entry_id'] ?? ''), (string) ($_POST['reverse_content'] ?? ''));

                return 'success';

            case 'rdns.delete':
                if (!ignite_serviceOwnsRdns($reseller, $serviceId, (string) ($_POST['entry_id'] ?? ''))) {
                    return 'Dieser IP-Eintrag gehört nicht zu diesem Service.';
                }
                $reseller->reverseDns->delete((string) ($_POST['entry_id'] ?? ''));

                return 'success';

            case 'mail.verify':
                $reseller->mail->verifyDns($serviceId, []);

                return 'success';

            case 'mail.apply':
                $reseller->mail->applyDns($serviceId, []);

                return 'success';

            case 'plesk.rotate':
                $reseller->plesk->rotateCredentials($serviceId);

                return 'success';

            case 'backup.create':
                $reseller->backups->create($serviceId, []);

                return 'success';

            case 'backup.restore':
                if (!ignite_serviceOwnsBackup($reseller, $serviceId, (string) ($_POST['backup_id'] ?? ''))) {
                    return 'Dieses Backup gehört nicht zu diesem Service.';
                }
                $reseller->backups->restore($serviceId, (string) ($_POST['backup_id'] ?? ''), []);

                return 'success';

            case 'backup.delete':
                if (!ignite_serviceOwnsBackup($reseller, $serviceId, (string) ($_POST['backup_id'] ?? ''))) {
                    return 'Dieses Backup gehört nicht zu diesem Service.';
                }
                $reseller->backups->delete($serviceId, (string) ($_POST['backup_id'] ?? ''));

                return 'success';

            default:
                return 'Unbekannte Aktion.';
        }
    } catch (\Throwable $e) {
        Support::log('Action.' . $action, $params, $_POST, $e->getMessage());

        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/** @param array<string, mixed> $params */
function ignite_serviceAction(array $params, string $action): string
{
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return 'Der Service ist noch nicht bereitgestellt.';
    }

    try {
        Support::reseller($params)->services->power($serviceId, $action);

        return 'success';
    } catch (\Throwable $e) {
        return \IgniteReseller\ErrorTranslator::message($e, 'de');
    }
}

/** @param array<string, mixed> $params */
function ignite_start(array $params): string
{
    return ignite_serviceAction($params, 'start');
}

/** @param array<string, mixed> $params */
function ignite_stop(array $params): string
{
    return ignite_serviceAction($params, 'stop');
}

/** @param array<string, mixed> $params */
function ignite_restart(array $params): string
{
    return ignite_serviceAction($params, 'restart');
}

/** @param array<string, mixed> $params */
function ignite_reset(array $params): string
{
    return ignite_serviceAction($params, 'reset');
}

function ignite_ClientAreaCustomButtonArray(): array
{
    return [
        'Starten' => 'start',
        'Stoppen' => 'stop',
        'Neu starten' => 'restart',
    ];
}

function ignite_ClientAreaAllowedFunctions(): array
{
    return [
        'Aktion ausführen' => 'Action',
    ];
}

function ignite_AdminCustomButtonArray(): array
{
    return [
        'Starten' => 'start',
        'Stoppen' => 'stop',
        'Neu starten' => 'restart',
    ];
}

/**
 * Admin service tab: remote id, status and a link into the reseller's own
 * Ignite account. Admin-only; never rendered in the client area.
 *
 * @param array<string, mixed> $params
 * @return array<string, string>
 */
function ignite_AdminServicesTabFields(array $params): array
{
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return ['Ignite' => 'Noch nicht bereitgestellt.'];
    }

    $fields = ['Ignite-Service-ID' => htmlspecialchars($serviceId)];

    try {
        $reseller = Support::reseller($params);

        $web = $reseller->links->webService($serviceId);
        if ($web !== null) {
            $fields['Im Ignite-Panel'] = '<a href="' . htmlspecialchars($web) . '" target="_blank" rel="noopener">Service im Ignite-Kundenkonto öffnen</a>';
        }

        $detail = $reseller->services->get($serviceId);
        $status = $detail['server']['status'] ?? null;
        if ($status !== null) {
            $fields['Status'] = htmlspecialchars((string) $status);
        }
    } catch (\Throwable $e) {
        $fields['Fehler'] = htmlspecialchars(\IgniteReseller\ErrorTranslator::message($e, 'de'));
    }

    return $fields;
}

/** @param array<string, mixed> $params */
function ignite_AdminServicesTabFieldsSave(array $params): void
{
    // No editable fields: the remote id is managed by the module.
}

function ignite_serviceOwnsDomain(Reseller $reseller, string $serviceId, string $domainId): bool
{
    if ($domainId === '') {
        return false;
    }

    try {
        $detail = $reseller->services->get($serviceId);
        $domainName = $detail['server']['domainName'] ?? null;
        if (!is_string($domainName) || $domainName === '') {
            return false;
        }

        foreach ($reseller->domains->owned() as $zone) {
            if ((string) ($zone['id'] ?? '') !== $domainId) {
                continue;
            }

            return strcasecmp((string) ($zone['domain'] ?? $zone['name'] ?? ''), $domainName) === 0;
        }
    } catch (\Throwable) {
        return false;
    }

    return false;
}

function ignite_serviceOwnsRdns(Reseller $reseller, string $serviceId, string $entryId): bool
{
    if ($entryId === '') {
        return false;
    }

    foreach ($reseller->reverseDns->forService($serviceId) as $entry) {
        if ((string) ($entry['id'] ?? '') === $entryId) {
            return true;
        }
    }

    return false;
}

function ignite_serviceOwnsBackup(Reseller $reseller, string $serviceId, string $backupId): bool
{
    if ($backupId === '') {
        return false;
    }

    $list = $reseller->backups->list($serviceId);
    foreach ($list['backups'] ?? $list['data'] ?? [] as $backup) {
        if (is_array($backup) && (string) ($backup['id'] ?? '') === $backupId) {
            return true;
        }
    }

    return false;
}

/**
 * Cron usage sync: mirror disk usage into tblhosting.
 *
 * @param array<string, mixed> $params
 */
function ignite_UsageUpdate(array $params): void
{
    $serviceId = Support::remoteServiceId($params);
    if ($serviceId === null) {
        return;
    }

    try {
        $reseller = Support::reseller($params);
        $disk = $reseller->backups->diskUsage($serviceId);

        $update = [];
        $used = $disk['usedGb'] ?? $disk['used'] ?? null;
        $limit = $disk['totalGb'] ?? $disk['limit'] ?? null;
        if ($used !== null) {
            $update['diskusage'] = (float) $used;
        }
        if ($limit !== null) {
            $update['disklimit'] = (float) $limit;
        }

        if ($update !== [] && class_exists('\WHMCS\Database\Capsule')) {
            \WHMCS\Database\Capsule::table('tblhosting')->where('id', (int) ($params['serviceid'] ?? 0))->update($update);
        }
    } catch (\Throwable $e) {
        Support::log('UsageUpdate', $params, [], $e->getMessage());
    }
}
