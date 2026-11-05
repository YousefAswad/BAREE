<?php

namespace App\Services\Files;

interface Scanner
{
    /** @return array{clean:bool, signature:?string} */
    public function scan(string $contents): array;
}
