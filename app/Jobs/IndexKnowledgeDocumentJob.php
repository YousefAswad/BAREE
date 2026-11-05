<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class IndexKnowledgeDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600]; // waits for the source file scan if needed

    public function __construct(public int $documentId, public ?int $tenantId)
    {
        $this->onQueue('files');
    }

    public function handle(TenantContext $context, KnowledgeIndexer $indexer): void
    {
        $work = function () use ($indexer) {
            $document = KnowledgeDocument::query()->findOrFail($this->documentId);
            try {
                $indexer->index($document);
            } catch (Throwable $e) {
                $document->update(['status' => $this->attempts() >= $this->tries ? 'failed' : 'indexing']);
                throw $e;
            }
        };

        $this->tenantId ? $context->runAs($this->tenantId, $work) : $context->asPlatform($work);
    }
}
