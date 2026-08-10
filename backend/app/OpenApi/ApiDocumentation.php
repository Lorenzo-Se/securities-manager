<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Securities Manager API',
    description: 'OpenAPI-Dokumentation für die Securities Manager API',
)]
#[OA\Server(
    url: '/api',
    description: 'API-Basis-URL',
)]
class ApiDocumentation
{
    //
}
