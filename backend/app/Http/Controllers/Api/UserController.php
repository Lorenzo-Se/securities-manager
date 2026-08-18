<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/user',
        operationId: 'getAuthenticatedUser',
        summary: 'Aktuellen Benutzer abrufen',
        description: 'Gibt den authentifizierten Benutzer zurück.',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentifizierter Benutzer',
                content: new OA\JsonContent(
                    required: ['id', 'name', 'email', 'keycloak_id', 'currency'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Demo User'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@example.com'),
                        new OA\Property(property: 'keycloak_id', type: 'string', format: 'uuid', example: 'f47ac10b-58cc-4372-a567-0e02b2c3d479'),
                        new OA\Property(property: 'currency', type: 'string', example: 'usd'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    #[OA\Patch(
        path: '/user',
        operationId: 'updateAuthenticatedUser',
        summary: 'Benutzereinstellungen aktualisieren',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['currency'],
                properties: [
                    new OA\Property(property: 'currency', type: 'string', example: 'eur'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Aktualisierter Benutzer',
                content: new OA\JsonContent(
                    required: ['id', 'name', 'email', 'keycloak_id', 'currency'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Demo User'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@example.com'),
                        new OA\Property(property: 'keycloak_id', type: 'string', format: 'uuid', example: 'f47ac10b-58cc-4372-a567-0e02b2c3d479'),
                        new OA\Property(property: 'currency', type: 'string', example: 'eur'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
            new OA\Response(response: 422, description: 'Validierungsfehler'),
        ],
    )]
    public function update(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json($this->userPayload($user->fresh()));
    }

    /**
     * @return array{id: int, name: string, email: string, keycloak_id: string|null, currency: string}
     */
    private function userPayload(\App\Models\User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'keycloak_id' => $user->keycloak_id,
            'currency' => $user->preferredCurrency(),
        ];
    }
}
