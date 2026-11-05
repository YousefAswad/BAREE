<?php

namespace App\Jobs;

use App\Models\AiRun;
use App\Models\Export;
use App\Models\KnowledgeFile;
use App\Models\MediaJob;
use App\Models\WhatsAppMessage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Data minimisation (nightly): delete what we no longer need. Audit logs and the credit ledger are never deleted.
 * Retention periods live in config/baree.php → retention.
 */
final class RetentionJob implements ShouldQueue
{
    use Queueable;

    public function handle(TenantContext $context): void
    {
        $context->asPlatform(function () {
            $r = config('baree.retention');

            // Raw AI outputs and redaction maps: the saved Output is the user's document; the run keeps metadata only.
            AiRun::query()->where('created_at', '<', now()->subDays($r['ai_run_content_days']))->whereNotNull('output')
                ->update(['output' => null, 'redaction_map' => null]);

            Export::query()->where('expires_at', '<', now())->whereNotNull('path')->chunkById(200, function ($exports) {
                foreach ($exports as $export) {
                    Storage::disk($export->disk)->delete($export->path);
                    $export->update(['path' => null, 'status' => 'expired']);
                }
            });

            KnowledgeFile::query()->where('status', 'rejected')->where('updated_at', '<', now()->subDays(7))->chunkById(200, function ($files) {
                foreach ($files as $file) {
                    Storage::disk($file->disk)->delete($file->path);
                    $file->delete();
                }
            });

            MediaJob::query()->whereIn('status', ['failed', 'blocked'])->where('created_at', '<', now()->subDays($r['failed_media_days']))->delete();
            WhatsAppMessage::query()->where('created_at', '<', now()->subDays($r['whatsapp_metadata_days']))->delete();
        });
    }
}
