<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** One id per request, in every log line and in the response, so support can trace an incident end to end. */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) ? $incoming : (string) Str::ulid();

        Log::withContext(['request_id' => $id, 'tenant_id' => fn () => $request->user()?->tenant_id, 'user_id' => fn () => $request->user()?->id]);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
