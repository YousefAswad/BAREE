<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Services\Platform\AgentEvaluationService;
use App\Services\Platform\AgentVersionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AgentVersionController extends Controller
{
    public function __construct(private AgentVersionService $versions) {}

    public function storeDraft(Request $request, string $key): RedirectResponse
    {
        $this->authorize('platform.agents.manage');

        return $this->attempt(fn () => $this->versions->createDraft(Agent::query()->whereNull('tenant_id')->where('key', $key)->firstOrFail(), $request->user()));
    }

    public function update(Request $request, AgentVersion $version): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $data = $request->validate([
            'system_prompt' => ['sometimes', 'string', 'max:60000'],
            'prompt_block_keys' => ['sometimes', 'array'], 'prompt_block_keys.*' => ['string', 'exists:prompt_blocks,key'],
            'input_schema' => ['sometimes', 'array'], 'input_schema.fields' => ['array', 'max:30'],
            'routing' => ['sometimes', 'array'], 'routing.providers' => ['array', 'min:1'], 'routing.tier' => ['in:light,standard,advanced'],
            'routing.max_tokens' => ['integer', 'between:256,32000'],
            'customization_policy' => ['sometimes', 'array'],
            'credits_cost' => ['sometimes', 'integer', 'between:1,500'],
            'release_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);

        return $this->attempt(fn () => $this->versions->updateDraft($version, $data));
    }

    public function submit(AgentVersion $version): RedirectResponse
    {
        $this->authorize('platform.agents.manage');

        return $this->attempt(fn () => $this->versions->submitForTesting($version));
    }

    public function runTests(AgentVersion $version, AgentEvaluationService $evaluation): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $summary = $evaluation->run($version);

        return back()->with('status', __(':p passed, :f failed, :e errors', ['p' => $summary['passed'], 'f' => $summary['failed'], 'e' => $summary['errors']]));
    }

    public function rollout(Request $request, AgentVersion $version): RedirectResponse
    {
        $this->authorize('platform.agents.publish');
        $data = $request->validate([
            'mode' => ['required', 'in:pilot,percent,all'],
            'tenant_ids' => ['required_if:mode,pilot', 'array'], 'tenant_ids.*' => ['integer', 'exists:tenants,id'],
            'percent' => ['required_if:mode,percent', 'integer', 'between:1,99'],
        ]);

        return $this->attempt(fn () => $this->versions->startRollout($version, $data));
    }

    public function promote(AgentVersion $version): RedirectResponse
    {
        $this->authorize('platform.agents.publish');

        return $this->attempt(fn () => $this->versions->promote($version));
    }

    public function rollback(AgentVersion $version): RedirectResponse
    {
        $this->authorize('platform.agents.publish');

        return $this->attempt(fn () => $this->versions->rollback(Agent::query()->findOrFail($version->agent_id), $version));
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back();
    }
}
