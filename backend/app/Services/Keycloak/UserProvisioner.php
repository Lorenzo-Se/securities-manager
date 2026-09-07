<?php

namespace App\Services\Keycloak;

use App\Enums\PortfolioShareStatus;
use App\Models\PortfolioShare;
use App\Models\User;
use Illuminate\Support\Str;

class UserProvisioner
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function provision(array $claims): User
    {
        $keycloakId = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? $claims['preferred_username'] ?? '');
        $name = (string) ($claims['name'] ?? $claims['preferred_username'] ?? 'User');

        if ($keycloakId === '') {
            throw new \InvalidArgumentException('Token is missing subject claim.');
        }

        if ($email === '') {
            $email = "{$keycloakId}@keycloak.local";
        }

        $user = User::query()->updateOrCreate(
            ['keycloak_id' => $keycloakId],
            [
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Str::password(32),
            ],
        );

        PortfolioShare::query()
            ->where('invited_email', Str::lower($email))
            ->where('status', PortfolioShareStatus::Pending)
            ->whereNull('invited_user_id')
            ->update(['invited_user_id' => $user->id]);

        return $user;
    }
}
