<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Plesk hosting service management: setup wizard, credential reveal/rotate,
 * domain sync and the destructive reset.
 */
final class PleskManager
{
    public function __construct(private readonly Api $api)
    {
    }

    private function base(string $serviceId): string
    {
        return '/services/' . rawurlencode($serviceId) . '/plesk';
    }

    /** @return array<string, mixed> */
    public function setup(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/setup');
    }

    /** @return array<string, mixed> */
    public function saveSetup(string $serviceId, array $body): array
    {
        return $this->api->post($this->base($serviceId) . '/setup', $body);
    }

    /** @return array<string, mixed> */
    public function credentials(string $serviceId, bool $reveal = false): array
    {
        return $this->api->get($this->base($serviceId) . '/credentials', $reveal ? ['reveal' => 1] : null);
    }

    /** @return array<string, mixed> */
    public function rotateCredentials(string $serviceId): array
    {
        return $this->api->post($this->base($serviceId) . '/credentials', []);
    }

    /** @return array<string, mixed> */
    public function domains(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/domains');
    }

    /** @return array<string, mixed> */
    public function refreshDomains(string $serviceId): array
    {
        return $this->api->post($this->base($serviceId) . '/domains', []);
    }

    /** @return array<string, mixed> */
    public function reset(string $serviceId, array $body): array
    {
        return $this->api->post($this->base($serviceId) . '/reset', $body);
    }
}
