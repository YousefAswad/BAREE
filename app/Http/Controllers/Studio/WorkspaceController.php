<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeFile;
use App\Models\Output;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Studio\WorkspaceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $shared = DB::table('workspace_members')->where('user_id', $user->id)->pluck('role', 'workspace_id');

        return Inertia::render('Workspaces/Index', [
            'workspaces' => Workspace::query()->whereNull('parent_id')->whereNull('archived_at')
                ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereIn('id', $shared->keys()))
                ->latest('updated_at')->get(['id', 'title', 'kind', 'owner_id', 'updated_at'])
                ->map(fn ($w) => $w->only(['id', 'title', 'kind', 'updated_at']) + ['role' => $w->owner_id === $user->id ? 'owner' : $shared[$w->id]]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('workspaces.manage_own');
        $data = $request->validate([
            'kind' => ['required', 'in:course,unit,lesson,project'],
            'title' => ['required', 'string', 'max:160'],
            'parent_id' => ['nullable', 'integer'],
            'instructions' => ['nullable', 'string', 'max:6000'],
        ]);

        try {
            $workspace = $this->workspaces->create($request->user(), $data);
        } catch (DomainException $e) {
            return back()->withErrors(['workspace' => $e->getMessage()]);
        }

        return to_route('workspaces.show', $workspace);
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);
        $root = $this->workspaces->root($workspace);
        $ids = $this->workspaces->subtreeIds($workspace);

        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace->only(['id', 'title', 'kind', 'instructions', 'memory', 'parent_id']),
            'role' => $this->workspaces->access($request->user(), $workspace),
            'children' => Workspace::query()->where('parent_id', $workspace->id)->orderBy('title')->get(['id', 'title', 'kind']),
            // Latest version of each item only.
            'outputs' => Output::query()->whereIn('workspace_id', $ids)->whereNull('archived_at')->orderByDesc('updated_at')->get()
                ->groupBy(fn ($o) => $o->rootId())->map->sortByDesc('version')->map->first()->values()
                ->map->only(['id', 'type', 'title', 'version', 'updated_at', 'workspace_id']),
            'files' => KnowledgeFile::query()->where('owner_type', $workspace->getMorphClass())->where('owner_id', $workspace->id)
                ->get(['id', 'original_name', 'mime', 'status', 'usage_mode', 'size']),
            'members' => DB::table('workspace_members')->join('users', 'users.id', '=', 'workspace_members.user_id')
                ->where('workspace_id', $root->id)->get(['users.public_id as id', 'users.name', 'workspace_members.role']),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);
        $workspace->update($request->validate(['title' => ['sometimes', 'string', 'max:160'], 'instructions' => ['sometimes', 'nullable', 'string', 'max:6000']]));

        return back();
    }

    public function memory(Request $request, Workspace $workspace): RedirectResponse
    {
        $facts = $request->validate(['memory' => ['present', 'array', 'max:50'], 'memory.*' => ['string', 'max:300']])['memory'];
        try {
            $this->workspaces->setMemory($request->user(), $workspace, $facts);
        } catch (DomainException $e) {
            abort(403);
        }

        return back();
    }

    public function share(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate(['user' => ['required', 'string'], 'role' => ['required', 'in:editor,viewer']]);
        $member = User::query()->where('tenant_id', $request->user()->tenant_id)->where('public_id', $data['user'])->firstOrFail();

        try {
            $this->workspaces->share($request->user(), $workspace, $member, $data['role']);
        } catch (DomainException $e) {
            return back()->withErrors(['share' => $e->getMessage()]);
        }

        return back();
    }
}
