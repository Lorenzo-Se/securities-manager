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
            'stale_ttl' => (int) env('COINGECKO_STALE_CACHE_TTL', 604800),
        ],
    ],

    'platform_priority' => [
        'ethereum',
        'binance-smart-chain',
        'tron',
        'solana',
    ],

    'mempool' => [
        'base_url' => env('MEMPOOL_BASE_URL', 'https://mempool.space/api'),
        'cache_ttl' => (int) env('MEMPOOL_CACHE_TTL', 300),
        'inactive_address_cache_ttl' => (int) env('MEMPOOL_INACTIVE_ADDRESS_CACHE_TTL', 86400),
        'stale_cache_ttl' => (int) env('MEMPOOL_STALE_CACHE_TTL', 604800),
        'request_delay_ms' => (int) env('MEMPOOL_REQUEST_DELAY_MS', 200),
        'retry_backoff_ms' => (int) env('MEMPOOL_RETRY_BACKOFF_MS', 1000),
        'max_retries' => (int) env('MEMPOOL_MAX_RETRIES', 3),
    ],

    'bitcoin_hd' => [
        'gap_limit' => (int) env('BITCOIN_HD_GAP_LIMIT', 20),
        'max_scan_index' => (int) env('BITCOIN_HD_MAX_SCAN_INDEX', 100),
    ],

    'blockchain_info' => [
        'base_url' => env('BLOCKCHAIN_INFO_BASE_URL', 'https://blockchain.info'),
        'cache_ttl' => (int) env('BLOCKCHAIN_INFO_CACHE_TTL', 300),
    ],

    'etherscan' => [
        'base_url' => env('ETHERSCAN_BASE_URL', 'https://api.etherscan.io/v2/api'),
        'api_key' => env('ETHERSCAN_API_KEY'),
        'chain_id' => (int) env('ETHERSCAN_CHAIN_ID', 1),
        'cache_ttl' => (int) env('ETHERSCAN_CACHE_TTL', 300),
    ],

    'bscscan' => [
        'base_url' => env('BSCSCAN_BASE_URL', 'https://api.bscscan.com/api'),
        'api_key' => env('BSCSCAN_API_KEY'),
        'cache_ttl' => (int) env('BSCSCAN_CACHE_TTL', 300),
    ],

    'trongrid' => [
        'base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
        'api_key' => env('TRONGRID_API_KEY'),
        'cache_ttl' => (int) env('TRONGRID_CACHE_TTL', 300),
    ],

    'helius' => [
        'base_url' => env('HELIUS_BASE_URL', 'https://api.helius.xyz'),
        'api_key' => env('HELIUS_API_KEY'),
        'cache_ttl' => (int) env('HELIUS_CACHE_TTL', 300),
    ],

    'blockfrost' => [
        'base_url' => env('BLOCKFROST_BASE_URL', 'https://cardano-mainnet.blockfrost.io/api/v0'),
        'api_key' => env('BLOCKFROST_API_KEY'),
        'cache_ttl' => (int) env('BLOCKFROST_CACHE_TTL', 300),
    ],

    'sochain' => [
        'base_url' => env('SOCHAIN_BASE_URL', 'https://sochain.com/api/v2'),
        'cache_ttl' => (int) env('SOCHAIN_CACHE_TTL', 300),
    ],

    'xrpl' => [
        'base_url' => env('XRPL_BASE_URL', 'https://xrplcluster.com'),
        'cache_ttl' => (int) env('XRPL_CACHE_TTL', 300),
    ],

    'hyperliquid' => [
        'base_url' => env('HYPERLIQUID_BASE_URL', 'https://api.hyperliquid.xyz'),
        'cache_ttl' => (int) env('HYPERLIQUID_CACHE_TTL', 300),
    ],

    /*
     * Supported chains mapped to CoinGecko.
     * coingecko_platform: key in CoinGecko coin detail "platforms" for token lookup.
     * providers: optional per wallet_source_type override (xpub / address).
     */
    'chains' => [
        'bitcoin' => [
            'provider' => 'mempool',
            'providers' => [
                'xpub' => 'blockchain_info',
                'address' => 'mempool',
            ],
            'provider_options' => [],
            'model' => 'utxo',
            'native_coingecko_id' => 'bitcoin',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'ethereum' => [
            'provider' => 'etherscan',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'ethereum',
            'decimals' => 18,
            'coingecko_platform' => 'ethereum',
        ],
        'binance-smart-chain' => [
            'provider' => 'bscscan',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'binancecoin',
            'decimals' => 18,
            'coingecko_platform' => 'binance-smart-chain',
        ],
        'tron' => [
            'provider' => 'trongrid',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'tron',
            'decimals' => 6,
            'coingecko_platform' => 'tron',
        ],
        'solana' => [
            'provider' => 'helius',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'solana',
            'decimals' => 9,
            'coingecko_platform' => 'solana',
        ],
        'ripple' => [
            'provider' => 'xrpl',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'ripple',
            'decimals' => 6,
            'coingecko_platform' => '',
        ],
        'cardano' => [
            'provider' => 'blockfrost',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'cardano',
            'decimals' => 6,
            'coingecko_platform' => 'cardano',
        ],
        'litecoin' => [
            'provider' => 'sochain',
            'providers' => [
                'address' => 'sochain',
            ],
            'provider_options' => [
                'sochain_network' => 'LTC',
            ],
            'model' => 'utxo',
            'native_coingecko_id' => 'litecoin',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'dogecoin' => [
            'provider' => 'sochain',
            'providers' => [
                'address' => 'sochain',
            ],
            'provider_options' => [
                'sochain_network' => 'DOGE',
            ],
            'model' => 'utxo',
            'native_coingecko_id' => 'dogecoin',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'zcash' => [
            'provider' => 'sochain',
            'providers' => [
                'address' => 'sochain',
            ],
            'provider_options' => [
                'sochain_network' => 'ZEC',
            ],
            'model' => 'utxo',
            'native_coingecko_id' => 'zcash',
            'decimals' => 8,
            'coingecko_platform' => '',
        ],
        'hyperliquid' => [
            'provider' => 'hyperliquid',
            'providers' => [],
            'provider_options' => [],
            'model' => 'account',
            'native_coingecko_id' => 'hyperliquid',
            'decimals' => 18,
            'coingecko_platform' => '',
        ],
    ],
];
