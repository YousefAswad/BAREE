<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTestCase;
use App\Models\AgentTestRun;
use App\Models\AiProvider;
use App\Models\PromptBlock;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AgentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('platform.agents.manage');

        return Inertia::render('Platform/Agents/Index', [
            'agents' => Agent::query()->whereNull('tenant_id')->with('publishedVersion:id,version,credits_cost,routing')->orderBy('key')->get()
                ->map(fn (Agent $a) => [
                    'key' => $a->key, 'name' => $a->name, 'category' => $a->category, 'status' => $a->status,
                    'version' => $a->publishedVersion?->version, 'credits' => $a->publishedVersion?->credits_cost,
                    'providers' => $a->publishedVersion?->routing['providers'] ?? [],
                    'rolling_out' => $a->versions()->where('status', 'rolling_out')->value('version'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $data = $request->validate([
            'key' => ['required', 'regex:/^[a-z][a-z0-9_]{2,40}$/', 'unique:agents,key'],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:32'],
            'role_templates' => ['nullable', 'array'],
        ]);

        $agent = Agent::create([
            'tenant_id' => null, 'key' => $data['key'], 'category' => $data['category'], 'status' => 'draft',
            'name' => ['ar' => $data['name_ar'], 'en' => $data['name_en'] ?? $data['name_ar']], 'role_templates' => $data['role_templates'] ?? null,
        ]);
        Audit::log('agent.created', $agent);

        return to_route('platform.agents.show', $agent->key);
    }

    public function show(string $key): Response
    {
        $this->authorize('platform.agents.manage');
        $agent = Agent::query()->whereNull('tenant_id')->where('key', $key)->firstOrFail();
        $versions = $agent->versions()->orderByDesc('version')->get();

        $latestRuns = AgentTestRun::query()->whereIn('agent_version_id', $versions->pluck('id'))
            ->orderByDesc('id')->get()->groupBy('agent_version_id')
            ->map(fn ($runs) => $runs->where('batch', $runs->first()->batch)->values());

        return Inertia::render('Platform/Agents/Show', [
            'agent' => $agent->only(['key', 'name', 'description', 'category', 'status', 'role_templates', 'published_version_id']),
            // Staff see layer 1 here: makeVisible is deliberate and only in the platform area.
            'versions' => $versions->map(fn ($v) => $v->makeVisible('system_prompt')->only([
                'id', 'version', 'status', 'system_prompt', 'prompt_block_keys', 'input_schema', 'routing',
                'customization_policy', 'credits_cost', 'rollout', 'release_notes', 'published_at',
            ])),
            'testCases' => AgentTestCase::query()->where('agent_id', $agent->id)->orderBy('id')->get(),
            'latestRuns' => $latestRuns,
            'providers' => AiProvider::query()->pluck('key'),
            'promptBlocks' => PromptBlock::query()->where('status', 'published')->distinct()->pluck('key'),
        ]);
    }
}
