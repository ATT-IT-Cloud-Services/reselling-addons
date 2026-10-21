<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Domain management, split by the two concepts the API exposes:
 *
 *  - Owned domains: DNS zones managed by the platform's PowerDNS. These are the
 *    only domains whose records the reseller can read/write/import/export.
 *  - Registrar registrations: domains bought through the shop; actions such as
 *    auth code, transfer, nameservers or auto-renew are exposed per registration.
 *
 * External domains (registered elsewhere) are deliberately out of scope: the
 * integration never claims a foreign zone on the platform nameservers.
 */
final class DomainManager
{
    public function __construct(private readonly Api $api)
    {
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        return $this->api->get('/domains');
    }

    /** @return list<array<string, mixed>> */
    public function owned(): array
    {
        $response = $this->list();
        $domains = $response['data'] ?? $response['domains'] ?? [];
        if (!is_array($domains)) {
            return [];
        }

        return array_values(array_filter($domains, 'is_array'));
    }

    /** @return array<string, mixed> */
    public function add(string $domain): array
    {
        return $this->api->post('/domains/add', ['domain' => $domain]);
    }

    /** @return array<string, mixed> */
    public function delete(string $domainId): array
    {
        return $this->api->delete('/domains/' . rawurlencode($domainId) . '/delete');
    }

    /** @return array<string, mixed> */
    public function records(string $domainId): array
    {
        return $this->api->get('/domains/' . rawurlencode($domainId) . '/records');
    }

    /** @return array<string, mixed> */
    public function createRecord(string $domainId, array $record): array
    {
        return $this->api->post('/domains/' . rawurlencode($domainId) . '/records', $record);
    }

    /** @return array<string, mixed> */
    public function updateRecord(string $domainId, string $recordId, array $record): array
    {
        return $this->api->patch('/domains/' . rawurlencode($domainId) . '/records/' . rawurlencode($recordId), $record);
    }

    /** @return array<string, mixed> */
    public function deleteRecord(string $domainId, string $recordId): array
    {
        return $this->api->delete('/domains/' . rawurlencode($domainId) . '/records/' . rawurlencode($recordId));
    }

    /** @return array<string, mixed> */
    public function importText(string $domainId, string $text, bool $dryRun = true): array
    {
        return $this->api->post('/domains/' . rawurlencode($domainId) . '/import/text', [
            'text' => $text,
            'dryRun' => $dryRun,
        ]);
    }

    /** @return array<string, mixed> */
    public function nameservers(): array
    {
        return $this->api->get('/domains/nameservers');
    }

    /** @return array<string, mixed> */
    public function registrations(): array
    {
        return $this->api->get('/domains/registrations');
    }

    /** @return array<string, mixed> */
    public function registration(string $id): array
    {
        return $this->api->get('/domains/registrations/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function registrationByService(string $serviceId): array
    {
        return $this->api->get('/domains/registrations/by-service/' . rawurlencode($serviceId));
    }

    /** @return array<string, mixed> */
    public function registrationAction(string $id, array $body): array
    {
        return $this->api->post('/domains/registrations/' . rawurlencode($id) . '/actions', $body);
    }

    /** @return array<string, mixed> */
    public function quote(string $domain, int $years = 1, ?string $taxCountryCode = null): array
    {
        return $this->api->post('/domains/quote', array_filter([
            'domain' => $domain,
            'years' => $years,
            'taxCountryCode' => $taxCountryCode,
        ], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function transferQuote(string $domain, ?string $taxCountryCode = null): array
    {
        return $this->api->post('/domains/transfer-quote', array_filter([
            'domain' => $domain,
            'taxCountryCode' => $taxCountryCode,
        ], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function suggest(string $label, int $limit = 12): array
    {
        return $this->api->post('/domains/suggest', ['label' => $label, 'limit' => $limit]);
    }

    /** @return array<string, mixed> */
    public function whois(string $domain): array
    {
        return $this->api->get('/domains/whois', ['domain' => $domain]);
    }

    /** @return array<string, mixed> */
    public function order(array $body): array
    {
        return $this->api->post('/domains/order', $body);
    }
}
