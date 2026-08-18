<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CryptoController extends Controller
{
    #[OA\Get(
        path: '/crypto/coins',
        operationId: 'listCryptoCoins',
        summary: 'Kryptowährungen suchen',
        tags: ['Crypto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'btc'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste der Kryptowährungen',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CryptoCoin'),
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
        ],
    )]
    public function coins(Request $request, CoinGeckoClient $coinGecko): JsonResponse
    {
        $search = $request->query('search');
        $search = is_string($search) ? $search : null;

        $coins = $coinGecko->searchCoins($search);

        return response()->json(array_map(
            fn (array $coin): array => [
                'id' => $coin['id'],
                'symbol' => $coin['symbol'],
                'name' => $coin['name'],
                'image' => $coin['image'],
                'market_cap_rank' => $coin['market_cap_rank'],
            ],
            $coins,
        ));
    }

    #[OA\Get(
        path: '/crypto/prices',
        operationId: 'getCryptoPrices',
        summary: 'Aktuelle Krypto-Preise abrufen',
        tags: ['Crypto'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'ids',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'bitcoin,ethereum'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Preise in EUR',
                content: new OA\JsonContent(
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(
                        properties: [
                            new OA\Property(property: 'eur', type: 'number', format: 'float', example: 45000.12),
                        ],
                        type: 'object',
                    ),
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 422, description: 'Validierungsfehler'),
        ],
    )]
    public function prices(Request $request, CoinGeckoClient $coinGecko): JsonResponse
    {
        $ids = $request->query('ids');

        if (! is_string($ids) || trim($ids) === '') {
            return response()->json([
                'message' => 'The ids field is required.',
                'errors' => [
                    'ids' => ['The ids field is required.'],
                ],
            ], 422);
        }

        $idList = array_values(array_filter(array_map('trim', explode(',', $ids))));

        return response()->json($coinGecko->getPrices($idList));
    }
}
