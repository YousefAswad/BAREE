<?php

namespace App\Http\Controllers\Studio;

use App\AI\Exceptions\AiPolicyViolation;
use App\AI\Gateway\AiGateway;
use App\Http\Controllers\Controller;
use App\Models\Export;
use App\Models\Output;
use App\Services\Studio\OutputService;
use App\Services\Studio\ScenarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OutputController extends Controller
{
    public function __construct(private OutputService $outputs) {}

    public function show(Request $request, Output $output, ScenarioService $scenarios): Response
    {
        $this->outputs->assertCanView($output, $request->user());

        if ($output->type === 'scenario') {
            return Inertia::render('Scenario/Editor', [
                'scenario' => $output->only(['id', 'title', 'version']) + ['context' => $output->content['context'] ?? []],
                'scenes' => $scenarios->present($output),
                'sceneTypes' => ScenarioService::SCENE_TYPES,
                'credits' => config('media.credits'),
            ]);
        }

        return Inertia::render('Outputs/Show', [
            'output' => $output->only(['id', 'title', 'type', 'version', 'workspace_id', 'metadata']) + ['body' => $output->content['body'] ?? ''],
            'versions' => $this->outputs->versions($output),
            'exports' => Export::query()->where('output_id', $output->id)->where('user_id', $request->user()->id)->latest()->limit(5)
                ->get(['public_id', 'format', 'status', 'expires_at']),
            'canEdit' => $request->user()->can('update', $output->workspace),
        ]);
    }

    public function update(Request $request, Output $output): RedirectResponse
    {
        $body = $request->validate(['body' => ['required', 'string', 'max:200000']])['body'];
        $next = $this->outputs->newVersion($output, $request->user(), $body);

        return to_route('outputs.show', $next);
    }

    /** "Make it shorter", "add a group activity"... runs the refine agent on the current text. */
    public function refine(Request $request, Output $output, AiGateway $gateway): RedirectResponse
    {
        $this->outputs->assertCanEdit($output, $request->user());
        $instruction = $request->validate(['instruction' => ['required', 'string', 'max:1000']])['instruction'];

        try {
            $run = $gateway->run($request->user(), 'refine_output', ['instruction' => $instruction, 'document' => mb_substr($output->content['body'] ?? '', 0, 60000)], $output->workspace);
        } catch (AiPolicyViolation $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        $next = $this->outputs->newVersion($output, $request->user(), $gateway->presentOutput($run, $request->user()), $run);

        return to_route('outputs.show', $next);
    }

    public function archive(Request $request, Output $output): RedirectResponse
    {
        $this->outputs->assertCanEdit($output, $request->user());
        $output->update(['archived_at' => now()]);

        return to_route('workspaces.show', $output->workspace_id);
    }
}
