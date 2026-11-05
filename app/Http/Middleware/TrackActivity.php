<?php

namespace App\Http\Middleware;

use App\Models\UserActivityDay;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** One row per user per day (no page-level tracking). */
final class TrackActivity
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (! $user?->tenant_id) {
            return $response;
        }

        $key = 'active:'.$user->id.':'.now()->toDateString();

        if (! cache()->has($key)) {
            // On POST /login the user was a guest when SetTenantContext ran, so no tenant is set yet. Scope the write to the
            // user's OWN tenant (runAs restores the previous context). RLS stays enforced: a mismatched tenant_id is still rejected.
            $this->context->runAs((int) $user->tenant_id, function () use ($user) {
                UserActivityDay::query()->firstOrCreate(['user_id' => $user->id, 'day' => now()->toDateString()], ['tenant_id' => $user->tenant_id]);
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            });
            cache()->put($key, true, now()->endOfDay()); // only after the write succeeded, so a failure is retried
        }

        return $response;
    }
}
