<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\RoleService;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(Request $request): Response
    {
        $this->authorize('roles.manage');
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Institution/Roles/Index', [
            'roles' => Role::query()->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhere(fn ($q) => $q->whereNull('tenant_id')->where('is_template', true)))
                ->with('permissions:id,key')->orderBy('level')->get()
                ->map(fn (Role $r) => ['id' => $r->id, 'key' => $r->key, 'name' => $r->name, 'level' => $r->level, 'template' => $r->tenant_id === null, 'permissions' => $r->permissions->pluck('key')]),
            // Institutions only ever see permissions they are allowed to grant.
            'permissions' => Permission::query()->where('scope', '!=', 'platform')->orderBy('group')->get(['key', 'group', 'scope', 'label']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function clone(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');
        $data = $request->validate(['key' => ['required', 'regex:/^[a-z][a-z0-9_]{2,40}$/'], 'name' => ['required', 'string', 'max:80']]);

        return $this->attempt(fn () => $this->roles->cloneTemplate($role, $request->user()->tenant_id, $data['key'], ['ar' => $data['name'], 'en' => $data['name']]));
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');
        abort_unless($role->tenant_id === $request->user()->tenant_id, 404); // templates are read-only for institutions
        $data = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'exists:permissions,key']]);

        return $this->attempt(fn () => $this->roles->syncPermissions($role, $data['permissions']));
    }

    public function assign(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');
        abort_unless($role->tenant_id === null || $role->tenant_id === $request->user()->tenant_id, 404);
        $data = $request->validate(['user' => ['required', 'string'], 'department_id' => ['nullable', 'integer']]);

        $user = User::query()->where('tenant_id', $request->user()->tenant_id)->where('public_id', $data['user'])->firstOrFail();
        $department = isset($data['department_id']) ? Department::query()->findOrFail($data['department_id']) : null;

        return $this->attempt(fn () => $this->roles->assign($request->user(), $user, $role, $department ? 'department' : null, $department?->id));
    }

    public function unassign(Request $request, RoleAssignment $assignment): RedirectResponse
    {
        $this->authorize('roles.manage');
        abort_unless($assignment->tenant_id === $request->user()->tenant_id, 404);
        abort_if($assignment->user_id === $request->user()->id, 422, __('You cannot remove your own roles.'));

        $assignment->delete();
        Audit::log('role.unassigned', $assignment);

        return back();
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back();
    }
}
