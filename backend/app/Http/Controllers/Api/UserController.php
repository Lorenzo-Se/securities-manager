<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                    required: ['id', 'name', 'email', 'keycloak_id'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Demo User'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@example.com'),
                        new OA\Property(property: 'keycloak_id', type: 'string', format: 'uuid', example: 'f47ac10b-58cc-4372-a567-0e02b2c3d479'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Nicht authentifiziert'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'keycloak_id' => $user->keycloak_id,
        ]);
    }
}
