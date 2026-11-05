<?php

// Include from routes/console.php:  require __DIR__.'/schedule.php';

use App\Jobs\SyncSegmentMembersJob;
use App\Models\AssessmentCampaign;
use App\Models\Segment;
use App\Services\Institution\CampaignService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schedule;

// Launch campaigns whose start time has arrived.
Schedule::call(function (TenantContext $context, CampaignService $campaigns) {
    $due = $context->asPlatform(fn () => AssessmentCampaign::query()->where('status', 'scheduled')->where('starts_at', '<=', now())->get(['id', 'tenant_id']));
    foreach ($due as $row) {
        $context->runAs($row->tenant_id, fn () => $campaigns->launch(AssessmentCampaign::query()->findOrFail($row->id)));
    }
})->everyFiveMinutes()->name('campaigns:launch-due')->withoutOverlapping();

// Rule-based segments follow profile and passport changes.
Schedule::call(function (TenantContext $context) {
    $context->asPlatform(fn () => Segment::query()->where('mode', 'rules')->get(['id', 'tenant_id']))
        ->each(fn ($s) => SyncSegmentMembersJob::dispatch($s->tenant_id, $s->id));
})->dailyAt('02:00')->name('segments:sync')->withoutOverlapping();

// Expired support sessions are closed even if staff forget.
Schedule::call(fn () => \App\Models\SupportSession::query()->whereNull('ended_at')->where('expires_at', '<', now())->update(['ended_at' => now()]))
    ->everyFiveMinutes()->name('support:expire');

// Phase 5
Schedule::job(new \App\Jobs\BillingCycleJob)->dailyAt('03:00')->name('billing:cycle')->withoutOverlapping();
Schedule::call(fn (TenantContext $context) => $context->asPlatform(fn () => \App\Models\Tenant::query()->whereIn('status', ['trial', 'active'])->pluck('id'))
    ->each(fn ($id) => $context->runAs($id, fn () => app(\App\Services\Operations\AssignmentService::class)->sendReminders())))
    ->dailyAt('07:30')->name('assignments:remind')->withoutOverlapping();
Schedule::job(new \App\Jobs\SnapshotReadinessJob)->monthlyOn(1, '04:00')->name('readiness:snapshot');
Schedule::call(fn (TenantContext $context) => $context->asPlatform(fn () => \App\Models\Post::query()->where('status', 'scheduled')->where('published_at', '<=', now())->update(['status' => 'published'])))
    ->everyFiveMinutes()->name('posts:publish-scheduled');

// Phase 6
Schedule::job(new \App\Jobs\RetentionJob)->dailyAt('02:30')->name('retention')->withoutOverlapping();
