<?php

namespace App\Http\Controllers\Api;

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortfolioShareRequest;
use App\Models\Portfolio;
use App\Models\PortfolioShare;
use App\Models\User;
use App\Services\Portfolio\PortfolioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PortfolioShareController extends Controller
{
    public function __construct(
        private readonly PortfolioAccessService $accessService,
    ) {}

    #[OA\Get(
        path: '/portfolios/{portfolio}/shares',
        operationId: 'listPortfolioShares',
        summary: 'Freigaben eines Portfolios abrufen',
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
                description: 'Liste der Freigaben',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PortfolioShare'),
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
        ],
    )]
    public function index(Request $request, Portfolio $portfolio): JsonResponse
    {
        if (! $this->accessService->canManage($portfolio, $request->user())) {
            abort(404);
        }

        $shares = $portfolio->shares()
            ->with(['invitedUser', 'invitedBy'])
            ->latest()
            ->get();

        return response()->json(
            $shares->map(fn (PortfolioShare $share) => $this->formatShare($share)),
        );
    }

    #[OA\Post(
        path: '/portfolios/{portfolio}/shares',
        operationId: 'createPortfolioShare',
        summary: 'Portfolio-Freigabe erstellen',
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
                required: ['email', 'share_type'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'person@example.com'),
                    new OA\Property(property: 'share_type', type: 'string', enum: ['viewer', 'amount', 'percentage']),
                    new OA\Property(property: 'share_amount', type: 'string', nullable: true, example: '0.50000000'),
                    new OA\Property(property: 'share_percentage', type: 'string', nullable: true, example: '25.00'),
                    new OA\Property(property: 'show_total_balance', type: 'boolean', example: false),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Freigabe erstellt',
                content: new OA\JsonContent(ref: '#/components/schemas/PortfolioShare'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Portfolio nicht gefunden'),
            new OA\Response(response: 422, description: 'Validierungsfehler'),
        ],
    )]
    public function store(StorePortfolioShareRequest $request, Portfolio $portfolio): JsonResponse
    {
        if (! $this->accessService->canManage($portfolio, $request->user())) {
            abort(404);
        }

        $shareType = $request->shareType();
        $email = $request->normalizedEmail();
        $invitedUser = User::query()->where('email', $email)->first();

        $share = $portfolio->shares()->create([
            'invited_by_user_id' => $request->user()->id,
            'invited_email' => $email,
            'invited_user_id' => $invitedUser?->id,
            'share_type' => $shareType,
            'share_amount' => $shareType === PortfolioShareType::Amount
                ? $request->string('share_amount')->toString()
                : null,
            'share_percentage' => $shareType === PortfolioShareType::Percentage
                ? $request->string('share_percentage')->toString()
                : null,
            'show_total_balance' => $shareType === PortfolioShareType::Viewer
                ? false
                : $request->boolean('show_total_balance'),
            'status' => PortfolioShareStatus::Pending,
        ]);

        $share->load(['invitedUser', 'invitedBy']);

        return response()->json($this->formatShare($share), 201);
    }

    #[OA\Delete(
        path: '/portfolios/{portfolio}/shares/{share}',
        operationId: 'revokePortfolioShare',
        summary: 'Portfolio-Freigabe widerrufen',
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
                name: 'share',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Freigabe widerrufen'),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Nicht gefunden'),
        ],
    )]
    public function destroy(Request $request, Portfolio $portfolio, PortfolioShare $share): JsonResponse
    {
        if (! $this->accessService->canManage($portfolio, $request->user())) {
            abort(404);
        }

        if ($share->portfolio_id !== $portfolio->id) {
            abort(404);
        }

        $share->update([
            'status' => PortfolioShareStatus::Revoked,
        ]);

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/portfolio-shares/invitations',
        operationId: 'listPortfolioShareInvitations',
        summary: 'Offene Portfolio-Einladungen abrufen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste der Einladungen',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PortfolioShareInvitation'),
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
        ],
    )]
    public function invitations(Request $request): JsonResponse
    {
        $user = $request->user();
        $email = strtolower($user->email);

        $shares = PortfolioShare::query()
            ->with(['portfolio', 'invitedBy'])
            ->where('status', PortfolioShareStatus::Pending)
            ->where(function ($query) use ($user, $email): void {
                $query->where('invited_user_id', $user->id)
                    ->orWhere('invited_email', $email);
            })
            ->latest()
            ->get();

        return response()->json(
            $shares->map(fn (PortfolioShare $share) => $this->formatInvitation($share)),
        );
    }

    #[OA\Post(
        path: '/portfolio-shares/{share}/accept',
        operationId: 'acceptPortfolioShare',
        summary: 'Portfolio-Einladung annehmen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'share',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Einladung angenommen',
                content: new OA\JsonContent(ref: '#/components/schemas/PortfolioShareInvitation'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Nicht gefunden'),
        ],
    )]
    public function accept(Request $request, PortfolioShare $share): JsonResponse
    {
        if (! $this->canRespondToInvitation($share, $request->user())) {
            abort(404);
        }

        $share->update([
            'invited_user_id' => $request->user()->id,
            'status' => PortfolioShareStatus::Accepted,
            'accepted_at' => now(),
            'declined_at' => null,
        ]);

        $share->load(['portfolio', 'invitedBy']);

        return response()->json($this->formatInvitation($share));
    }

    #[OA\Post(
        path: '/portfolio-shares/{share}/decline',
        operationId: 'declinePortfolioShare',
        summary: 'Portfolio-Einladung ablehnen',
        tags: ['Portfolio'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'share',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Einladung abgelehnt',
                content: new OA\JsonContent(ref: '#/components/schemas/PortfolioShareInvitation'),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 404, description: 'Nicht gefunden'),
        ],
    )]
    public function decline(Request $request, PortfolioShare $share): JsonResponse
    {
        if (! $this->canRespondToInvitation($share, $request->user())) {
            abort(404);
        }

        $share->update([
            'invited_user_id' => $request->user()->id,
            'status' => PortfolioShareStatus::Declined,
            'declined_at' => now(),
        ]);

        $share->load(['portfolio', 'invitedBy']);

        return response()->json($this->formatInvitation($share));
    }

    private function canRespondToInvitation(PortfolioShare $share, User $user): bool
    {
        if ($share->status !== PortfolioShareStatus::Pending) {
            return false;
        }

        $email = strtolower($user->email);

        return $share->invited_user_id === $user->id
            || strtolower($share->invited_email) === $email;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatShare(PortfolioShare $share): array
    {
        return [
            'id' => $share->id,
            'portfolio_id' => $share->portfolio_id,
            'invited_email' => $share->invited_email,
            'invited_user' => $share->invitedUser ? [
                'id' => $share->invitedUser->id,
                'name' => $share->invitedUser->name,
                'email' => $share->invitedUser->email,
            ] : null,
            'invited_by' => [
                'id' => $share->invitedBy->id,
                'name' => $share->invitedBy->name,
                'email' => $share->invitedBy->email,
            ],
            'share_type' => $share->share_type->value,
            'share_amount' => $share->share_amount !== null
                ? number_format((float) $share->share_amount, 8, '.', '')
                : null,
            'share_percentage' => $share->share_percentage !== null
                ? number_format((float) $share->share_percentage, 2, '.', '')
                : null,
            'show_total_balance' => $share->show_total_balance,
            'status' => $share->status->value,
            'accepted_at' => $share->accepted_at?->toIso8601String(),
            'declined_at' => $share->declined_at?->toIso8601String(),
            'created_at' => $share->created_at?->toIso8601String(),
            'updated_at' => $share->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvitation(PortfolioShare $share): array
    {
        return [
            'id' => $share->id,
            'portfolio' => [
                'id' => $share->portfolio->id,
                'name' => $share->portfolio->name,
                'crypto_symbol' => $share->portfolio->crypto_symbol,
                'crypto_name' => $share->portfolio->crypto_name,
            ],
            'invited_by' => [
                'id' => $share->invitedBy->id,
                'name' => $share->invitedBy->name,
                'email' => $share->invitedBy->email,
            ],
            'share_type' => $share->share_type->value,
            'share_amount' => $share->share_amount !== null
                ? number_format((float) $share->share_amount, 8, '.', '')
                : null,
            'share_percentage' => $share->share_percentage !== null
                ? number_format((float) $share->share_percentage, 2, '.', '')
                : null,
            'show_total_balance' => $share->show_total_balance,
            'status' => $share->status->value,
            'created_at' => $share->created_at?->toIso8601String(),
        ];
    }
}
