<?php

namespace App\Services\Crypto;

use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CoinGeckoClient
{
    /**
     * @return list<array{id: string, symbol: string, name: string}>
     */
    public function getCoinsList(): array
    {
        $ttl = config('crypto.coingecko.cache.coins_list_ttl');

        return Cache::remember('coingecko.coins_list', $ttl, function (): array {
            $response = $this->request()->get('/coins/list');

            if (! $response->successful()) {
                throw new RuntimeException('Failed to fetch coins list from CoinGecko.');
            }

            /** @var list<array{id: string, symbol: string, name: string}> $coins */
            $coins = $response->json();

            return $coins;
        });
    }

    /**
     * @return array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}|null
     */
    /**
     * @return array{id: string, platforms: array<string, string>}|null
     */
    public function getCoinDetail(string $id): ?array
    {
        $cacheKey = 'coingecko.coin_detail.'.md5($id);
        $ttl = config('crypto.coingecko.cache.coin_detail_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($id): ?array {
            $response = $this->request()->get("/coins/{$id}", [
                'localization' => 'false',
                'tickers' => 'false',
                'market_data' => 'false',
                'community_data' => 'false',
                'developer_data' => 'false',
            ]);

            if (! $response->successful()) {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = $response->json();

            /** @var array<string, string> $platforms */
            $platforms = $data['platforms'] ?? [];

            return [
                'id' => (string) ($data['id'] ?? $id),
                'platforms' => array_filter(
                    $platforms,
                    static fn (mixed $address): bool => is_string($address) && $address !== '',
                ),
            ];
        });
    }

    public function findCoinById(string $id): ?array
    {
        foreach ($this->getTopCoinsByMarketCap() as $coin) {
            if ($coin['id'] === $id) {
                return $coin;
            }
        }

        foreach ($this->getCoinsList() as $coin) {
            if ($coin['id'] === $id) {
                return $this->normalizeListCoin($coin);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $ids
     * @param  list<string>|string|null  $vsCurrencies
     * @return array<string, array<string, float>>
     */
    public function getPrices(array $ids, array|string|null $vsCurrencies = null): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        $currencies = $this->normalizeVsCurrencies($vsCurrencies);
        $cacheKey = 'coingecko.prices.'.md5(implode(',', $ids).'.'.implode(',', $currencies));
        $ttl = config('crypto.coingecko.cache.prices_ttl');

        return $this->rememberWithStaleFallback($cacheKey, $ttl, function () use ($ids, $currencies): array {
            $response = $this->request()->get('/simple/price', [
                'ids' => implode(',', $ids),
                'vs_currencies' => implode(',', $currencies),
            ]);

            if ($response->status() === 429) {
                throw new RuntimeException('CoinGecko Rate-Limit erreicht (429).');
            }

            if (! $response->successful()) {
                throw new RuntimeException(
                    'CoinGecko-Preise nicht verfügbar (HTTP '.$response->status().').',
                );
            }

            /** @var array<string, array<string, float>> $prices */
            $prices = $response->json();

            return $prices;
        });
    }

    /**
     * @return list<array{timestamp: int, price: float}>
     */
    public function getMarketChart(
        string $id,
        int $days,
        string $vsCurrency = 'usd',
    ): array {
        $vsCurrency = strtolower($vsCurrency);
        $daysParam = (string) $days;
        $cacheKey = 'coingecko.market_chart.'.md5($id.'.'.$daysParam.'.'.$vsCurrency);
        $ttl = $this->marketChartCacheTtl($days);

        return $this->rememberWithStaleFallback($cacheKey, $ttl, function () use ($id, $days, $daysParam, $vsCurrency): array {
            $query = [
                'vs_currency' => $vsCurrency,
                'days' => $daysParam,
            ];

            if ($days > 90) {
                $query['interval'] = 'daily';
            }

            $response = $this->request()->get("/coins/{$id}/market_chart", $query);

            if ($response->status() === 429) {
                throw new RuntimeException('CoinGecko Rate-Limit erreicht (429).');
            }

            if (! $response->successful()) {
                throw new RuntimeException(
                    'CoinGecko-Marktchart nicht verfügbar (HTTP '.$response->status().').',
                );
            }

            /** @var list<array{0: int|float, 1: float|int}>|null $prices */
            $prices = $response->json('prices');

            if (! is_array($prices)) {
                throw new RuntimeException('Marktchart-Preise fehlen in der CoinGecko-Antwort.');
            }

            return array_values(array_map(
                static fn (array $point): array => [
                    'timestamp' => (int) $point[0],
                    'price' => (float) $point[1],
                ],
                $prices,
            ));
        });
    }

    public function getHistoricalPrice(
        string $id,
        DateTimeInterface $date,
        string $vsCurrency = 'usd',
    ): float {
        $vsCurrency = strtolower($vsCurrency);

        if ($this->shouldUseSpotPrice($date)) {
            return $this->getSpotPrice($id, $vsCurrency);
        }

        $dateKey = $date->format('d-m-Y');
        $cacheKey = 'coingecko.history.'.md5($id.'.'.$dateKey.'.'.$vsCurrency);
        $ttl = config('crypto.coingecko.cache.prices_ttl');

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($id, $dateKey, $vsCurrency): float {
                return $this->fetchHistoricalPriceFromApi($id, $dateKey, $vsCurrency);
            });
        } catch (RuntimeException $exception) {
            Cache::forget($cacheKey);

            Log::warning('CoinGecko historical price unavailable, using spot price fallback', [
                'coin_id' => $id,
                'date' => $dateKey,
                'error' => $exception->getMessage(),
            ]);

            return $this->getSpotPrice($id, $vsCurrency);
        }
    }

    private function shouldUseSpotPrice(DateTimeInterface $date): bool
    {
        return $date->format('Y-m-d') >= gmdate('Y-m-d');
    }

    private function fetchHistoricalPriceFromApi(string $id, string $dateKey, string $vsCurrency): float
    {
        $response = $this->request()->get("/coins/{$id}/history", [
            'date' => $dateKey,
            'localization' => 'false',
        ]);

        if ($response->status() === 429) {
            throw new RuntimeException('CoinGecko Rate-Limit erreicht (429).');
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Historischer CoinGecko-Preis nicht verfügbar (HTTP '.$response->status().').',
            );
        }

        $price = $response->json("market_data.current_price.{$vsCurrency}");

        if (! is_numeric($price)) {
            throw new RuntimeException('Historischer Preis fehlt in der CoinGecko-Antwort.');
        }

        return (float) $price;
    }

    private function getSpotPrice(string $id, string $vsCurrency): float
    {
        $prices = $this->getPrices([$id], $vsCurrency);
        $price = $prices[$id][$vsCurrency] ?? null;

        if (! is_numeric($price)) {
            throw new RuntimeException('Aktueller Preis von CoinGecko nicht verfügbar.');
        }

        return (float) $price;
    }

    /**
     * @param  list<string>|string|null  $vsCurrencies
     * @return list<string>
     */
    private function normalizeVsCurrencies(array|string|null $vsCurrencies): array
    {
        if ($vsCurrencies === null) {
            return [strtolower((string) config('crypto.coingecko.default_currency'))];
        }

        if (is_string($vsCurrencies)) {
            return [strtolower($vsCurrencies)];
        }

        $normalized = array_values(array_unique(array_map(
            static fn (string $currency): string => strtolower($currency),
            array_filter($vsCurrencies),
        )));

        return $normalized === [] ? ['usd'] : $normalized;
    }

    /**
     * @return list<array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}>
     */
    public function searchCoins(?string $search, int $limit = 50): array
    {
        if ($search === null || trim($search) === '') {
            return array_slice($this->getTopCoinsByMarketCap(), 0, $limit);
        }

        return $this->searchByNameOrSymbol(trim($search), $limit);
    }

    /**
     * @return list<array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}>
     */
    private function getTopCoinsByMarketCap(): array
    {
        $ttl = config('crypto.coingecko.cache.markets_ttl');

        return Cache::remember('coingecko.top_coins', $ttl, function (): array {
            $response = $this->request()->get('/coins/markets', [
                'vs_currency' => config('crypto.coingecko.default_currency'),
                'order' => 'market_cap_desc',
                'per_page' => 250,
                'page' => 1,
                'sparkline' => 'false',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Failed to fetch top coins from CoinGecko.');
            }

            /** @var list<array<string, mixed>> $coins */
            $coins = $response->json();

            return array_map(
                fn (array $coin): array => $this->normalizeMarketCoin($coin),
                $coins,
            );
        });
    }

    /**
     * @return list<array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}>
     */
    private function searchByNameOrSymbol(string $search, int $limit): array
    {
        $cacheKey = 'coingecko.search.'.md5(mb_strtolower($search));
        $ttl = config('crypto.coingecko.cache.search_ttl');

        return Cache::remember($cacheKey, $ttl, function () use ($search, $limit): array {
            $response = $this->request()->get('/search', [
                'query' => $search,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Failed to search coins on CoinGecko.');
            }

            /** @var list<array<string, mixed>> $coins */
            $coins = $response->json('coins') ?? [];
            $needle = mb_strtolower($search);

            $matches = array_values(array_filter(
                $coins,
                fn (array $coin): bool => str_contains(mb_strtolower((string) ($coin['name'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($coin['symbol'] ?? '')), $needle),
            ));

            usort(
                $matches,
                fn (array $left, array $right): int => ($left['market_cap_rank'] ?? PHP_INT_MAX)
                    <=> ($right['market_cap_rank'] ?? PHP_INT_MAX),
            );

            return array_slice(
                array_map(
                    fn (array $coin): array => $this->normalizeSearchCoin($coin),
                    $matches,
                ),
                0,
                $limit,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $coin
     * @return array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}
     */
    private function normalizeMarketCoin(array $coin): array
    {
        return [
            'id' => (string) $coin['id'],
            'symbol' => (string) $coin['symbol'],
            'name' => (string) $coin['name'],
            'image' => (string) ($coin['image'] ?? ''),
            'market_cap_rank' => isset($coin['market_cap_rank']) ? (int) $coin['market_cap_rank'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $coin
     * @return array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}
     */
    private function normalizeSearchCoin(array $coin): array
    {
        return [
            'id' => (string) $coin['id'],
            'symbol' => (string) $coin['symbol'],
            'name' => (string) $coin['name'],
            'image' => (string) ($coin['thumb'] ?? $coin['large'] ?? ''),
            'market_cap_rank' => isset($coin['market_cap_rank']) ? (int) $coin['market_cap_rank'] : null,
        ];
    }

    /**
     * @param  array{id: string, symbol: string, name: string}  $coin
     * @return array{id: string, symbol: string, name: string, image: string, market_cap_rank: int|null}
     */
    private function normalizeListCoin(array $coin): array
    {
        return [
            'id' => $coin['id'],
            'symbol' => $coin['symbol'],
            'name' => $coin['name'],
            'image' => '',
            'market_cap_rank' => null,
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function rememberWithStaleFallback(string $cacheKey, int $ttl, callable $callback): mixed
    {
        $staleKey = $cacheKey.'.stale';
        $staleTtl = (int) config('crypto.coingecko.cache.stale_ttl');

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
                Log::warning('CoinGecko unavailable, serving stale cache', [
                    'cache_key' => $cacheKey,
                    'error' => $exception->getMessage(),
                ]);

                return $stale;
            }

            throw $exception;
        }
    }

    private function marketChartCacheTtl(int $days): int
    {
        if ($days > 90) {
            return (int) config('crypto.coingecko.cache.market_chart_long_ttl');
        }

        return (int) config('crypto.coingecko.cache.market_chart_ttl');
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('crypto.coingecko.base_url'), '/');

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout(15);

        $apiKey = config('crypto.coingecko.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $header = str_contains($baseUrl, 'pro-api.coingecko.com')
                ? 'x-cg-pro-api-key'
                : 'x-cg-demo-api-key';

            $request = $request->withHeaders([
                $header => $apiKey,
            ]);
        }

        return $request;
    }
}
