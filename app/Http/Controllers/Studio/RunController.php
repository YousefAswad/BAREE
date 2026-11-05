<?php

namespace App\Http\Controllers\Studio;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use App\Http\Controllers\Controller;
use App\Jobs\RunAgentJob;
use App\Models\AiRun;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use App\Services\Studio\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class RunController extends Controller
{
    public function store(Request $request, string $key, AiGateway $gateway, WorkflowRunner $workflows): JsonResponse
    {
        $this->authorize('ai.agents.use');
        $data = $request->validate([
            'inputs' => ['required', 'array', 'max:40'],
            'workspace_id' => ['nullable', 'integer'],
            'task_instructions' => ['nullable', 'string', 'max:4000'],
            'file_ids' => ['nullable', 'array', 'max:10'], 'file_ids.*' => ['integer'],
        ]);
        $user = $request->user();
        $workspace = isset($data['workspace_id']) ? Workspace::query()->findOrFail($data['workspace_id']) : null;
        [, $version] = $gateway->resolve($user, $key);

        if (! empty($version->workflow)) {
            $run = $workflows->start($user, $key, $data['inputs'], $workspace, $data['task_instructions'] ?? null, $data['file_ids'] ?? []);

            return response()->json(['kind' => 'workflow', 'id' => $run->public_id, 'url' => route('studio.workflows.show', $run)], 202);
        }

        $gateway->validateInputs($version->input_schema, $data['inputs']); // reject bad input now, not in the queue
        if ($workspace) {
            $this->authorize('update', $workspace);
        }
        $streamId = (string) Str::ulid();
        RunAgentJob::dispatch($user->id, $key, $data['inputs'], $workspace?->id, $data['task_instructions'] ?? null, $data['file_ids'] ?? [], $streamId);

        return response()->json(['kind' => 'single', 'stream' => $streamId], 202);
    }

    public function showRun(Request $request, AiRun $run, AiGateway $gateway): JsonResponse
    {
        abort_unless($run->user_id === $request->user()->id, 404);

        return response()->json([
            'id' => $run->public_id, 'status' => $run->status, 'credits' => $run->credits, 'redactions' => $run->redactions_count,
            'output' => $run->status === 'succeeded' ? $gateway->presentOutput($run, $request->user()) : null,
        ]);
    }

    public function showWorkflow(Request $request, WorkflowRun $workflow): Response
    {
        abort_unless($workflow->user_id === $request->user()->id, 404);
        $version = $workflow->version;
        $titles = collect($version->workflow)->pluck('title', 'key');

        return Inertia::render('Studio/Run', [
            'workflow' => ['id' => $workflow->public_id, 'status' => $workflow->status, 'workspace_id' => $workflow->workspace_id],
            'steps' => collect($workflow->steps)->map(fn ($s) => $s + ['title' => $titles[$s['key']] ?? $s['key'],
                'output' => ($s['status'] === 'succeeded' && $s['run_id'])
                    ? app(AiGateway::class)->presentOutput(AiRun::query()->findOrFail($s['run_id']), $request->user()) : null]),
        ]);
    }

    public function regenerate(Request $request, WorkflowRun $workflow, string $step, WorkflowRunner $runner)
    {
        $data = $request->validate(['instruction' => ['nullable', 'string', 'max:1000']]);

        try {
            $runner->regenerate($workflow, $step, $data['instruction'] ?? null, $request->user());
        } catch (AiPolicyViolation $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back();
    }
}
