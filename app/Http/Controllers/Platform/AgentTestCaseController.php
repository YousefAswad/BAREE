<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTestCase;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AgentTestCaseController extends Controller
{
    public function store(Request $request, string $key): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $agent = Agent::query()->whereNull('tenant_id')->where('key', $key)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:quality,safety,privacy,performance'],
            'inputs' => ['required', 'array'],
            'task_instructions' => ['nullable', 'string', 'max:4000'],
            'assertions' => ['required', 'array', 'min:1', 'max:20'],
            'assertions.*.type' => ['required', 'in:contains,not_contains,regex,max_chars,min_chars,sections'],
            'assertions.*.value' => ['required'],
            'blocking' => ['boolean'],
        ]);

        foreach ($data['assertions'] as $assertion) {
            if ($assertion['type'] === 'regex' && @preg_match((string) $assertion['value'], '') === false) {
                return back()->withErrors(['assertions' => __('Invalid regular expression.')]);
            }
        }

        $case = AgentTestCase::create($data + ['agent_id' => $agent->id, 'active' => true]);
        Audit::log('agent.test_case_created', $case);

        return back();
    }

    public function destroy(AgentTestCase $case): RedirectResponse
    {
        $this->authorize('platform.agents.manage');
        $case->update(['active' => false]); // keep history for old batches

        return back();
    }
}
