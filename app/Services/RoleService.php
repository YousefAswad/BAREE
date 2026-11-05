<?php

namespace App\Services;

use App\Authorization\PermissionResolver;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Institutions build their own roles from BAREE templates, within hard limits. */
final class RoleService
{
    public function __construct(private PermissionResolver $resolver) {}

    public function cloneTemplate(Role $template, int $tenantId, string $key, array $name): Role
    {
        if (! $template->is_template) {
            throw new DomainException('Only templates can be cloned.');
        }

        return DB::transaction(function () use ($template, $tenantId, $key, $name) {
            $role = Role::create([
                'tenant_id' => $tenantId, 'key' => $key, 'name' => $name,
                'level' => $template->level, 'cloned_from_id' => $template->id,
            ]);
            $role->permissions()->sync($template->permissions()->pluck('permissions.id'));
            Audit::log('role.cloned', $role, ['from' => $template->key]);

            return $role;
        });
    }

    /** @param  string[]  $permissionKeys */
    public function syncPermissions(Role $role, array $permissionKeys): void
    {
        $permissions = Permission::query()->whereIn('key', $permissionKeys)->get();

        if ($role->tenant_id !== null && $permissions->contains(fn ($p) => $p->scope === 'platform')) {
            throw new DomainException('Institution roles cannot hold platform permissions.');
        }

        if ($role->level === 'department' && $permissions->contains(fn ($p) => $p->scope === 'tenant')) {
            throw new DomainException('Department roles cannot hold institution-wide permissions.');
        }

        $role->permissions()->sync($permissions->pluck('id'));
        $this->resolver->flush();
        Audit::log('role.permissions_synced', $role, ['permissions' => $permissionKeys]);
    }

    public function assign(User $actor, User $user, Role $role, ?string $scopeType = null, ?int $scopeId = null): RoleAssignment
    {
        if ($role->level === 'platform' && ! $actor->is_platform_admin) {
            throw new DomainException('Only BAREE staff can grant platform roles.');
        }

        if ($role->tenant_id !== null && $role->tenant_id !== $user->tenant_id) {
            throw new DomainException('Role belongs to another institution.');
        }

        if ($role->level === 'department' && $scopeType === null) {
            throw new DomainException('Department roles need a department scope.');
        }

        $assignment = RoleAssignment::create([
            'user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $user->tenant_id,
            'scope_type' => $scopeType, 'scope_id' => $scopeId, 'granted_by' => $actor->id,
        ]);
        $this->resolver->flush();
        Audit::log('role.assigned', $user, ['role' => $role->key, 'scope' => [$scopeType, $scopeId]]);

        return $assignment;
    }
}
