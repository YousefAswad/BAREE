<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);

            if ($model->tenant_id === null) {
                if (! $context->hasTenant()) {
                    throw new RuntimeException('Refusing to create '.static::class.' without a tenant context.');
                }
                $model->tenant_id = $context->id();
            }

            if ($context->hasTenant() && (int) $model->tenant_id !== $context->id()) {
                throw new RuntimeException('Cross-tenant write blocked on '.static::class.'.');
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('tenant_id')) {
                throw new RuntimeException('tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
