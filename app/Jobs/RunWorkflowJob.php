<?php

namespace App\Jobs;

use App\Events\AgentRunFinished;
use App\Models\WorkflowRun;
use App\Services\Studio\WorkflowRunner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunWorkflowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // never re-run paid steps automatically

    public int $timeout = 900;

    public function __construct(public int $workflowRunId, public int $tenantId)
    {
        $this->onQueue('ai');
    }

    public function handle(TenantContext $context, WorkflowRunner $runner): void
    {
        $context->runAs($this->tenantId, function () use ($runner) {
            $run = $runner->execute(WorkflowRun::query()->findOrFail($this->workflowRunId));
            AgentRunFinished::dispatch($run->user_id, $run->public_id, $run->status);
        });
    }
}
