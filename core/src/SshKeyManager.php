<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Account-level SSH keys. Keys are stored on the platform account and attached
 * to services at provisioning/reinstall time via the service `sshKeys` option.
 */
final class SshKeyManager
{
    public function __construct(private readonly Api $api)
    {
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        return $this->api->get('/ssh-keys');
    }

    /** @return list<array<string, mixed>> */
    public function keys(): array
    {
        $response = $this->list();
        $keys = $response['sshKeys'] ?? [];
        if (!is_array($keys)) {
            return [];
        }

        return array_values(array_filter($keys, 'is_array'));
    }

    /** @return array<string, mixed> */
    public function create(string $name, string $publicKey): array
    {
        return $this->api->post('/ssh-keys', ['name' => $name, 'publicKey' => $publicKey]);
    }

    /**
     * Generate a key pair. The private key is returned once and never stored by
     * the platform.
     *
     * @return array<string, mixed>
     */
    public function generate(string $name, string $keyType = 'ed25519', ?string $passphrase = null): array
    {
        return $this->api->post('/ssh-keys/generate', array_filter([
            'name' => $name,
            'keyType' => $keyType,
            'passphrase' => $passphrase,
        ], static fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function rename(string $id, string $name): array
    {
        return $this->api->patch('/ssh-keys/' . rawurlencode($id), ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function delete(string $id): array
    {
        return $this->api->delete('/ssh-keys/' . rawurlencode($id));
    }

    /**
     * Attach keys to a service by replacing the service's key list.
     *
     * @param list<string> $keyIds
     * @return array<string, mixed>
     */
    public function assignToService(string $serviceId, array $keyIds): array
    {
        return $this->api->patch('/services/' . rawurlencode($serviceId) . '/settings', ['sshKeyIds' => array_values($keyIds)]);
    }
}
