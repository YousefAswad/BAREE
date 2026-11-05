<?php

namespace App\Http\Controllers\Institution;

use App\Authorization\PermissionResolver;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\Institution\AnalyticsService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $this->authorize('users.view');
        $viewer = $request->user();

        $users = User::query()->where('tenant_id', $viewer->tenant_id)
            ->with(['department:id,name', 'jobTitle:id,name'])
            ->withExists(['passport as assessed'])
            ->when($request->integer('department'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.addcslashes($s, '%_').'%')->orWhere('email', 'like', '%'.addcslashes($s, '%_').'%')))
            ->orderBy('name')->paginate(30)->withQueryString();

        return Inertia::render('Institution/Users/Index', [
            'users' => $users->through(fn (User $u) => [
                'id' => $u->public_id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
                'department' => $u->department?->name, 'job_title' => $u->jobTitle?->name, 'assessed' => (bool) $u->assessed,
                'last_active_at' => $u->last_active_at,
                'level' => $analytics->individualLevel($viewer, $u), // null unless policy + consent allow it
            ]),
            'pendingInvitations' => Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->latest()->limit(50)->get(['id', 'email', 'role_key', 'expires_at']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'jobTitles' => JobTitle::query()->orderBy('name')->get(['id', 'name', 'role_template_key']),
            'filters' => $request->only(['q', 'department', 'status']),
        ]);
    }

    public function update(Request $request, string $publicId): RedirectResponse
    {
        $user = $this->findInTenant($request, $publicId);
        $this->authorize('users.manage');

        $data = $request->validate([
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'job_title_id' => ['nullable', Rule::exists('job_titles', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'status' => ['sometimes', 'in:active,disabled'],
        ]);

        abort_if(($data['status'] ?? null) === 'disabled' && $user->id === $request->user()->id, 422, __('You cannot disable yourself.'));

        $user->update($data);
        app(PermissionResolver::class)->flush($user);
        Audit::log('user.updated', $user, array_keys($data));

        return back();
    }

    private function findInTenant(Request $request, string $publicId): User
    {
        return User::query()->where('tenant_id', $request->user()->tenant_id)->where('public_id', $publicId)->firstOrFail();
    }
}
