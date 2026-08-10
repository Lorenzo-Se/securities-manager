<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    #[OA\Get(
        path: '/health',
        operationId: 'getHealth',
        summary: 'API-Status prüfen',
        description: 'Gibt den aktuellen Status der API zurück.',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'API ist erreichbar',
                content: new OA\JsonContent(
                    required: ['status', 'timestamp'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-08-10T12:00:00+00:00'),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
