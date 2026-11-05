<?php

namespace App\Services\Files;

/** Local development and tests only. Registered automatically when baree.uploads.scan_with = none. */
final class NullScanner implements Scanner
{
    public function scan(string $contents): array
    {
        // Still catch the EICAR test string so the pipeline can be exercised end to end.
        return str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')
            ? ['clean' => false, 'signature' => 'Eicar-Test-Signature']
            : ['clean' => true, 'signature' => null];
    }
}
