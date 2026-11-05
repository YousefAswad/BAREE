<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Learning\ArabicNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid retrieval: vector similarity (pgvector in production, PHP cosine elsewhere) + Arabic keyword overlap.
 * Only published documents of the user's own institution, BAREE's global documents, and the user's audience.
 */
final class KnowledgeSearch
{
    public function __construct(private Embedder $embedder) {}

    /** @return array<int, array{chunk_id:int, document_id:int, title:array, kind:string, content:string, score:float}> */
    public function search(?int $tenantId, ?string $roleTemplate, string $query, ?int $k = null): array
    {
        $k ??= (int) config('knowledge.top_k');
        $documents = KnowledgeDocument::query()->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q) => $q->orWhere('tenant_id', $tenantId)))
            ->get(['id', 'tenant_id', 'title', 'kind', 'audience'])
            ->filter(fn ($d) => empty($d->audience) || in_array($roleTemplate, $d->audience, true))
            ->keyBy('id');

        if ($documents->isEmpty()) {
            return [];
        }

        $vector = $this->embedder->embed([$query])[0];
        $tokens = array_unique(ArabicNormalizer::tokens($query));
        $candidates = DB::getDriverName() === 'pgsql' ? $this->pgCandidates($documents->keys()->all(), $vector, $k * 4) : $this->phpCandidates($documents->keys()->all(), $vector);

        return collect($candidates)->map(function ($c) use ($tokens, $documents) {
            $words = array_unique(ArabicNormalizer::tokens($c['content']));
            $overlap = $tokens ? count(array_intersect($tokens, $words)) / count($tokens) : 0;
            $doc = $documents[$c['document_id']];

            return $c + ['title' => $doc->title, 'kind' => $doc->kind, 'score' => round(0.8 * $c['similarity'] + 0.2 * $overlap, 4)];
        })->sortByDesc('score')->take($k)->values()->all();
    }

    private function pgCandidates(array $documentIds, array $vector, int $limit): array
    {
        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $rows = DB::select("SELECT id, document_id, content, 1 - (embedding_vec <=> ?::vector) AS similarity FROM knowledge_chunks
            WHERE document_id IN ({$placeholders}) AND embedding_vec IS NOT NULL ORDER BY embedding_vec <=> ?::vector LIMIT ?",
            array_merge(['['.implode(',', $vector).']'], $documentIds, ['['.implode(',', $vector).']', $limit]));

        return array_map(fn ($r) => ['chunk_id' => $r->id, 'document_id' => $r->document_id, 'content' => $r->content, 'similarity' => (float) $r->similarity], $rows);
    }

    private function phpCandidates(array $documentIds, array $vector): array
    {
        return KnowledgeChunk::query()->whereIn('document_id', $documentIds)->get(['id', 'document_id', 'content', 'embedding'])
            ->map(fn ($c) => ['chunk_id' => $c->id, 'document_id' => $c->document_id, 'content' => $c->content, 'similarity' => $this->cosine($vector, $c->embedding ?? [])])
            ->all();
    }

    private function cosine(array $a, array $b): float
    {
        if (! $a || count($a) !== count($b)) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $na += $v * $v;
            $nb += $b[$i] * $b[$i];
        }

        return ($na && $nb) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}
