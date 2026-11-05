<?php

namespace App\Jobs;

use App\Models\AssessmentCampaign;
use App\Notifications\CampaignAssignedNotification;
use App\Services\Institution\AudienceResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

final class LaunchCampaignJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(TenantContext $context, AudienceResolver $audiences): void
    {
        $tenantId = $context->asPlatform(fn () => AssessmentCampaign::query()->findOrFail($this->campaignId)->tenant_id);

        $context->runAs($tenantId, function () use ($audiences) {
            $campaign = AssessmentCampaign::query()->with('template')->findOrFail($this->campaignId);

            $audiences->query($campaign->audience ?? [], $campaign->tenant_id)
                ->chunkById(200, fn ($users) => Notification::send($users, new CampaignAssignedNotification($campaign)));
        });
    }
}
