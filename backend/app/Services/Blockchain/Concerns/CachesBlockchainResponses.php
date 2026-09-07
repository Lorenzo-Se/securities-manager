<?php

namespace App\Services\Blockchain\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

trait CachesBlockchainResponses
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function remember(string $cacheKey, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($cacheKey, $ttl, $callback);
        } catch (RuntimeException $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function rememberWithStale(string $cacheKey, int $ttl, callable $callback, int $staleTtl = 604800): mixed
    {
        $staleKey = $cacheKey.'.stale';

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($callback, $staleKey, $staleTtl): mixed {
                $value = $callback();
                Cache::put($staleKey, $value, $staleTtl);

                return $value;
            });
        } catch (RuntimeException $exception) {
            Cache::forget($cacheKey);

            $stale = Cache::get($staleKey);

            if ($stale !== null) {
                Log::warning('Blockchain API unavailable, serving stale cache', [
                    'cache_key' => $cacheKey,
                    'error' => $exception->getMessage(),
                ]);

                return $stale;
            }

            throw $exception;
        }
    }
}
