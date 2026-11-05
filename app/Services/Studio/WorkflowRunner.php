<?php

namespace App\Services\Studio;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use App\Events\AgentRunDelta;
use App\Jobs\RunWorkflowJob;
use App\Models\Agent;
use App\Models\AiRun;
use App\Models\Output;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use App\Support\Audit;
use Throwable;

/**
 * Multi-step generation (e.g. lesson pack: plan → slides → activity → worksheet → quiz → homework).
 * Each step sees the earlier components, so the pack stays coherent. A failing step stops the chain
 * (later steps depend on it) and the run ends as "partial"; any single step can be regenerated later.
 */
final class WorkflowRunner
{
    public function __construct(private AiGateway $gateway, private OutputService $outputs, private WorkspaceService $workspaces) {}

    public function start(User $user, string $agentKey, array $inputs, ?Workspace $workspace, ?string $taskInstructions, array $fileIds): WorkflowRun
    {
        [$agent, $version] = $this->gateway->resolve($user, $agentKey);
        if (empty($version->workflow)) {
            throw new AiPolicyViolation('This tool runs as a single step.');
        }
        if ($workspace && ! in_array($this->workspaces->access($user, $workspace), ['owner', 'editor'], true)) {
            throw new AiPolicyViolation('You cannot save into this workspace.');
        }
        $this->gateway->validateInputs($version->input_schema, $inputs); // fail fast, before queueing

        $run = WorkflowRun::create([
            'user_id' => $user->id, 'agent_version_id' => $version->id, 'workspace_id' => $workspace?->id,
            'status' => 'queued', 'inputs' => $inputs, 'task_instructions' => $taskInstructions, 'file_ids' => $fileIds,
            'steps' => collect($version->workflow)->map(fn ($s) => ['key' => $s['key'], 'status' => 'pending', 'run_id' => null, 'output_id' => null])->all(),
        ]);
        RunWorkflowJob::dispatch($run->id, $user->tenant_id);

        return $run;
    }

    public function execute(WorkflowRun $run, bool $broadcast = true): WorkflowRun
    {
        $run->update(['status' => 'running']);
        $version = $run->version;
        $agent = Agent::query()->findOrFail($version->agent_id);
        $user = $run->user;
        $workspace = $run->workspace_id ? Workspace::query()->find($run->workspace_id) : null;
        $steps = $run->steps;
        $definitions = collect($version->workflow)->keyBy('key');
        $failed = false;

        foreach ($steps as $i => $state) {
            if ($state['status'] === 'succeeded') {
                continue;
            }
            if ($failed) {
                $steps[$i]['status'] = 'skipped';

                continue;
            }

            try {
                $aiRun = $this->runOne($run, $agent, $version, $definitions[$state['key']], $user, $workspace, $steps, $broadcast);
                $steps[$i]['status'] = 'succeeded';
                $steps[$i]['run_id'] = $aiRun->id;
                if ($workspace) {
                    $steps[$i]['output_id'] = $this->outputs->saveFromRun($aiRun, $user, $workspace, $definitions[$state['key']]['output_type'] ?? 'document', $this->titleFor($definitions[$state['key']], $run))->id;
                }
            } catch (AiPolicyViolation $e) {
                $steps[$i]['status'] = 'failed';
                $steps[$i]['error'] = $e->getMessage();
                $failed = true;
            } catch (Throwable $e) {
                report($e);
                $steps[$i]['status'] = 'failed';
                $steps[$i]['error'] = 'provider_error';
                $failed = true;
            }
            $run->update(['steps' => $steps]);
        }

        $succeeded = collect($steps)->where('status', 'succeeded')->count();
        $run->update(['status' => $succeeded === count($steps) ? 'succeeded' : ($succeeded > 0 ? 'partial' : 'failed'), 'steps' => $steps]);
        Audit::log('workflow.finished', $run, ['status' => $run->status]);

        return $run;
    }

    /** Regenerate one component with an optional extra instruction; creates a new version of its output. */
    public function regenerate(WorkflowRun $run, string $stepKey, ?string $instruction, User $user): AiRun
    {
        abort_unless($run->user_id === $user->id, 403);
        $version = $run->version;
        $definition = collect($version->workflow)->firstWhere('key', $stepKey) ?? abort(404);
        $agent = Agent::query()->findOrFail($version->agent_id);
        $workspace = $run->workspace_id ? Workspace::query()->find($run->workspace_id) : null;

        $aiRun = $this->runOne($run, $agent, $version, $definition, $user, $workspace, $run->steps, true, $instruction);

        $steps = $run->steps;
        foreach ($steps as $i => $s) {
            if ($s['key'] === $stepKey) {
                $steps[$i]['run_id'] = $aiRun->id;
                $steps[$i]['status'] = 'succeeded';
                if ($s['output_id'] && $output = Output::query()->find($s['output_id'])) {
                    $steps[$i]['output_id'] = $this->outputs->newVersion($output, $user, $this->gateway->presentOutput($aiRun, $user), $aiRun)->id;
                }
            }
        }
        $run->update(['steps' => $steps]);

        return $aiRun;
    }

    private function runOne(WorkflowRun $run, Agent $agent, $version, array $definition, User $user, ?Workspace $workspace, array $steps, bool $broadcast, ?string $extra = null): AiRun
    {
        // Earlier components are passed as task context so later steps stay consistent with them.
        $previous = collect($steps)->filter(fn ($s) => $s['status'] === 'succeeded' && $s['key'] !== $definition['key'] && $s['run_id'])
            ->map(fn ($s) => '### '.$s['key']."\n".mb_substr($this->gateway->presentOutput(AiRun::query()->findOrFail($s['run_id']), $user), 0, 12000))
            ->implode("\n\n");

        $task = trim(implode("\n\n", array_filter([$run->task_instructions, $previous ? "Components already produced:\n".$previous : null, $extra ? 'Change requested: '.$extra : null])));
        $buffer = '';
        $last = microtime(true);
        $onDelta = $broadcast ? function (string $piece) use ($user, $run, $definition, &$buffer, &$last) {
            $buffer .= $piece;
            if (microtime(true) - $last >= 0.25) { // ~4 messages per second is smooth and cheap
                AgentRunDelta::dispatch($user->id, $run->public_id, $buffer, $definition['key']);
                $buffer = '';
                $last = microtime(true);
            }
        } : null;

        $aiRun = $this->gateway->runStep($user, $agent, $version, $definition, $run->inputs, $workspace, $task, $run->file_ids ?? [], $run->id, $onDelta);
        if ($broadcast && $buffer !== '') {
            AgentRunDelta::dispatch($user->id, $run->public_id, $buffer, $definition['key']);
        }

        return $aiRun;
    }

    private function titleFor(array $definition, WorkflowRun $run): string
    {
        return ($definition['title']['ar'] ?? $definition['key']).': '.($run->inputs['topic'] ?? '');
    }
}
