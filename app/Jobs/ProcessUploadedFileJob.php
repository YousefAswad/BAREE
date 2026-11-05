<?php

namespace App\Jobs;

use App\Models\KnowledgeFile;
use App\Services\Files\Scanner;
use App\Services\Files\TextExtractor;
use App\Support\Audit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** pending → (scan) → scanned → (extract) → indexed | rejected */
final class ProcessUploadedFileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $fileId, public ?int $tenantId)
    {
        $this->onQueue('files');
    }

    public function handle(TenantContext $context, Scanner $scanner, TextExtractor $extractor): void
    {
        $run = fn () => $this->process($scanner, $extractor);
        $this->tenantId ? $context->runAs($this->tenantId, $run) : $context->asPlatform($run);
    }

    private function process(Scanner $scanner, TextExtractor $extractor): void
    {
        $file = KnowledgeFile::query()->findOrFail($this->fileId);
        $contents = Storage::disk($file->disk)->get($file->path);

        $verdict = $scanner->scan($contents); // throws on scanner failure → retried, file stays pending (fail closed)
        if (! $verdict['clean']) {
            Storage::disk($file->disk)->delete($file->path);
            $file->update(['status' => 'rejected', 'rejection_reason' => 'malware', 'scanned_at' => now()]);
            Audit::log('file.rejected', $file, ['signature' => $verdict['signature']]);

            return;
        }
        $file->update(['status' => 'scanned', 'scanned_at' => now()]);

        $tmp = tempnam(sys_get_temp_dir(), 'baree');
        try {
            file_put_contents($tmp, $contents);
            $text = $extractor->extract($tmp, $file->mime);
            $file->update(['status' => 'indexed', 'extracted_text' => $text, 'text_chars' => mb_strlen($text)]);
        } catch (Throwable $e) {
            $file->update(['status' => 'rejected', 'rejection_reason' => 'unreadable']);
            report($e);
        } finally {
            @unlink($tmp);
        }
    }
}
