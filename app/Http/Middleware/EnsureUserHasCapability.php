<?php

namespace App\Http\Middleware;

use App\Auth\CapabilityAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCapability
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $tenantId = $request->attributes->get('tenant_id');
        $capabilities = explode('|', $capability);

        abort_unless(
            $user && collect($capabilities)->contains(fn (string $capability) => $this->authorizer->allows($user, $capability, $tenantId)),
            403,
        );

        return $next($request);
    }
}
