<?php

use App\Models\User;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
                'currency' => 'usd',
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
            'currency' => 'usd',
        ]);
});

it('updates user currency with valid token', function () {
    $user = User::factory()->create([
        'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        'currency' => 'usd',
    ]);

    $claims = [
        'sub' => $user->keycloak_id,
        'email' => $user->email,
        'name' => $user->name,
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')->andReturn($claims);
    });

    $this->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')->with($claims)->andReturn($user);
    });

    $this->withToken('valid-token')
        ->patchJson('/api/user', ['currency' => 'eur'])
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'currency' => 'eur',
        ]);

    expect($user->fresh()->currency)->toBe('eur');
});

it('rejects invalid currency on update', function () {
    $user = User::factory()->create([
        'keycloak_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        'currency' => 'usd',
    ]);

    $claims = [
        'sub' => $user->keycloak_id,
        'email' => $user->email,
        'name' => $user->name,
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    $this->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')->andReturn($claims);
    });

    $this->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')->with($claims)->andReturn($user);
    });

    $this->withToken('valid-token')
        ->patchJson('/api/user', ['currency' => 'xyz'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});
