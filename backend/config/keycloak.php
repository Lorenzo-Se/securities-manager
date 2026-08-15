<?php

return [

    'base_url' => env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),

    'realm' => env('KEYCLOAK_REALM', 'securities-manager'),

    'client_id' => env('KEYCLOAK_CLIENT_ID', 'frontend'),

    'jwks_cache_ttl' => (int) env('KEYCLOAK_JWKS_CACHE_TTL', 3600),

    'issuer' => env(
        'KEYCLOAK_ISSUER',
        rtrim(env('KEYCLOAK_BASE_URL', 'http://localhost:8080'), '/').'/realms/'.env('KEYCLOAK_REALM', 'securities-manager'),
    ),

    'jwks_url' => env(
        'KEYCLOAK_JWKS_URL',
        rtrim(env('KEYCLOAK_BASE_URL', 'http://localhost:8080'), '/').'/realms/'.env('KEYCLOAK_REALM', 'securities-manager').'/protocol/openid-connect/certs',
    ),

];
