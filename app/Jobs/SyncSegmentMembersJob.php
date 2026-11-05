<?php

namespace App\Jobs;

use App\Models\Segment;
use App\Services\Institution\SegmentEvaluator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Run on save and nightly (scheduler) so rule-based segments follow profile and passport changes. */
final class SyncSegmentMembersJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function __construct(public int $tenantId, public int $segmentId) {}

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->segmentId;
    }

    public function handle(TenantContext $context, SegmentEvaluator $evaluator): void
    {
        $context->runAs($this->tenantId, fn () => $evaluator->sync(Segment::query()->findOrFail($this->segmentId)));
    }
}
