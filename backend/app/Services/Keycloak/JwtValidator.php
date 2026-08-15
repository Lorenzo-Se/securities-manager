<?php

namespace App\Services\Keycloak;

use Firebase\JWT\JWT;
use UnexpectedValueException;

class JwtValidator
{
    public function __construct(private readonly JwksFetcher $jwksFetcher) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(string $token): array
    {
        $headers = new \stdClass;
        $decoded = JWT::decode($token, $this->jwksFetcher->getKeySet(), $headers);
        $claims = (array) $decoded;

        $this->validateClaims($claims);

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function validateClaims(array $claims): void
    {
        if (($claims['iss'] ?? null) !== config('keycloak.issuer')) {
            throw new UnexpectedValueException('Invalid token issuer.');
        }

        $clientId = config('keycloak.client_id');

        // Keycloak access tokens set azp to the client id; aud is often "account".
        if (($claims['azp'] ?? null) === $clientId) {
            return;
        }

        $audience = $claims['aud'] ?? null;

        if (is_array($audience) && in_array($clientId, $audience, true)) {
            return;
        }

        if ($audience === $clientId) {
            return;
        }

        throw new UnexpectedValueException('Invalid token audience.');
    }
}
