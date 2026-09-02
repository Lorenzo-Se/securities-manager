<?php

return [
    'supported_currencies' => [
        'eur',
        'usd',
        'chf',
        'gbp',
        'jpy',
        'cad',
        'aud',
        'sek',
        'nok',
        'dkk',
        'pln',
        'czk',
        'huf',
        'brl',
        'inr',
    ],

    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'api_key' => env('COINGECKO_API_KEY'),
        'default_currency' => env('COINGECKO_DEFAULT_CURRENCY', 'usd'),
        'cache' => [
            'coins_list_ttl' => (int) env('COINGECKO_COINS_LIST_CACHE_TTL', 86400),
            'markets_ttl' => (int) env('COINGECKO_MARKETS_CACHE_TTL', 900),
            'search_ttl' => (int) env('COINGECKO_SEARCH_CACHE_TTL', 300),
            'prices_ttl' => (int) env('COINGECKO_PRICES_CACHE_TTL', 300),
            'coin_detail_ttl' => (int) env('COINGECKO_COIN_DETAIL_CACHE_TTL', 86400),
        ],
    ],

    'blockchair' => [
        'base_url' => env('BLOCKCHAIR_BASE_URL', 'https://api.blockchair.com'),
        'api_key' => env('BLOCKCHAIR_API_KEY'),
        'cache_ttl' => (int) env('BLOCKCHAIR_CACHE_TTL', 300),
    ],

    'mempool' => [
        'base_url' => env('MEMPOOL_BASE_URL', 'https://mempool.space/api'),
        'cache_ttl' => (int) env('MEMPOOL_CACHE_TTL', 300),
    ],

    /*
   * Blockchair-supported chains mapped to CoinGecko.
   * coingecko_platform: key in CoinGecko coin detail "platforms" for token lookup.
   */
    'chains' => [
        'bitcoin' => [
            'blockchair_slug' => 'bitcoin',
            'model' => 'utxo',
            'native_coingecko_id' => 'bitcoin',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'bitcoin-cash' => [
            'blockchair_slug' => 'bitcoin-cash',
            'model' => 'utxo',
            'native_coingecko_id' => 'bitcoin-cash',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'litecoin' => [
            'blockchair_slug' => 'litecoin',
            'model' => 'utxo',
            'native_coingecko_id' => 'litecoin',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'dogecoin' => [
            'blockchair_slug' => 'dogecoin',
            'model' => 'utxo',
            'native_coingecko_id' => 'dogecoin',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'dash' => [
            'blockchair_slug' => 'dash',
            'model' => 'utxo',
            'native_coingecko_id' => 'dash',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'zcash' => [
            'blockchair_slug' => 'zcash',
            'model' => 'utxo',
            'native_coingecko_id' => 'zcash',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'ethereum' => [
            'blockchair_slug' => 'ethereum',
            'model' => 'account',
            'native_coingecko_id' => 'ethereum',
            'decimals' => 18,
            'coingecko_platform' => 'ethereum',
        ],
        'ripple' => [
            'blockchair_slug' => 'ripple',
            'model' => 'account',
            'native_coingecko_id' => 'ripple',
            'decimals' => 6,
            'coingecko_platform' => '',
        ],
        'stellar' => [
            'blockchair_slug' => 'stellar',
            'model' => 'account',
            'native_coingecko_id' => 'stellar',
            'decimals' => 7,
            'coingecko_platform' => 'stellar',
        ],
        'cardano' => [
            'blockchair_slug' => 'cardano',
            'model' => 'account',
            'native_coingecko_id' => 'cardano',
            'decimals' => 6,
            'coingecko_platform' => 'cardano',
        ],
    ],
];
