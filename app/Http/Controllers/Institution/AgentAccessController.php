<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AiProvider;
use App\Models\AiProviderCredential;
use App\Models\Department;
use App\Models\TenantAgentSetting;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AgentAccessController extends Controller
{
    public function index(): Response
    {
        $this->authorize('agents.configure');
        $settings = TenantAgentSetting::query()->get()->keyBy('agent_id');

        return Inertia::render('Institution/Agents/Access', [
            'agents' => Agent::query()->where('status', 'published')->orderBy('category')->get()->map(fn (Agent $a) => [
                'id' => $a->id, 'key' => $a->key, 'name' => $a->name, 'category' => $a->category,
                'enabled' => $settings[$a->id]->enabled ?? true,
                'department_ids' => $settings[$a->id]->department_ids ?? null,
                'monthly_credit_cap' => $settings[$a->id]->monthly_credit_cap ?? null,
                'institution_rules' => $settings[$a->id]->institution_rules ?? '',
            ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'byok' => AiProvider::query()->where('enabled', true)->get(['id', 'key'])->map(fn ($p) => [
                'key' => $p->key, 'configured' => AiProviderCredential::query()->where('provider_id', $p->id)->where('tenant_id', auth()->user()->tenant_id)->exists(),
            ]),
        ]);
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorize('agents.configure');
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'department_ids' => ['nullable', 'array'], 'department_ids.*' => [Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'monthly_credit_cap' => ['nullable', 'integer', 'between:0,10000000'],
            'institution_rules' => ['nullable', 'string', 'max:6000'],
        ]);

        $setting = TenantAgentSetting::updateOrCreate(['agent_id' => $agent->id], $data);
        Audit::log('agent.access_updated', $setting, ['agent' => $agent->key, 'enabled' => $data['enabled']]);

        return back();
    }

    /** Bring your own key: used before the platform key for this institution only. */
    public function storeKey(Request $request, AiProvider $provider): RedirectResponse
    {
        $this->authorize('tenant.settings.manage');
        $secret = $request->validate(['secret' => ['required', 'string', 'min:10', 'max:500']])['secret'];

        AiProviderCredential::updateOrCreate(['provider_id' => $provider->id, 'tenant_id' => $request->user()->tenant_id], ['secret' => $secret, 'rotated_at' => now()]);
        Audit::log('provider.byok_saved', $provider);

        return back()->with('status', __('Key saved.'));
    }
}
