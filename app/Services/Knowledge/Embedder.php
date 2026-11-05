<?php

namespace App\Services\Knowledge;

interface Embedder
{
    /** @param  string[]  $texts  @return array<int, float[]> */
    public function embed(array $texts): array;
}
