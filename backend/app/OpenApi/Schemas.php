<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Portfolio',
    required: ['id', 'name', 'crypto_id', 'crypto_symbol', 'crypto_name', 'balance', 'cost_basis_usd', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
        new OA\Property(property: 'crypto_id', type: 'string', example: 'bitcoin'),
        new OA\Property(property: 'crypto_symbol', type: 'string', example: 'btc'),
        new OA\Property(property: 'crypto_name', type: 'string', example: 'Bitcoin'),
        new OA\Property(property: 'balance', type: 'string', example: '1.25000000'),
        new OA\Property(property: 'cost_basis_usd', type: 'string', nullable: true, example: '50000.00000000'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'PortfolioMovement',
    required: ['id', 'type', 'amount', 'unit_price_usd', 'occurred_at', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'type', type: 'string', enum: ['deposit', 'withdrawal'], example: 'deposit'),
        new OA\Property(property: 'amount', type: 'string', example: '0.50000000'),
        new OA\Property(property: 'unit_price_usd', type: 'string', nullable: true, example: '45000.12000000'),
        new OA\Property(property: 'occurred_at', type: 'string', format: 'date-time', example: '2026-08-15T14:30:00+02:00'),
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
#[OA\Schema(
    schema: 'CryptoPrices',
    required: ['currency', 'prices', 'reference_currency', 'reference_prices'],
    properties: [
        new OA\Property(property: 'currency', type: 'string', example: 'chf'),
        new OA\Property(
            property: 'prices',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'number', format: 'float'),
            example: ['bitcoin' => 45000.12],
        ),
        new OA\Property(property: 'reference_currency', type: 'string', example: 'usd'),
        new OA\Property(
            property: 'reference_prices',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'number', format: 'float'),
            example: ['bitcoin' => 50000.0],
        ),
    ],
)]
class Schemas
{
    //
}
