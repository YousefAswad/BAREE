<?php

namespace App\Support\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "which institution is this request acting for".
 * Fail-closed: tenant-owned models return nothing when no tenant is set and platform mode is off.
 */
final class TenantContext
{
    private ?int $tenantId = null;

    private bool $platformMode = false;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->platformMode = false;
        $this->syncDatabase();
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function inPlatformMode(): bool
    {
        return $this->platformMode;
    }

    /** Run a callback as a specific tenant, restoring the previous context afterwards. */
    public function runAs(int $tenantId, Closure $callback): mixed
    {
        [$previousTenant, $previousMode] = [$this->tenantId, $this->platformMode];
        $this->set($tenantId);

        $failed = false;

        try {
            return $callback();
        } catch (\Throwable $e) {
            $failed = true;

            throw $e;
        } finally {
            $this->tenantId = $previousTenant;
            $this->platformMode = $previousMode;
            $this->restoreDatabase($failed);
        }
    }

    /** Cross-tenant access for BAREE staff tooling only. Every call should be audited by the caller. */
    public function asPlatform(Closure $callback): mixed
    {
        [$previousTenant, $previousMode] = [$this->tenantId, $this->platformMode];
        $this->tenantId = null;
        $this->platformMode = true;
        $this->syncDatabase();

        $failed = false;

        try {
            return $callback();
        } catch (\Throwable $e) {
            $failed = true;

            throw $e;
        } finally {
            $this->tenantId = $previousTenant;
            $this->platformMode = $previousMode;
            $this->restoreDatabase($failed);
        }
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->platformMode = false;
        $this->syncDatabase();
    }

    /**
     * Re-sync the DB session after a callback. If the callback already threw (for example a failed SQL statement that left the
     * transaction aborted), a failure here must not replace that original exception: report it and let the original propagate.
     */
    private function restoreDatabase(bool $callbackFailed): void
    {
        try {
            $this->syncDatabase();
        } catch (\Throwable $e) {
            if (! $callbackFailed) {
                throw $e;
            }
            report($e);
        }
    }

    /** Mirror the context into PostgreSQL so row-level security enforces it too. */
    private function syncDatabase(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::select("select set_config('app.tenant_id', ?, false)", [(string) ($this->tenantId ?? '')]);
        DB::select("select set_config('app.bypass_rls', ?, false)", [$this->platformMode ? 'on' : 'off']);
    }
}
