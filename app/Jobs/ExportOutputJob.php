<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\Export\ExportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ExportOutputJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $exportId, public int $tenantId)
    {
        $this->onQueue('exports');
    }

    public function handle(TenantContext $context, ExportService $exports): void
    {
        $context->runAs($this->tenantId, function () use ($exports) {
            $export = Export::query()->findOrFail($this->exportId);
            try {
                $exports->render($export);
            } catch (Throwable $e) {
                report($e);
                $export->update(['status' => 'failed', 'error' => class_basename($e)]);
            }
        });
    }
}
