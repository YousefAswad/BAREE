<?php

namespace App\Services\Studio;

use App\Models\Agent;
use App\Models\TenantAgentSetting;
use App\Models\User;
use Illuminate\Support\Collection;

/** What the studio shows a person: only tools their role and institution allow, ranked by their gaps. */
final class ToolCatalog
{
    public function forUser(User $user): Collection
    {
        $settings = TenantAgentSetting::query()->get()->keyBy('agent_id');
        $role = $user->roleTemplateKey();

        return Agent::query()->whereIn('status', ['published', 'catalog'])->where('category', '!=', 'system')->orderBy('category')->get()
            ->filter(function (Agent $agent) use ($settings, $role, $user) {
                $setting = $settings[$agent->id] ?? null;
                if ($setting && ! $setting->enabled) {
                    return false;
                }
                if ($setting?->department_ids && ! in_array($user->department_id, $setting->department_ids, true)) {
                    return false;
                }

                return empty($agent->role_templates) || in_array($role, $agent->role_templates, true);
            })->values();
    }

    /** Top picks: tools that train the user's two weakest competencies first, then popular ones for the role. */
    public function recommend(User $user, int $limit = 3): Collection
    {
        $weakest = collect($user->passport?->competencies ?? [])->sort()->keys()->take(2)->all();

        return $this->forUser($user)->where('availability', 'available')->where('status', 'published')
            ->sortByDesc(fn (Agent $a) => count(array_intersect($a->competency_tags ?? [], $weakest)) * 10 + ($a->published_version_id ? 1 : 0))
            ->take($limit)->values()
            ->map(fn (Agent $a) => ['agent' => $a, 'reason' => array_values(array_intersect($a->competency_tags ?? [], $weakest))]);
    }
}
