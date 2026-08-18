<?php

namespace App\Http\Middleware;

use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserInfoResolver;
use App\Services\Keycloak\UserProvisioner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use UnexpectedValueException;

class AuthenticateKeycloak
{
    public function __construct(
        private readonly JwtValidator $jwtValidator,
        private readonly UserProvisioner $userProvisioner,
        private readonly UserInfoResolver $userInfoResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $claims = $this->jwtValidator->validate($token);

            if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
                $claims['sub'] = $this->userInfoResolver->resolveSubject($token);
            }

            $user = $this->userProvisioner->provision($claims);
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        } catch (UnexpectedValueException|\InvalidArgumentException $exception) {
            Log::warning('Keycloak token rejected', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $exception) {
            Log::error('Keycloak authentication failed', [
                'message' => $exception->getMessage(),
                'type' => $exception::class,
            ]);

            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
