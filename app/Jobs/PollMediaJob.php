<?php

namespace App\Jobs;

use App\Models\MediaJob;
use App\Services\CreditService;
use App\Services\Media\MediaRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class PollMediaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaJobId, public int $tenantId)
    {
        $this->onQueue('media');
    }

    public function handle(TenantContext $context, MediaRegistry $registry, CreditService $credits): void
    {
        $context->runAs($this->tenantId, function () use ($registry, $credits) {
            $job = MediaJob::query()->findOrFail($this->mediaJobId);
            if (! in_array($job->status, ['submitted', 'processing'], true)) {
                return;
            }

            try {
                $driver = $registry->async($job->type);
                $status = $driver->poll((string) $job->external_id);
                $job->increment('polls');

                if ($status->state === 'succeeded') {
                    GenerateMediaJob::store($job, $driver->download((string) $status->resultUrl), $credits);

                    return;
                }
                if ($status->state === 'failed' || $job->polls >= (int) config('media.max_polls')) {
                    $job->update(['status' => 'failed', 'error' => $status->error ?? 'timeout']);

                    return;
                }

                $job->update(['status' => 'processing']);
                self::dispatch($job->id, $this->tenantId)->delay(now()->addSeconds(min(60, 10 + $job->polls * 2)));
            } catch (Throwable $e) {
                report($e);
                $job->update(['status' => 'failed', 'error' => class_basename($e)]);
            }
        });
    }
}
