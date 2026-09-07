<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Portfolio',
    required: ['id', 'name', 'crypto_id', 'crypto_symbol', 'crypto_name', 'wallet_source_type', 'wallet_source', 'sync_status', 'access_role', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
        new OA\Property(property: 'crypto_id', type: 'string', example: 'bitcoin'),
        new OA\Property(property: 'crypto_symbol', type: 'string', example: 'btc'),
        new OA\Property(property: 'crypto_name', type: 'string', example: 'Bitcoin'),
        new OA\Property(property: 'wallet_source_type', type: 'string', enum: ['xpub', 'address'], nullable: true, example: 'xpub'),
        new OA\Property(property: 'wallet_source', type: 'string', nullable: true, example: 'xpub6C...cUx'),
        new OA\Property(property: 'sync_status', type: 'string', enum: ['pending', 'syncing', 'synced', 'failed'], example: 'synced'),
        new OA\Property(property: 'sync_error', type: 'string', nullable: true),
        new OA\Property(property: 'last_synced_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'balance', type: 'string', nullable: true, example: '1.25000000'),
        new OA\Property(property: 'cost_basis_usd', type: 'string', nullable: true, example: '50000.00000000'),
        new OA\Property(property: 'access_role', type: 'string', enum: ['owner', 'viewer'], example: 'owner'),
        new OA\Property(property: 'share', ref: '#/components/schemas/PortfolioShareInfo', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'PortfolioShareInfo',
    required: ['type', 'show_total_balance'],
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['viewer', 'amount', 'percentage'], example: 'amount'),
        new OA\Property(property: 'amount', type: 'string', nullable: true, example: '0.50000000'),
        new OA\Property(property: 'percentage', type: 'string', nullable: true, example: '25.00'),
        new OA\Property(property: 'show_total_balance', type: 'boolean', example: false),
        new OA\Property(property: 'my_share_amount', type: 'string', nullable: true, example: '0.50000000'),
        new OA\Property(property: 'my_share_cost_basis_usd', type: 'string', nullable: true, example: '12500.00000000'),
    ],
)]
#[OA\Schema(
    schema: 'PortfolioShare',
    required: ['id', 'portfolio_id', 'invited_email', 'invited_by', 'share_type', 'show_total_balance', 'status', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'portfolio_id', type: 'integer', example: 1),
        new OA\Property(property: 'invited_email', type: 'string', format: 'email', example: 'person@example.com'),
        new OA\Property(property: 'invited_user', ref: '#/components/schemas/PortfolioShareUser', nullable: true),
        new OA\Property(property: 'invited_by', ref: '#/components/schemas/PortfolioShareUser'),
        new OA\Property(property: 'share_type', type: 'string', enum: ['viewer', 'amount', 'percentage']),
        new OA\Property(property: 'share_amount', type: 'string', nullable: true),
        new OA\Property(property: 'share_percentage', type: 'string', nullable: true),
        new OA\Property(property: 'show_total_balance', type: 'boolean'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'accepted', 'declined', 'revoked']),
        new OA\Property(property: 'accepted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'declined_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'PortfolioShareInvitation',
    required: ['id', 'portfolio', 'invited_by', 'share_type', 'show_total_balance', 'status', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'portfolio', ref: '#/components/schemas/PortfolioShareInvitationPortfolio'),
        new OA\Property(property: 'invited_by', ref: '#/components/schemas/PortfolioShareUser'),
        new OA\Property(property: 'share_type', type: 'string', enum: ['viewer', 'amount', 'percentage']),
        new OA\Property(property: 'share_amount', type: 'string', nullable: true),
        new OA\Property(property: 'share_percentage', type: 'string', nullable: true),
        new OA\Property(property: 'show_total_balance', type: 'boolean'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'accepted', 'declined', 'revoked']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'PortfolioShareInvitationPortfolio',
    required: ['id', 'name', 'crypto_symbol', 'crypto_name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
        new OA\Property(property: 'crypto_symbol', type: 'string', example: 'btc'),
        new OA\Property(property: 'crypto_name', type: 'string', example: 'Bitcoin'),
    ],
)]
#[OA\Schema(
    schema: 'PortfolioShareUser',
    required: ['id', 'name', 'email'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Max Mustermann'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'max@example.com'),
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
        new OA\Property(property: 'tx_hash', type: 'string', nullable: true, example: 'a1b2c3...'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'CryptoCoin',
    required: ['id', 'symbol', 'name', 'image', 'sync_supported'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'bitcoin'),
        new OA\Property(property: 'symbol', type: 'string', example: 'btc'),
        new OA\Property(property: 'name', type: 'string', example: 'Bitcoin'),
        new OA\Property(property: 'image', type: 'string', format: 'uri', example: 'https://coin-images.coingecko.com/coins/images/1/large/bitcoin.png'),
        new OA\Property(property: 'market_cap_rank', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'sync_supported', type: 'boolean', example: true),
        new OA\Property(property: 'wallet_source_type', type: 'string', enum: ['xpub', 'address'], nullable: true, example: 'xpub'),
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
