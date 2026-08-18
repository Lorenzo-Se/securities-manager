<?php

namespace App\Services\Keycloak;

use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class UserInfoResolver
{
    public function resolveSubject(string $accessToken): string
    {
        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->get(config('keycloak.issuer').'/protocol/openid-connect/userinfo');

        if (! $response->successful()) {
            throw new UnexpectedValueException('Failed to resolve user from Keycloak userinfo.');
        }

        $sub = $response->json('sub');

        if (! is_string($sub) || $sub === '') {
            throw new UnexpectedValueException('Keycloak userinfo response is missing subject.');
        }

        return $sub;
    }
}
