<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\RoleService;
use Illuminate\Support\Facades\Gate;

beforeEach(fn () => seedRoles());

it('lets institution admins invite users but not regular staff', function () {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('institution_admin', $tenant);
    $staff = userWithRole('staff', $tenant);

    expect(Gate::forUser($admin)->allows('users.invite'))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('users.invite'))->toBeFalse()
        ->and(Gate::forUser($staff)->allows('ai.agents.use'))->toBeTrue();
});

it('scopes department heads to their own department', function () {
    $tenant = Tenant::factory()->create();
    [$math, $science] = bareeTenantContext()->runAs($tenant->id, fn () => [
        Department::create(['name' => 'Math']), Department::create(['name' => 'Science']),
    ]);
    $head = userWithRole('department_head', $tenant, 'department', $math->id);

    expect($head->hasPermission('department.analytics.view', $math))->toBeTrue()
        ->and($head->hasPermission('department.analytics.view', $science))->toBeFalse()
        ->and($head->hasPermission('users.invite'))->toBeFalse();
});

it('never lets an institution role hold platform permissions', function () {
    $tenant = Tenant::factory()->create();
    $template = Role::query()->whereNull('tenant_id')->where('key', 'staff')->first();
    $role = bareeTenantContext()->runAs($tenant->id, fn () => app(RoleService::class)->cloneTemplate($template, $tenant->id, 'custom_staff', ['ar' => 'موظف مخصّص']));

    expect(fn () => app(RoleService::class)->syncPermissions($role, ['platform.tenants.manage']))
        ->toThrow(DomainException::class);
});

it('ignores role assignments that belong to another institution', function () {
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];
    $user = userWithRole('staff', $a);
    $adminRole = Role::query()->whereNull('tenant_id')->where('key', 'institution_admin')->first();
    \App\Models\RoleAssignment::create(['user_id' => $user->id, 'role_id' => $adminRole->id, 'tenant_id' => $b->id]);

    expect($user->hasPermission('users.invite'))->toBeFalse();
});
