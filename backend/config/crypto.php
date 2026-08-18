<?php

return [
    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'api_key' => env('COINGECKO_API_KEY'),
        'default_currency' => env('COINGECKO_DEFAULT_CURRENCY', 'eur'),
        'cache' => [
            'coins_list_ttl' => (int) env('COINGECKO_COINS_LIST_CACHE_TTL', 86400),
            'markets_ttl' => (int) env('COINGECKO_MARKETS_CACHE_TTL', 900),
            'search_ttl' => (int) env('COINGECKO_SEARCH_CACHE_TTL', 300),
            'prices_ttl' => (int) env('COINGECKO_PRICES_CACHE_TTL', 300),
        ],
    ],
];
