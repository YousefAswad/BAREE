<?php

namespace App\Http\Controllers\Me;

use App\AI\Gateway\AiGateway;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeFile;
use App\Models\UserAgentPreference;
use App\Services\Files\FileIntakeService;
use App\Services\Studio\ToolCatalog;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Layer 4: a person's own instructions and reference files for one agent, within what the agent allows. */
final class AgentPreferenceController extends Controller
{
    public function show(Request $request, string $key, ToolCatalog $catalog, AiGateway $gateway): Response
    {
        $this->authorize('agents.customize_own');
        $user = $request->user();
        $agent = $catalog->forUser($user)->firstWhere('key', $key) ?? abort(404);
        [, $version] = $gateway->resolve($user, $key);
        $preference = UserAgentPreference::query()->firstOrCreate(['user_id' => $user->id, 'agent_id' => $agent->id]);
        $institutionRules = \App\Models\TenantAgentSetting::query()->where('agent_id', $agent->id)->value('institution_rules');

        return Inertia::render('Studio/AgentSettings', [
            'agent' => ['key' => $agent->key, 'name' => $agent->name],
            'policy' => $version->customization_policy,
            'institutionRules' => $institutionRules, // read-only for the user (layer 2)
            'instructions' => $preference->instructions,
            'files' => KnowledgeFile::query()->where('owner_type', $preference->getMorphClass())->where('owner_id', $preference->id)
                ->get(['id', 'original_name', 'status', 'usage_mode']),
            'suggestions' => ['اكتب بلغة بسيطة لطلاب متفاوتين', 'ركّز على أمثلة من البيئة المحلية', 'خلّي الأنشطة جماعية بمجموعات من ثلاثة', 'أضف سؤال تفكير ناقد بالآخر'],
        ]);
    }

    public function update(Request $request, string $key, ToolCatalog $catalog, AiGateway $gateway): RedirectResponse
    {
        $this->authorize('agents.customize_own');
        $user = $request->user();
        $agent = $catalog->forUser($user)->firstWhere('key', $key) ?? abort(404);
        [, $version] = $gateway->resolve($user, $key);
        abort_if(data_get($version->customization_policy, 'user.instructions') === false, 403);

        $instructions = $request->validate(['instructions' => ['nullable', 'string', 'max:4000']])['instructions'];
        UserAgentPreference::query()->updateOrCreate(['user_id' => $user->id, 'agent_id' => $agent->id], ['instructions' => $instructions]);
        Audit::log('agent.personal_instructions_saved', $user, ['agent' => $key]);

        return back()->with('status', __('Saved.'));
    }

    public function upload(Request $request, string $key, ToolCatalog $catalog, AiGateway $gateway, FileIntakeService $intake): RedirectResponse
    {
        $this->authorize('agents.customize_own');
        $user = $request->user();
        $agent = $catalog->forUser($user)->firstWhere('key', $key) ?? abort(404);
        [, $version] = $gateway->resolve($user, $key);
        abort_if(data_get($version->customization_policy, 'user.files') === false, 403);
        $request->validate(['file' => ['required', 'file', 'max:20480'], 'usage' => ['nullable', 'in:always,on_demand,off']]);

        $preference = UserAgentPreference::query()->firstOrCreate(['user_id' => $user->id, 'agent_id' => $agent->id]);
        try {
            $intake->store($request->file('file'), $user, $preference, $request->input('usage', 'always'));
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyFile(Request $request, KnowledgeFile $file): RedirectResponse
    {
        $preference = UserAgentPreference::query()->find($file->owner_id);
        abort_unless($file->owner_type === (new UserAgentPreference)->getMorphClass() && $preference?->user_id === $request->user()->id, 404);
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return back();
    }
}
