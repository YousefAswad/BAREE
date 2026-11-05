<?php

namespace App\Services\Institution;

use App\Models\ProtectedTerm;
use App\Support\Audit;

/** Student names and other terms that must never reach a model provider. Decrypted in memory only. */
final class ProtectedTermsRepository
{
    /** @var array<int, string[]> per-request memo; never written to a shared cache */
    private array $memo = [];

    public function forTenant(?int $tenantId): array
    {
        if ($tenantId === null) {
            return [];
        }

        return $this->memo[$tenantId] ??= ProtectedTerm::query()->where('tenant_id', $tenantId)->get()->pluck('term')->all();
    }

    /** @param  string[]  $terms */
    public function add(array $terms, string $source = 'manual'): int
    {
        $added = 0;

        foreach (array_unique(array_filter(array_map(fn ($t) => trim((string) $t), $terms))) as $term) {
            if (mb_strlen($term) < 2 || mb_strlen($term) > 120) {
                continue;
            }

            $created = ProtectedTerm::query()->firstOrCreate(
                ['term_hash' => ProtectedTerm::hashFor($term)],
                ['term' => $term, 'source' => $source],
            );
            $added += (int) $created->wasRecentlyCreated;
        }

        $this->memo = [];
        Audit::log('privacy.protected_terms_added', null, ['count' => $added, 'source' => $source]); // never log the terms

        return $added;
    }

    /** One name per line or a single-column CSV. */
    public function importRoster(string $contents): int
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents); // strip UTF-8 BOM (Excel)
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        return $this->add(array_map(fn ($line) => str_getcsv($line)[0] ?? '', $lines), 'roster_upload');
    }
}
