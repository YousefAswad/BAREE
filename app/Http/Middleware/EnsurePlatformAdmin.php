<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** BAREE staff area: platform admin flag + confirmed 2FA + platform mode for the request. */
final class EnsurePlatformAdmin
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->is_platform_admin, 404); // do not even reveal the area exists
        abort_if(app()->isProduction() && $user->two_factor_confirmed_at === null, 403, 'Two-factor authentication is required.');

        return $this->context->asPlatform(fn () => $next($request));
    }
}
