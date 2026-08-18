<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortfolioMovementRequest;
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
        summary: 'Buchungen eines Portfolios abrufen',
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
                description: 'Liste der Buchungen',
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
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json(
            $movements->map(fn (PortfolioMovement $movement) => $this->formatMovement($movement)),
        );
    }

    #[OA\Post(
        path: '/portfolios/{portfolio}/movements',
        operationId: 'createPortfolioMovement',
        summary: 'Neue Buchung anlegen',
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
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'amount', 'date'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['deposit', 'withdrawal'], example: 'deposit'),
                    new OA\Property(property: 'amount', type: 'string', example: '0.5'),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-08-15'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Buchung erstellt',
                content: new OA\JsonContent(ref: '#/components/schemas/PortfolioMovement'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
            new OA\Response(response: 422, description: 'Validierungsfehler'),
        ],
    )]
    public function store(
        StorePortfolioMovementRequest $request,
        Portfolio $portfolio,
    ): JsonResponse {
        $this->ensurePortfolioOwnership($request, $portfolio);

        $movement = $portfolio->movements()->create([
            'type' => $request->string('type')->toString(),
            'amount' => $request->string('amount')->toString(),
            'date' => $request->date('date'),
        ]);

        return response()->json($this->formatMovement($movement), 201);
    }

    #[OA\Delete(
        path: '/portfolios/{portfolio}/movements/{movement}',
        operationId: 'deletePortfolioMovement',
        summary: 'Buchung löschen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'portfolio',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'movement',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Buchung gelöscht'),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio oder Buchung nicht gefunden'),
        ],
    )]
    public function destroy(
        Request $request,
        Portfolio $portfolio,
        PortfolioMovement $movement,
    ): JsonResponse {
        $this->ensurePortfolioOwnership($request, $portfolio);

        if ($movement->portfolio_id !== $portfolio->id) {
            abort(404);
        }

        $movement->delete();

        return response()->json(null, 204);
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
            'date' => $movement->date?->format('Y-m-d'),
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }
}
