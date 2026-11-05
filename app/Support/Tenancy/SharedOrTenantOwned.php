<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * For catalogue records that BAREE publishes globally (tenant_id = null) and institutions may extend.
 * Reads: global + own tenant. Writes by institutions are forced to their own tenant.
 */
trait SharedOrTenantOwned
{
    public static function bootSharedOrTenantOwned(): void
    {
        static::addGlobalScope('shared_or_tenant', function (Builder $builder) {
            $context = app(TenantContext::class);

            if ($context->inPlatformMode()) {
                return;
            }

            $column = $builder->getModel()->qualifyColumn('tenant_id');
            $builder->where(fn (Builder $q) => $q->whereNull($column)
                ->when($context->hasTenant(), fn (Builder $q) => $q->orWhere($column, $context->id())));
        });

        static::saving(function ($model) {
            $context = app(TenantContext::class);

            if ($context->inPlatformMode()) {
                return;
            }

            if ($context->hasTenant() && $model->tenant_id === null && ! $model->exists) {
                $model->tenant_id = $context->id(); // institutions always create institution-owned records
            }

            if (! $context->hasTenant() || (int) $model->tenant_id !== $context->id()) {
                throw new \RuntimeException('Only BAREE staff can write global catalogue records.');
            }
        });
    }

    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }
}
