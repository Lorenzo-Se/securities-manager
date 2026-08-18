<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Portfolio',
    required: ['id', 'name', 'crypto_id', 'crypto_symbol', 'crypto_name', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
        new OA\Property(property: 'crypto_id', type: 'string', example: 'bitcoin'),
        new OA\Property(property: 'crypto_symbol', type: 'string', example: 'btc'),
        new OA\Property(property: 'crypto_name', type: 'string', example: 'Bitcoin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'CryptoCoin',
    required: ['id', 'symbol', 'name', 'image'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'bitcoin'),
        new OA\Property(property: 'symbol', type: 'string', example: 'btc'),
        new OA\Property(property: 'name', type: 'string', example: 'Bitcoin'),
        new OA\Property(property: 'image', type: 'string', format: 'uri', example: 'https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png'),
        new OA\Property(property: 'market_cap_rank', type: 'integer', nullable: true, example: 1),
    ],
)]
class Schemas
{
    //
}
