<?php

namespace App\Services\Knowledge;

use App\Services\Learning\ArabicNormalizer;

/** Deterministic hashing-trick embeddings for tests and offline demos (keyword-level similarity). */
final class FakeEmbedder implements Embedder
{
    public function __construct(private int $dimensions = 256) {}

    public function embed(array $texts): array
    {
        return array_map(function (string $text) {
            $vector = array_fill(0, $this->dimensions, 0.0);
            foreach (ArabicNormalizer::tokens($text) as $token) {
                $vector[crc32($token) % $this->dimensions] += 1.0;
            }
            $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector))) ?: 1.0;

            return array_map(fn ($v) => $v / $norm, $vector);
        }, $texts);
    }
}
