<?php

declare(strict_types=1);

namespace IgniteReseller;

/**
 * Tiny file-backed cache. Used for the product catalog, which changes rarely
 * and must not be fetched on every client-area page load.
 */
final class FileCache implements CacheStore
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignite-reseller-cache');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0770, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        /** @var array{expires: int, value: mixed}|null $data */
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('expires', $data)) {
            return null;
        }
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            @unlink($path);

            return null;
        }

        return $data['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $payload = json_encode([
            'expires' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
            'value' => $value,
        ]);
        if ($payload === false) {
            return;
        }
        @file_put_contents($this->path($key), $payload, LOCK_EX);
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    private function path(string $key): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'ignite-reseller-' . hash('sha256', $key) . '.json';
    }
}
