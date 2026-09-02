<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PortfolioMovementController extends Controller
{
    #[OA\Get(
        path: '/portfolios/{portfolio}/movements',
        operationId: 'listPortfolioMovements',
        summary: 'Transaktionen eines Portfolios abrufen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'portfolio',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste der Transaktionen',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PortfolioMovement'),
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
        ],
    )]
    public function index(Request $request, Portfolio $portfolio): JsonResponse
    {
        $this->ensurePortfolioOwnership($request, $portfolio);

        $movements = $portfolio->movements()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(
            $movements->map(fn (PortfolioMovement $movement) => $this->formatMovement($movement)),
        );
    }

    private function ensurePortfolioOwnership(Request $request, Portfolio $portfolio): void
    {
        if ($portfolio->user_id !== $request->user()->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMovement(PortfolioMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'amount' => number_format((float) $movement->amount, 8, '.', ''),
            'unit_price_usd' => $movement->unit_price_usd !== null
                ? number_format((float) $movement->unit_price_usd, 8, '.', '')
                : null,
            'occurred_at' => $movement->occurred_at?->toIso8601String(),
            'tx_hash' => $movement->tx_hash,
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }
}
