<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeFile;
use App\Services\Learning\ArabicNormalizer;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class KnowledgeIndexer
{
    public function __construct(private Chunker $chunker, private Embedder $embedder) {}

    public function index(KnowledgeDocument $document): int
    {
        $text = $document->body;
        if ($document->source_file_id) {
            $file = KnowledgeFile::query()->find($document->source_file_id);
            if (! $file || $file->status !== 'indexed') {
                throw new RuntimeException('Source file is not scanned and readable yet.');
            }
            $text = trim(($text ? $text."\n\n" : '').$file->extracted_text);
        }

        $title = (string) ($document->title['ar'] ?? reset($document->title));
        $chunks = $this->chunker->split((string) $text, (int) config('knowledge.chunk_chars'), (int) config('knowledge.chunk_overlap'));
        // The title travels with every chunk: better retrieval for short policy clauses.
        $vectors = $chunks ? $this->embedder->embed(array_map(fn ($c) => $title."\n".$c, $chunks)) : [];

        DB::transaction(function () use ($document, $chunks, $vectors) {
            KnowledgeChunk::query()->where('document_id', $document->id)->delete();
            foreach ($chunks as $i => $content) {
                $chunk = KnowledgeChunk::create([
                    'tenant_id' => $document->tenant_id, 'document_id' => $document->id, 'chunk_index' => $i,
                    'content' => $content, 'normalized' => ArabicNormalizer::normalize($content), 'embedding' => $vectors[$i] ?? null,
                ]);
                if (DB::getDriverName() === 'pgsql' && isset($vectors[$i])) {
                    DB::update('UPDATE knowledge_chunks SET embedding_vec = ?::vector WHERE id = ?', ['['.implode(',', $vectors[$i]).']', $chunk->id]);
                }
            }
            $document->update(['status' => 'published', 'published_at' => $document->published_at ?? now()]);
        });

        Audit::log('knowledge.indexed', $document, ['chunks' => count($chunks)]);

        return count($chunks);
    }
}
