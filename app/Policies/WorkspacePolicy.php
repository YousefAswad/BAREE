<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Studio\WorkspaceService;

final class WorkspacePolicy
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->workspaces->access($user, $workspace) !== null;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($this->workspaces->access($user, $workspace), ['owner', 'editor'], true);
    }

    public function share(User $user, Workspace $workspace): bool
    {
        return $this->workspaces->access($user, $workspace) === 'owner';
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->workspaces->access($user, $workspace) === 'owner';
    }
}
