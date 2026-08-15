<?php

use App\Models\User;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;

it('returns unauthorized without token', function () {
    $this->getJson('/api/user')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns authenticated user with valid token', function () {
    $claims = [
        'sub' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        'email' => 'demo@example.com',
        'name' => 'Demo User',
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')
            ->once()
            ->with('valid-token')
            ->andReturn($claims);
    });

    $this->mock(UserProvisioner::class, function ($mock) use ($claims) {
        $mock->shouldReceive('provision')
            ->once()
            ->with($claims)
            ->andReturn(User::factory()->make([
                'id' => 1,
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            ]));
    });

    $this->withToken('valid-token')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJson([
            'id' => 1,
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);
});
