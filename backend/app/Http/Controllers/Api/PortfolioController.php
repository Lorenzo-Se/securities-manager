<?php

namespace App\Http\Controllers\Api;

use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortfolioRequest;
use App\Http\Requests\UpdatePortfolioRequest;
use App\Jobs\SyncPortfolioJob;
use App\Models\Portfolio;
use App\Services\Portfolio\PortfolioAccessService;
use App\Services\Portfolio\PortfolioPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PortfolioController extends Controller
{
    public function __construct(
        private readonly PortfolioAccessService $accessService,
        private readonly PortfolioPresenter $presenter,
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
        $user = $request->user();

        $ownedPortfolios = $user->portfolios()
            ->with('movements')
            ->latest()
            ->get();

        $sharedPortfolios = $user->sharedPortfolios()
            ->with('movements')
            ->latest('portfolios.created_at')
            ->get();

        $portfolios = $ownedPortfolios
            ->concat($sharedPortfolios)
            ->unique('id')
            ->values();

        return response()->json(
            $portfolios->map(fn (Portfolio $portfolio) => $this->presenter->format($portfolio, $user)),
        );
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
        if (! $this->accessService->canView($portfolio, $request->user())) {
            abort(404);
        }

        $portfolio->load('movements');

        return response()->json($this->presenter->format($portfolio, $request->user()));
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
                required: ['name', 'crypto_id', 'wallet_source'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
                    new OA\Property(property: 'crypto_id', type: 'string', example: 'bitcoin'),
                    new OA\Property(property: 'wallet_source', type: 'string', example: 'xpub6CUGRUon...'),
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
        $syncable = $request->resolvedSyncableCoin();

        $portfolio = $request->user()->portfolios()->create([
            'name' => $request->string('name')->toString(),
            'crypto_id' => $coin['id'],
            'crypto_symbol' => $coin['symbol'],
            'crypto_name' => $coin['name'],
            'wallet_source_type' => $syncable->walletSourceType,
            'wallet_source' => trim($request->string('wallet_source')->toString()),
            'chain_slug' => $syncable->chainSlug,
            'token_contract' => $syncable->tokenContract,
            'sync_status' => SyncStatus::Pending,
        ]);

        SyncPortfolioJob::dispatch($portfolio->id);

        return response()->json($this->presenter->format($portfolio, $request->user()), 201);
    }

    #[OA\Patch(
        path: '/portfolios/{portfolio}',
        operationId: 'updatePortfolio',
        summary: 'Portfolio bearbeiten',
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
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mein BTC'),
                    new OA\Property(property: 'wallet_source', type: 'string', example: 'xpub6CUGRUon...'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Portfolio aktualisiert',
                content: new OA\JsonContent(ref: '#/components/schemas/Portfolio'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
        ],
    )]
    public function update(
        UpdatePortfolioRequest $request,
        Portfolio $portfolio,
    ): JsonResponse {
        if (! $this->accessService->canManage($portfolio, $request->user())) {
            abort(404);
        }

        $walletSourceChanged = false;

        if ($request->has('name')) {
            $portfolio->name = $request->string('name')->toString();
        }

        if ($request->has('wallet_source')) {
            $newWalletSource = trim($request->string('wallet_source')->toString());
            $walletSourceChanged = $portfolio->wallet_source !== $newWalletSource;
            $portfolio->wallet_source = $newWalletSource;
        }

        if ($walletSourceChanged) {
            $portfolio->sync_status = SyncStatus::Pending;
            $portfolio->sync_error = null;
        }

        $portfolio->save();

        if ($walletSourceChanged) {
            SyncPortfolioJob::dispatch($portfolio->id);
        }

        return response()->json($this->presenter->format($portfolio->fresh(), $request->user()));
    }

    #[OA\Post(
        path: '/portfolios/{portfolio}/sync',
        operationId: 'syncPortfolio',
        summary: 'Portfolio manuell synchronisieren',
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
                response: 202,
                description: 'Sync gestartet',
                content: new OA\JsonContent(ref: '#/components/schemas/Portfolio'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
        ],
    )]
    public function sync(Request $request, Portfolio $portfolio): JsonResponse
    {
        if (! $this->accessService->canManage($portfolio, $request->user())) {
            abort(404);
        }

        $portfolio->update([
            'sync_status' => SyncStatus::Pending,
            'sync_error' => null,
        ]);

        SyncPortfolioJob::dispatch($portfolio->id);

        return response()->json($this->presenter->format($portfolio->fresh(), $request->user()), 202);
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
        if (! $this->accessService->canManage($portfolio, $request->user())) {
            abort(404);
        }

        $portfolio->delete();

        return response()->json(null, 204);
    }
}
