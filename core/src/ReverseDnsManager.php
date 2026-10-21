<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Reverse DNS (PTR) for service IPs.
 *
 * rDNS is not part of the domain zone management: the platform keeps A/AAAA and
 * their PTR records in the IP manager. Forward records there are read-only; only
 * the reverse content (PTR) is editable, and IPv6 entries can be added/removed
 * inside the service's assigned subnet.
 */
final class ReverseDnsManager
{
    public function __construct(private readonly Api $api)
    {
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        return $this->api->get('/ip-manager');
    }

    /**
     * Entries belonging to one service.
     *
     * @return list<array<string, mixed>>
     */
    public function forService(string $serviceId): array
    {
        $response = $this->list();
        $entries = $response['data'] ?? [];
        if (!is_array($entries)) {
            return [];
        }

        return array_values(array_filter($entries, static fn ($entry): bool => is_array($entry)
            && (string) ($entry['serviceId'] ?? '') === $serviceId));
    }

    /** @return array<string, mixed> */
    public function addIpv6(string $serviceId, string $ipv6Address): array
    {
        return $this->api->post('/ip-manager', [
            'serviceId' => $serviceId,
            'ipv6Address' => $ipv6Address,
        ]);
    }

    /** @return array<string, mixed> */
    public function setReverse(string $entryId, string $reverseContent): array
    {
        return $this->api->patch('/ip-manager/' . rawurlencode($entryId), ['reverseContent' => $reverseContent]);
    }

    /** @return array<string, mixed> */
    public function delete(string $entryId): array
    {
        return $this->api->delete('/ip-manager/' . rawurlencode($entryId));
    }
}
