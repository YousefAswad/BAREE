<?php

namespace App\Jobs;

use App\Models\MediaJob;
use App\Services\CreditService;
use App\Services\Media\MediaRegistry;
use App\Services\Media\MediaResult;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class GenerateMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // providers charge per call: never auto-retry silently

    public int $timeout = 300;

    public function __construct(public int $mediaJobId, public int $tenantId)
    {
        $this->onQueue('media');
    }

    public function handle(TenantContext $context, MediaRegistry $registry, CreditService $credits): void
    {
        $context->runAs($this->tenantId, function () use ($registry, $credits) {
            $job = MediaJob::query()->findOrFail($this->mediaJobId);
            $prompt = (string) $job->request['prompt'];
            $options = $job->request['options'] ?? [];

            try {
                if (in_array($job->type, ['image', 'speech'], true)) {
                    $result = $job->type === 'image' ? $registry->image()->generate($prompt, $options) : $registry->speech()->synthesize($prompt, $options);
                    self::store($job, $result, $credits);

                    return;
                }

                $externalId = $registry->async($job->type)->submit($prompt, $options);
                $job->update(['status' => 'submitted', 'external_id' => $externalId]);
                PollMediaJob::dispatch($job->id, $this->tenantId)->delay(now()->addSeconds(15));
            } catch (Throwable $e) {
                report($e);
                $job->update(['status' => 'failed', 'error' => Str::limit(class_basename($e), 120)]); // not charged
            }
        });
    }

    public static function store(MediaJob $job, MediaResult $result, CreditService $credits): void
    {
        $disk = config('filesystems.default');
        $path = 'tenants/'.$job->tenant_id.'/media/'.now()->format('Y/m').'/'.Str::ulid().'.'.$result->extension;
        Storage::disk($disk)->put($path, $result->bytes, ['visibility' => 'private']);

        $job->update(['status' => 'succeeded', 'disk' => $disk, 'path' => $path, 'mime' => $result->mime]);
        $credits->debit($job->tenant_id, $job->credits, $job, null, $job->user_id); // charged only on success
        Audit::log('media.generated', $job, ['type' => $job->type, 'bytes' => strlen($result->bytes)]);
    }
}
