<?php

namespace App\Http\Controllers\Studio;

use App\AI\Gateway\AiGateway;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\Studio\ToolCatalog;
use App\Services\Studio\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudioController extends Controller
{
    public function home(Request $request, ToolCatalog $catalog): Response
    {
        $this->authorize('ai.agents.use');
        $user = $request->user();

        return Inertia::render('Studio/Home', [
            'recommended' => $catalog->recommend($user)->map(fn ($r) => $this->card($r['agent']) + ['reason' => $r['reason']]),
            'tools' => $catalog->forUser($user)->map(fn (Agent $a) => $this->card($a)),
            'recentWorkspaces' => Workspace::query()->where('owner_id', $user->id)->whereNull('parent_id')->latest('updated_at')->limit(4)->get(['id', 'title', 'kind']),
        ]);
    }

    public function tool(Request $request, string $key, AiGateway $gateway, ToolCatalog $catalog, WorkspaceService $workspaces): Response
    {
        $this->authorize('ai.agents.use');
        $user = $request->user();
        $agent = $catalog->forUser($user)->firstWhere('key', $key) ?? abort(404);
        abort_if($agent->availability !== 'available', 404);
        [, $version] = $gateway->resolve($user, $key);

        // Prefill guided-form fields the platform already knows (profile, workspace).
        $prefill = collect($version->input_schema['fields'] ?? [])->mapWithKeys(fn ($f) => [$f['key'] => match ($f['source'] ?? null) {
            'profile' => match ($f['key']) {
                'grade' => (string) (collect($user->profile['grades'] ?? [])->first() ?? ''),
                'subject' => (string) (collect($user->profile['subjects'] ?? [])->first() ?? ''),
                default => '',
            },
            default => '',
        }]);

        return Inertia::render('Studio/Tool', [
            'agent' => $this->card($agent),
            'schema' => $version->input_schema,
            'steps' => collect($version->workflow ?? [])->map(fn ($s) => ['key' => $s['key'], 'title' => $s['title'] ?? $s['key']]),
            'credits' => $version->workflow ? collect($version->workflow)->sum(fn ($s) => $s['credits'] ?? 0) ?: $version->credits_cost : $version->credits_cost,
            'policy' => $version->customization_policy,
            'prefill' => $prefill,
            'workspaces' => Workspace::query()->get()->filter(fn ($w) => in_array($workspaces->access($user, $w), ['owner', 'editor'], true))
                ->map->only(['id', 'title', 'kind', 'parent_id'])->values(),
        ]);
    }

    private function card(Agent $a): array
    {
        return [
            'key' => $a->key, 'name' => $a->name, 'description' => $a->description, 'category' => $a->category,
            'content_type' => $a->content_type, 'availability' => $a->availability, 'human_review' => $a->human_review,
            'output_types' => $a->output_types ?? [],
        ];
    }
}
