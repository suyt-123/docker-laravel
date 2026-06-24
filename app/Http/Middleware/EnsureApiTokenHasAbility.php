<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenHasAbility
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tokenCan($ability)) {
            if ($request->is('api/v1/*')) {
                return ApiResponse::error(
                    message: 'Token ability is missing.',
                    code: 'token_ability_missing',
                    meta: ['ability' => $ability],
                    status: 403,
                );
            }

            abort(403);
        }

        return $next($request);
    }
}
