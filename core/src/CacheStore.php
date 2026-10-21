<?php

declare(strict_types=1);

namespace IgniteReseller;

/** Minimal cache contract so the core stays free of platform cache types. */
interface CacheStore
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds): void;

    public function forget(string $key): void;
}
