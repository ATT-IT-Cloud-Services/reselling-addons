<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Service backups: list, create, restore, delete and download.
 */
final class BackupManager
{
    public function __construct(private readonly Api $api)
    {
    }

    /** @return array<string, mixed> */
    public function list(string $serviceId): array
    {
        return $this->api->get('/services/' . rawurlencode($serviceId) . '/backups');
    }

    /** @return array<string, mixed> */
    public function create(string $serviceId, array $body = []): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/backups', $body);
    }

    /** @return array<string, mixed> */
    public function restore(string $serviceId, string $backupId, array $body = []): array
    {
        return $this->api->post('/services/' . rawurlencode($serviceId) . '/backups/' . rawurlencode($backupId), $body);
    }

    /** @return array<string, mixed> */
    public function delete(string $serviceId, string $backupId): array
    {
        return $this->api->delete('/services/' . rawurlencode($serviceId) . '/backups/' . rawurlencode($backupId));
    }

    /** @return array<string, mixed> */
    public function diskUsage(string $serviceId): array
    {
        return $this->api->get('/services/' . rawurlencode($serviceId) . '/disk-usage');
    }

    /** @return array<string, mixed> */
    public function metrics(string $serviceId): array
    {
        return $this->api->get('/services/' . rawurlencode($serviceId) . '/metrics');
    }
}
