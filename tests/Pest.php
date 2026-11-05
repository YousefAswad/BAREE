<?php

use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
// Row-level security runs against an already-migrated database as the non-owner role (see CI), inside transactions.
pest()->extend(TestCase::class)->use(\Illuminate\Foundation\Testing\DatabaseTransactions::class)->group('rls')->in('Rls');

function bareeTenantContext(): TenantContext
{
    return app(TenantContext::class);
}

function seedRoles(): void
{
    bareeTenantContext()->asPlatform(function () {
        (new PermissionSeeder)->run();
        (new RoleTemplateSeeder)->run();
    });
}

function userWithRole(string $roleKey, ?Tenant $tenant = null, ?string $scopeType = null, ?int $scopeId = null): User
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $role = Role::query()->whereNull('tenant_id')->where('key', $roleKey)->firstOrFail();
    RoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id, 'scope_type' => $scopeType, 'scope_id' => $scopeId]);

    return $user;
}
