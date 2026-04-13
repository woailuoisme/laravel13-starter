<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\JWTGuard;

class EnsureJwtAuthVersionIsCurrent
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');
        $user = $guard->user();

        if ($user instanceof User) {
            $payloadVersion = (int) $guard->payload()->get('av', 1);

            if ($payloadVersion !== $user->auth_version) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.session_invalidated'),
                    'code' => 401,
                ], 401);
            }
        }

        return $next($request);
    }
}
