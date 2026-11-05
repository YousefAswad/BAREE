<?php

namespace App\Http\Middleware;

use App\Models\SupportSession;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staff browsing an institution during an active, audited, read-only support session. */
final class ReadOnlySupportSession
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->route('supportSession');
        $session = $session instanceof SupportSession ? $session : SupportSession::query()->findOrFail($session);

        abort_unless($session->staff_id === $request->user()->id && $session->isActive(), 403, 'Support session expired.');
        abort_unless($request->isMethodSafe(), 405, 'Support sessions are read-only.');

        return $this->context->runAs($session->tenant_id, fn () => $next($request));
    }
}
