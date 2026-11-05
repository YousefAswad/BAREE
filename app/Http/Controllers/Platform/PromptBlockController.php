<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AgentVersion;
use App\Models\PromptBlock;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Shared prompt blocks are versioned; publishing a version makes it live for every agent that uses the key. */
final class PromptBlockController extends Controller
{
    public function index(): Response
    {
        $this->authorize('platform.agents.manage');
        $usage = AgentVersion::query()->whereIn('status', ['published', 'rolling_out'])->get(['prompt_block_keys'])
            ->flatMap(fn ($v) => $v->prompt_block_keys ?? [])->countBy();

        return Inertia::render('Platform/Prompts/Index', [
            'blocks' => PromptBlock::query()->orderBy('key')->orderByDesc('version')->get(['id', 'key', 'version', 'group', 'content', 'status'])
                ->groupBy('key')->map(fn ($versions, $key) => ['key' => $key, 'group' => $versions->first()->group, 'agents' => $usage[$key] ?? 0, 'versions' => $versions->values()])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $data = $request->validate(['key' => ['required', 'regex:/^[a-z][a-z0-9_.]{2,60}$/'], 'group' => ['required', 'string', 'max:32'], 'content' => ['required', 'string', 'max:20000']]);
        $version = (int) PromptBlock::query()->where('key', $data['key'])->max('version') + 1;

        $block = PromptBlock::create($data + ['version' => $version, 'status' => 'draft']);
        Audit::log('prompt_block.drafted', $block, ['key' => $block->key, 'version' => $version]);

        return back();
    }

    public function publish(PromptBlock $block): RedirectResponse
    {
        $this->authorize('platform.agents.publish');
        PromptBlock::query()->where('key', $block->key)->where('status', 'published')->update(['status' => 'retired']);
        $block->update(['status' => 'published']);
        Audit::log('prompt_block.published', $block, ['key' => $block->key, 'version' => $block->version]);

        return back()->with('status', __('Published. Run the test suites of the agents that use this block.'));
    }
}
