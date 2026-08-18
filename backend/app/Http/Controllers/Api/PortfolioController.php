<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortfolioRequest;
use App\Models\Portfolio;
use App\Services\Portfolio\PortfolioCostBasisCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PortfolioController extends Controller
{
    public function __construct(
        private readonly PortfolioCostBasisCalculator $costBasisCalculator,
    ) {}
    #[OA\Get(
        path: '/portfolios',
        operationId: 'listPortfolios',
        summary: 'Portfolios des Benutzers abrufen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste der Portfolios',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Portfolio'),
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $portfolios = $request->user()
            ->portfolios()
            ->with('movements')
            ->latest()
            ->get();

        return response()->json($portfolios->map(fn (Portfolio $portfolio) => $this->formatPortfolio($portfolio)));
    }

    #[OA\Get(
        path: '/portfolios/{portfolio}',
        operationId: 'getPortfolio',
        summary: 'Einzelnes Portfolio abrufen',
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
                description: 'Portfolio',
                content: new OA\JsonContent(ref: '#/components/schemas/Portfolio'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
        ],
    )]
    public function show(Request $request, Portfolio $portfolio): JsonResponse
    {
        if ($portfolio->user_id !== $request->user()->id) {
            abort(404);
        }

        $portfolio->load('movements');

        return response()->json($this->formatPortfolio($portfolio));
    }

    #[OA\Post(
        path: '/portfolios',
        operationId: 'createPortfolio',
        summary: 'Neues Portfolio erstellen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'crypto_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
                    new OA\Property(property: 'crypto_id', type: 'string', example: 'bitcoin'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Portfolio erstellt',
                content: new OA\JsonContent(ref: '#/components/schemas/Portfolio'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 422, description: 'Validierungsfehler'),
        ],
    )]
    public function store(StorePortfolioRequest $request): JsonResponse
    {
        $coin = $request->resolvedCoin();

        $portfolio = $request->user()->portfolios()->create([
            'name' => $request->string('name')->toString(),
            'crypto_id' => $coin['id'],
            'crypto_symbol' => $coin['symbol'],
            'crypto_name' => $coin['name'],
        ]);

        return response()->json($this->formatPortfolio($portfolio), 201);
    }

    #[OA\Delete(
        path: '/portfolios/{portfolio}',
        operationId: 'deletePortfolio',
        summary: 'Portfolio löschen',
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
            new OA\Response(response: 204, description: 'Portfolio gelöscht'),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
        ],
    )]
    public function destroy(Request $request, Portfolio $portfolio): JsonResponse
    {
        if ($portfolio->user_id !== $request->user()->id) {
            abort(404);
        }

        $portfolio->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPortfolio(Portfolio $portfolio): array
    {
        $costBasisUsd = $this->costBasisCalculator->calculate($portfolio->movements);

        return [
            'id' => $portfolio->id,
            'name' => $portfolio->name,
            'crypto_id' => $portfolio->crypto_id,
            'crypto_symbol' => $portfolio->crypto_symbol,
            'crypto_name' => $portfolio->crypto_name,
            'balance' => $portfolio->balance,
            'cost_basis_usd' => $costBasisUsd,
            'created_at' => $portfolio->created_at?->toIso8601String(),
            'updated_at' => $portfolio->updated_at?->toIso8601String(),
        ];
    }
}
