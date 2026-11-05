<?php

namespace App\Services\Studio;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Courses → units → lessons, or standalone projects. Sharing is set on the root and inherited by children.
 * Access: owner > editor > viewer. Other colleagues in the same institution see nothing.
 */
final class WorkspaceService
{
    private const CHILD_KINDS = ['course' => 'unit', 'unit' => 'lesson'];

    public const MAX_MEMORY = 50;

    public function create(User $user, array $data): Workspace
    {
        $parent = isset($data['parent_id']) ? Workspace::query()->findOrFail($data['parent_id']) : null;

        if ($parent) {
            if (! in_array($this->access($user, $parent), ['owner', 'editor'], true)) {
                throw new DomainException(__('You cannot add items here.'));
            }
            if ((self::CHILD_KINDS[$parent->kind] ?? null) !== $data['kind']) {
                throw new DomainException(__('A :parent can only contain :child items.', ['parent' => $parent->kind, 'child' => self::CHILD_KINDS[$parent->kind] ?? '—']));
            }
        } elseif (! in_array($data['kind'], ['course', 'project'], true)) {
            throw new DomainException(__('Top-level items must be a course or a project.'));
        }

        $workspace = Workspace::create([
            'owner_id' => $parent?->owner_id ?? $user->id, // children belong to the root owner
            'parent_id' => $parent?->id,
            'kind' => $data['kind'],
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
        ]);
        Audit::log('workspace.created', $workspace, ['kind' => $workspace->kind]);

        return $workspace;
    }

    public function access(User $user, Workspace $workspace): ?string
    {
        if ($workspace->tenant_id !== $user->tenant_id) {
            return null;
        }

        $root = $this->root($workspace);
        if ($root->owner_id === $user->id) {
            return 'owner';
        }

        return DB::table('workspace_members')->where('workspace_id', $root->id)->where('user_id', $user->id)->value('role');
    }

    public function root(Workspace $workspace): Workspace
    {
        $current = $workspace;
        for ($depth = 0; $current->parent_id && $depth < 5; $depth++) {
            $current = Workspace::query()->findOrFail($current->parent_id);
        }

        return $current;
    }

    public function share(User $actor, Workspace $workspace, User $member, string $role): void
    {
        $root = $this->root($workspace);
        if ($this->access($actor, $root) !== 'owner') {
            throw new DomainException(__('Only the owner can share.'));
        }
        if ($member->tenant_id !== $actor->tenant_id || $member->id === $root->owner_id) {
            throw new DomainException(__('You can only share with colleagues in your institution.'));
        }

        DB::table('workspace_members')->updateOrInsert(['workspace_id' => $root->id, 'user_id' => $member->id], ['role' => $role === 'editor' ? 'editor' : 'viewer']);
        Audit::log('workspace.shared', $root, ['member' => $member->id, 'role' => $role]);
    }

    public function unshare(User $actor, Workspace $workspace, User $member): void
    {
        $root = $this->root($workspace);
        if ($this->access($actor, $root) !== 'owner') {
            throw new DomainException(__('Only the owner can change sharing.'));
        }
        DB::table('workspace_members')->where('workspace_id', $root->id)->where('user_id', $member->id)->delete();
    }

    /** Project memory: short facts the user can read, edit and delete at any time. */
    public function setMemory(User $actor, Workspace $workspace, array $facts): void
    {
        if (! in_array($this->access($actor, $workspace), ['owner', 'editor'], true)) {
            throw new DomainException(__('Not allowed.'));
        }

        $clean = collect($facts)->map(fn ($f) => trim(mb_substr((string) $f, 0, 300)))->filter()->unique()->take(self::MAX_MEMORY)->values()->all();
        $workspace->update(['memory' => $clean]);
    }

    /** All ids in the subtree (for listing outputs of a course). */
    public function subtreeIds(Workspace $workspace): array
    {
        $ids = [$workspace->id];
        $frontier = [$workspace->id];
        for ($depth = 0; $frontier && $depth < 5; $depth++) {
            $frontier = Workspace::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
