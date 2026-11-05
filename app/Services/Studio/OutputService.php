<?php

namespace App\Services\Studio;

use App\AI\Gateway\AiGateway;
use App\Models\AiRun;
use App\Models\Output;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Audit;
use DomainException;

/**
 * Outputs are the user's documents. They are stored with real names restored, inside the institution
 * (tenant-scoped + RLS). Any later AI call on an output goes back through the gateway and is redacted again.
 */
final class OutputService
{
    public function __construct(private AiGateway $gateway, private WorkspaceService $workspaces) {}

    public function saveFromRun(AiRun $run, User $user, Workspace $workspace, string $type, string $title): Output
    {
        if (! in_array($this->workspaces->access($user, $workspace), ['owner', 'editor'], true)) {
            throw new DomainException(__('You cannot save into this workspace.'));
        }

        $output = Output::create([
            'workspace_id' => $workspace->id,
            'ai_run_id' => $run->id,
            'type' => $type,
            'title' => mb_substr($title, 0, 190),
            'content' => ['format' => 'markdown', 'body' => $this->gateway->presentOutput($run, $user)],
            'version' => 1,
            'created_by' => $user->id,
            'metadata' => ['ai_generated' => true],
        ]);
        Audit::log('output.created', $output, ['type' => $type]);

        return $output;
    }

    /** Every edit or regeneration creates a new version; old versions stay readable. */
    public function newVersion(Output $output, User $user, string $markdown, ?AiRun $run = null): Output
    {
        $this->assertCanEdit($output, $user);
        $latest = Output::query()->where(fn ($q) => $q->where('id', $output->rootId())->orWhere('metadata->root_id', $output->rootId()))
            ->orderByDesc('version')->first();

        $next = Output::create([
            'workspace_id' => $output->workspace_id,
            'ai_run_id' => $run?->id ?? $output->ai_run_id,
            'parent_id' => $latest->id,
            'type' => $output->type,
            'title' => $output->title,
            'content' => ['format' => 'markdown', 'body' => $markdown],
            'version' => $latest->version + 1,
            'created_by' => $user->id,
            'metadata' => ['root_id' => $output->rootId(), 'ai_generated' => $run !== null || ($output->metadata['ai_generated'] ?? false)],
        ]);
        Audit::log('output.versioned', $next, ['version' => $next->version]);

        if ($run === null && ($output->metadata['ai_generated'] ?? false)) {
            app(\App\Services\Learning\FeedbackService::class)->recordEdit($user, $latest->ai_run_id, (string) ($latest->content['body'] ?? ''), $markdown);
        }

        return $next;
    }

    public function versions(Output $output)
    {
        return Output::query()->where(fn ($q) => $q->where('id', $output->rootId())->orWhere('metadata->root_id', $output->rootId()))
            ->orderBy('version')->get(['id', 'version', 'created_by', 'created_at', 'ai_run_id']);
    }

    public function assertCanView(Output $output, User $user): void
    {
        abort_if($this->workspaces->access($user, $output->workspace) === null, 404);
    }

    public function assertCanEdit(Output $output, User $user): void
    {
        abort_unless(in_array($this->workspaces->access($user, $output->workspace), ['owner', 'editor'], true), 403);
    }
}
