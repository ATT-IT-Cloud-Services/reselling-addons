<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Mail service management (Mailcow). Domains, mailboxes and aliases are managed
 * per service; the DNS blueprint for a mail domain can be applied to an owned
 * zone and verified.
 */
final class MailManager
{
    public function __construct(private readonly Api $api)
    {
    }

    private function base(string $serviceId): string
    {
        return '/services/' . rawurlencode($serviceId) . '/mail';
    }

    /** @return array<string, mixed> */
    public function domains(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/domains');
    }

    /** @return array<string, mixed> */
    public function addDomain(string $serviceId, array $body): array
    {
        return $this->api->post($this->base($serviceId) . '/domains', $body);
    }

    /** @return array<string, mixed> */
    public function removeDomain(string $serviceId, array $body): array
    {
        return $this->api->delete($this->base($serviceId) . '/domains', null, $body);
    }

    /** @return array<string, mixed> */
    public function dns(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/domains/dns');
    }

    /** @return array<string, mixed> */
    public function applyDns(string $serviceId, array $body = []): array
    {
        return $this->api->post($this->base($serviceId) . '/domains/apply-dns', $body);
    }

    /** @return array<string, mixed> */
    public function verifyDns(string $serviceId, array $body = []): array
    {
        return $this->api->post($this->base($serviceId) . '/domains/verify-dns', $body);
    }

    /** @return array<string, mixed> */
    public function dnsZones(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/dns-zones');
    }

    /** @return array<string, mixed> */
    public function mailboxes(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/mailboxes');
    }

    /** @return array<string, mixed> */
    public function createMailbox(string $serviceId, array $body): array
    {
        return $this->api->post($this->base($serviceId) . '/mailboxes', $body);
    }

    /** @return array<string, mixed> */
    public function updateMailbox(string $serviceId, array $body): array
    {
        return $this->api->patch($this->base($serviceId) . '/mailboxes', $body);
    }

    /** @return array<string, mixed> */
    public function deleteMailbox(string $serviceId, array $body): array
    {
        return $this->api->delete($this->base($serviceId) . '/mailboxes', null, $body);
    }

    /** @return array<string, mixed> */
    public function aliases(string $serviceId): array
    {
        return $this->api->get($this->base($serviceId) . '/aliases');
    }

    /** @return array<string, mixed> */
    public function createAlias(string $serviceId, array $body): array
    {
        return $this->api->post($this->base($serviceId) . '/aliases', $body);
    }

    /** @return array<string, mixed> */
    public function deleteAlias(string $serviceId, array $body): array
    {
        return $this->api->delete($this->base($serviceId) . '/aliases', null, $body);
    }
}
