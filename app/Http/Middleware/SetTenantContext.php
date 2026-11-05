<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            if ($user->status === 'disabled' || ($user->tenant !== null && $user->tenant->status === 'suspended')) {
                abort(403, __('Account is not active.'));
            }

            $this->context->set($user->tenant_id); // platform admins have no tenant; they use explicit platform mode
        }

        return $next($request);
    }

    public function terminate(): void
    {
        $this->context->clear(); // never leak context into the next request on long-lived workers (Octane)
    }
}
