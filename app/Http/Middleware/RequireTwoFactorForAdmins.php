<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Anyone who can manage people or settings must have 2FA confirmed (enforced in production). */
final class RequireTwoFactorForAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (app()->isProduction() && $user && $user->two_factor_confirmed_at === null
            && ($user->hasPermission('users.manage') || $user->hasPermission('tenant.settings.manage'))) {
            return redirect()->route('two-factor.show')->with('status', __('Enable two-factor authentication to use the admin area.'));
        }

        return $next($request);
    }
}
