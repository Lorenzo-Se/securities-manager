<?php

namespace App\Services\Keycloak;

use Firebase\JWT\CachedKeySet;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Cache;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class JwksFetcher
{
    public function getKeySet(): CachedKeySet
    {
        $cacheTtl = config('keycloak.jwks_cache_ttl');
        $httpFactory = new HttpFactory;

        return new CachedKeySet(
            config('keycloak.jwks_url'),
            new Client,
            $httpFactory,
            new LaravelCacheItemPool($cacheTtl),
            $cacheTtl,
        );
    }
}

class LaravelCacheItemPool implements CacheItemPoolInterface
{
    public function __construct(private readonly int $ttl) {}

    public function getItem(string $key): CacheItemInterface
    {
        $value = Cache::get($key);

        return new LaravelCacheItem($key, $value, $value !== null);
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return Cache::has($key);
    }

    public function clear(): bool
    {
        return true;
    }

    public function deleteItem(string $key): bool
    {
        return Cache::forget($key);
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        /** @var LaravelCacheItem $item */
        return Cache::put($item->getKey(), $item->get(), $this->ttl);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

class LaravelCacheItem implements CacheItemInterface
{
    private mixed $value;

    private bool $isHit;

    public function __construct(
        private readonly string $key,
        mixed $value,
        bool $isHit,
    ) {
        $this->value = $value;
        $this->isHit = $isHit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}
