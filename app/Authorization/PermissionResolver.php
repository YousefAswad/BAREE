<?php

namespace App\Authorization;

use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves dynamic, scoped permissions:
 *  - platform roles (BAREE staff), tenant-wide roles, and department/campus-scoped roles.
 * Results are memoised per request.
 */
final class PermissionResolver
{
    /** @var array<int, Collection> */
    private array $cache = [];

    public function allows(User $user, string $permission, ?Model $target = null): bool
    {
        foreach ($this->grants($user) as $grant) {
            if (! in_array($permission, $grant['permissions'], true)) {
                continue;
            }

            if ($grant['scope'] === 'platform') {
                return $user->is_platform_admin;
            }

            if ($grant['tenant_id'] !== $user->tenant_id) {
                continue;
            }

            if ($grant['scope_type'] === null) {
                return true; // tenant-wide grant
            }

            if ($target !== null && $this->targetInScope($target, $grant['scope_type'], $grant['scope_id'])) {
                return true;
            }

            if ($target === null && $grant['scope_type'] !== null) {
                // A scoped grant can open a screen, but every record-level check must pass a target.
                return true;
            }
        }

        return false;
    }

    /** All permission keys a user holds anywhere (for UI menus). */
    public function keys(User $user): array
    {
        return $this->grants($user)->pluck('permissions')->flatten()->unique()->values()->all();
    }

    public function flush(?User $user = null): void
    {
        if ($user !== null) {
            unset($this->cache[$user->id]);

            return;
        }

        $this->cache = [];
    }

    private function grants(User $user): Collection
    {
        return $this->cache[$user->id] ??= RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('role.permissions:id,key')
            ->get()
            ->map(fn (RoleAssignment $a) => [
                'tenant_id' => $a->tenant_id,
                'scope' => $a->role->level,
                'scope_type' => $a->scope_type,
                'scope_id' => $a->scope_id,
                'permissions' => $a->role->permissions->pluck('key')->all(),
            ]);
    }

    private function targetInScope(Model $target, string $scopeType, ?int $scopeId): bool
    {
        $column = $scopeType === 'department' ? 'department_id' : 'campus_id';

        if ($target->getTable() === ($scopeType === 'department' ? 'departments' : 'campuses')) {
            return (int) $target->getKey() === $scopeId;
        }

        return isset($target->{$column}) && (int) $target->{$column} === $scopeId;
    }
}
