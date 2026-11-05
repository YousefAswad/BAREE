<?php

namespace App\Services\Institution;

use App\Jobs\LaunchCampaignJob;
use App\Models\AssessmentCampaign;
use App\Models\AssessmentTemplate;
use App\Models\User;
use App\Support\Audit;
use DomainException;

final class CampaignService
{
    public function __construct(private AudienceResolver $audiences) {}

    public function create(User $actor, array $data): AssessmentCampaign
    {
        $template = AssessmentTemplate::query()->where('status', 'published')->findOrFail($data['template_id']);
        $audience = array_filter([
            'departments' => $data['departments'] ?? [], 'segments' => $data['segments'] ?? [],
            'role_templates' => $data['role_templates'] ?? [], 'campuses' => $data['campuses'] ?? [],
        ]);

        if ($this->audiences->count($audience, $actor->tenant_id) === 0) {
            throw new DomainException(__('This audience has no active users.'));
        }

        $campaign = AssessmentCampaign::create([
            'template_id' => $template->id, 'name' => $data['name'], 'audience' => $audience,
            'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'] ?? null, 'status' => 'scheduled',
        ]);
        Audit::log('campaign.created', $campaign, ['audience' => $audience]);

        if ($campaign->starts_at->isPast()) {
            $this->launch($campaign);
        }

        return $campaign;
    }

    public function launch(AssessmentCampaign $campaign): void
    {
        if ($campaign->status !== 'scheduled') {
            throw new DomainException(__('Campaign already launched.'));
        }

        $campaign->update(['status' => 'active']);
        LaunchCampaignJob::dispatch($campaign->id);
        Audit::log('campaign.launched', $campaign);
    }
}
