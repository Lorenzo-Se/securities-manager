<?php

use App\Services\Keycloak\JwtValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('provisions user when access token lacks sub claim', function () {
    Http::fake([
        config('keycloak.issuer').'/protocol/openid-connect/userinfo' => Http::response([
            'sub' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            'email' => 'demo@example.com',
            'name' => 'Demo User',
        ]),
    ]);

    $claims = [
        'email' => 'demo@example.com',
        'name' => 'Demo User',
        'iss' => config('keycloak.issuer'),
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')
            ->once()
            ->with('lightweight-token')
            ->andReturn($claims);
    });

    $this->withToken('lightweight-token')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJson([
            'email' => 'demo@example.com',
            'name' => 'Demo User',
            'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ]);

    $this->assertDatabaseHas('users', [
        'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        'email' => 'demo@example.com',
    ]);
});

it('returns unauthorized when sub cannot be resolved', function () {
    Http::fake([
        '*/protocol/openid-connect/userinfo' => Http::response([], 401),
    ]);

    $claims = [
        'email' => 'demo@example.com',
        'iss' => config('keycloak.issuer'),
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')
            ->once()
            ->with('lightweight-token')
            ->andReturn($claims);
    });

    $this->withToken('lightweight-token')
        ->getJson('/api/user')
        ->assertUnauthorized();
});
