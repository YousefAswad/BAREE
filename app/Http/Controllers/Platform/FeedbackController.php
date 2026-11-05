<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTestCase;
use App\Services\Learning\FeedbackService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FeedbackController extends Controller
{
    public function index(Request $request, FeedbackService $feedback): Response
    {
        $this->authorize('platform.agents.manage');
        $days = (int) $request->integer('days', 30);

        return Inertia::render('Platform/Feedback/Index', ['agents' => $feedback->platformSummary(min(max($days, 7), 180)), 'days' => $days]);
    }

    /** Turn a recurring complaint into a draft regression case; staff complete the inputs before activating it. */
    public function convert(Request $request, string $agentKey): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $reason = $request->validate(['reason' => ['required', 'in:'.implode(',', FeedbackService::REASONS)]])['reason'];
        $agent = Agent::query()->whereNull('tenant_id')->where('key', $agentKey)->firstOrFail();

        $assertions = match ($reason) {
            'too_long' => [['type' => 'max_chars', 'value' => 3000]],
            'too_short' => [['type' => 'min_chars', 'value' => 800]],
            'format' => [['type' => 'contains', 'value' => '## ']],
            'not_local', 'language', 'not_curriculum', 'inaccurate' => [['type' => 'min_chars', 'value' => 200]],
            default => [['type' => 'min_chars', 'value' => 1]],
        };
        $case = AgentTestCase::create(['agent_id' => $agent->id, 'name' => 'من الملاحظات: '.$reason, 'category' => 'quality', 'inputs' => [],
            'assertions' => $assertions, 'blocking' => false, 'active' => false]);
        Audit::log('feedback.converted_to_test', $case, ['reason' => $reason]);

        return redirect()->route('platform.agents.show', $agent->key)->with('status', __('Draft test case created. Add inputs, then activate it.'));
    }
}
