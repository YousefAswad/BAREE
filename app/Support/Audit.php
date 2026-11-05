<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

final class Audit
{
    public static function log(string $action, ?Model $target = null, array $meta = []): void
    {
        $request = request();

        AuditLog::create([
            'tenant_id' => app(TenantContext::class)->id() ?? ($target?->getAttribute('tenant_id')),
            'actor_id' => auth()->id(),
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            // Store a keyed hash, not the raw IP: enough for investigations, minimal personal data.
            'ip_hash' => $request?->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
