<?php

declare(strict_types=1);

namespace Paymenter\Extensions\Servers\Ignite\Lib;

use App\Models\Server;
use App\Models\Service;
use IgniteReseller\Config;
use IgniteReseller\ErrorTranslator;
use IgniteReseller\Reseller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service-scoped actions for the Ignite extension.
 *
 * The end customer manages their own service only: every action resolves the
 * service of the logged-in user, maps it to the remote Ignite service and
 * re-checks that the referenced resource belongs to it. Never account-wide.
 */
final class ServiceActions
{
    private static ?Reseller $reseller = null;

    public static function reseller(): ?Reseller
    {
        if (self::$reseller instanceof Reseller) {
            return self::$reseller;
        }

        $settings = self::settings();
        if ($settings === [] || empty($settings['api_base_url']) || empty($settings['api_key'])) {
            return null;
        }

        try {
            self::$reseller = new Reseller(Config::fromArray([
                'apiBaseUrl' => (string) $settings['api_base_url'],
                'apiKey' => (string) $settings['api_key'],
                'webAppBaseUrl' => $settings['web_app_base_url'] ?? null,
                'locale' => (string) ($settings['locale'] ?? 'de'),
                'sandbox' => !empty($settings['sandbox']),
                'catalogTtlSeconds' => (int) ($settings['catalog_ttl_seconds'] ?? 900),
            ]));
        } catch (\Throwable) {
            return null;
        }

        return self::$reseller;
    }

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        try {
            $server = Server::query()->where('extension', 'ignite')->first();
            if ($server !== null && isset($server->settings)) {
                $settings = [];
                foreach ($server->settings as $setting) {
                    $key = $setting->key ?? null;
                    if ($key !== null) {
                        $settings[(string) $key] = $setting->value ?? null;
                    }
                }
                if ($settings !== []) {
                    return $settings;
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        try {
            $settings = [];
            foreach (DB::table('settings')->get() as $row) {
                $key = $row->key ?? null;
                if ($key !== null && in_array($key, self::knownKeys(), true)) {
                    $settings[(string) $key] = $row->value ?? null;
                }
            }

            return $settings;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private static function knownKeys(): array
    {
        return ['api_base_url', 'api_key', 'web_app_base_url', 'locale', 'sandbox', 'catalog_ttl_seconds'];
    }

    public static function remoteServiceId(Service $service): ?string
    {
        try {
            $value = $service->properties()->where('key', 'ignite_service_id')->value('value');
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Update the reverse DNS (PTR) of one IP that belongs to the user's service.
     *
     * @return array{type: string, message: string}
     */
    public static function setRdns(Request $request): array
    {
        $reseller = self::reseller();
        if ($reseller === null) {
            return ['type' => 'error', 'message' => 'The Ignite extension is not configured.'];
        }

        $service = self::ownedService((string) $request->input('service_id', ''));
        if ($service === null) {
            return ['type' => 'error', 'message' => 'Service not found.'];
        }

        $remoteId = self::remoteServiceId($service);
        if ($remoteId === null) {
            return ['type' => 'error', 'message' => 'The service is not provisioned yet.'];
        }

        $entryId = (string) $request->input('entry_id', '');
        $allowed = false;
        foreach ($reseller->reverseDns->forService($remoteId) as $entry) {
            if ((string) ($entry['id'] ?? '') === $entryId) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return ['type' => 'error', 'message' => 'This IP entry does not belong to the service.'];
        }

        try {
            $reseller->reverseDns->setReverse($entryId, (string) $request->input('reverse_content', ''));
        } catch (\Throwable $e) {
            return ['type' => 'error', 'message' => ErrorTranslator::message($e, (string) $reseller->config->locale)];
        }

        return ['type' => 'success', 'message' => 'Reverse DNS updated.'];
    }

    /**
     * The managed DNS zone of the service's own domain plus its records.
     *
     * @return array{domainName: ?string, domainId: ?string, records: list<array<string, mixed>>}
     */
    public static function dnsOverview(string $remoteServiceId): array
    {
        $empty = ['domainName' => null, 'domainId' => null, 'records' => []];

        $reseller = self::reseller();
        if ($reseller === null) {
            return $empty;
        }

        try {
            $detail = $reseller->services->get($remoteServiceId);
        } catch (\Throwable) {
            return $empty;
        }

        $domainName = $detail['server']['domainName'] ?? null;
        if (!is_string($domainName) || $domainName === '') {
            return $empty;
        }

        foreach ($reseller->domains->owned() as $zone) {
            if (strcasecmp((string) ($zone['domain'] ?? $zone['name'] ?? ''), $domainName) !== 0) {
                continue;
            }

            $domainId = (string) ($zone['id'] ?? '');
            $records = $reseller->domains->records($domainId);

            return [
                'domainName' => $domainName,
                'domainId' => $domainId,
                'records' => self::recordList($records),
            ];
        }

        return ['domainName' => $domainName, 'domainId' => null, 'records' => []];
    }

    /** @return array{type: string, message: string} */
    public static function createDnsRecord(Request $request): array
    {
        return self::dnsMutation($request, static function (Reseller $reseller, string $domainId) use ($request): void {
            $reseller->domains->createRecord($domainId, [
                'name' => (string) $request->input('name', ''),
                'type' => strtoupper((string) $request->input('type', 'A')),
                'content' => (string) $request->input('content', ''),
                'ttl' => (int) $request->input('ttl', 3600),
            ]);
        }, 'DNS record created.');
    }

    /** @return array{type: string, message: string} */
    public static function deleteDnsRecord(Request $request): array
    {
        return self::dnsMutation($request, static function (Reseller $reseller, string $domainId) use ($request): void {
            $recordId = (string) $request->input('record_id', '');
            foreach (self::recordList($reseller->domains->records($domainId)) as $record) {
                if ((string) ($record['id'] ?? '') === $recordId) {
                    $reseller->domains->deleteRecord($domainId, $recordId);

                    return;
                }
            }

            throw new \RuntimeException('This record does not belong to the service domain.');
        }, 'DNS record deleted.');
    }

    /**
     * @param callable(Reseller, string): void $mutation
     * @return array{type: string, message: string}
     */
    private static function dnsMutation(Request $request, callable $mutation, string $successMessage): array
    {
        $reseller = self::reseller();
        if ($reseller === null) {
            return ['type' => 'error', 'message' => 'The Ignite extension is not configured.'];
        }

        $service = self::ownedService((string) $request->input('service_id', ''));
        if ($service === null) {
            return ['type' => 'error', 'message' => 'Service not found.'];
        }

        $remoteId = self::remoteServiceId($service);
        if ($remoteId === null) {
            return ['type' => 'error', 'message' => 'The service is not provisioned yet.'];
        }

        $overview = self::dnsOverview($remoteId);
        if (empty($overview['domainId'])) {
            return ['type' => 'error', 'message' => 'No managed DNS zone for this service.'];
        }

        try {
            $mutation($reseller, (string) $overview['domainId']);
        } catch (\Throwable $e) {
            $message = $e instanceof \Ignite\ApiClient\ApiException
                ? ErrorTranslator::message($e, (string) $reseller->config->locale)
                : $e->getMessage();

            return ['type' => 'error', 'message' => $message];
        }

        return ['type' => 'success', 'message' => $successMessage];
    }

    /** @param array<string, mixed> $response @return list<array<string, mixed>> */
    private static function recordList(array $response): array
    {
        if (is_array($response['data'] ?? null)) {
            return array_values(array_filter($response['data'], 'is_array'));
        }
        if (is_array($response['records'] ?? null)) {
            return array_values(array_filter($response['records'], 'is_array'));
        }

        return [];
    }

    /** @return array{type: string, message: string} */
    public static function restoreBackup(Request $request): array
    {
        return self::backupMutation($request, static function (Reseller $reseller, string $remoteId, string $backupId): void {
            $reseller->backups->restore($remoteId, $backupId, []);
        }, 'Backup restore started.');
    }

    /** @return array{type: string, message: string} */
    public static function deleteBackup(Request $request): array
    {
        return self::backupMutation($request, static function (Reseller $reseller, string $remoteId, string $backupId): void {
            $reseller->backups->delete($remoteId, $backupId);
        }, 'Backup deleted.');
    }

    /**
     * @param callable(Reseller, string, string): void $mutation
     * @return array{type: string, message: string}
     */
    private static function backupMutation(Request $request, callable $mutation, string $successMessage): array
    {
        $reseller = self::reseller();
        if ($reseller === null) {
            return ['type' => 'error', 'message' => 'The Ignite extension is not configured.'];
        }

        $service = self::ownedService((string) $request->input('service_id', ''));
        if ($service === null) {
            return ['type' => 'error', 'message' => 'Service not found.'];
        }

        $remoteId = self::remoteServiceId($service);
        if ($remoteId === null) {
            return ['type' => 'error', 'message' => 'The service is not provisioned yet.'];
        }

        $backupId = (string) $request->input('backup_id', '');
        $exists = false;
        try {
            $list = $reseller->backups->list($remoteId);
        } catch (\Throwable) {
            $list = [];
        }
        foreach (($list['backups'] ?? $list['data'] ?? []) as $backup) {
            if (is_array($backup) && (string) ($backup['id'] ?? '') === $backupId) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            return ['type' => 'error', 'message' => 'This backup does not belong to the service.'];
        }

        try {
            $mutation($reseller, $remoteId, $backupId);
        } catch (\Throwable $e) {
            $message = $e instanceof \Ignite\ApiClient\ApiException
                ? ErrorTranslator::message($e, (string) $reseller->config->locale)
                : $e->getMessage();

            return ['type' => 'error', 'message' => $message];
        }

        return ['type' => 'success', 'message' => $successMessage];
    }

    private static function ownedService(string $serviceId): ?Service
    {
        if ($serviceId === '') {
            return null;
        }

        try {
            return Service::query()
                ->where('id', $serviceId)
                ->where('user_id', (string) Auth::id())
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
