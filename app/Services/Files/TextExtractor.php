<?php

namespace App\Services\Files;

use RuntimeException;
use ZipArchive;

/** Plain-text extraction for the formats agents can read. Office formats are parsed as XML without macros. */
final class TextExtractor
{
    public const MAX_CHARS = 400_000;

    public function extract(string $path, string $mime): string
    {
        $text = match (true) {
            str_contains($mime, 'pdf') => $this->pdf($path),
            str_contains($mime, 'wordprocessingml') => $this->zipXml($path, '#^word/document\.xml$#', '</w:p>'),
            str_contains($mime, 'presentationml') => $this->zipXml($path, '#^ppt/slides/slide\d+\.xml$#', '</a:p>', true),
            str_contains($mime, 'spreadsheetml') => $this->zipXml($path, '#^xl/sharedStrings\.xml$#', '</si>'),
            str_starts_with($mime, 'text/') => (string) file_get_contents($path),
            default => throw new RuntimeException("No extractor for {$mime}"),
        };

        $text = preg_replace("/[ \t]+/u", ' ', preg_replace("/\R{3,}/u", "\n\n", $text));

        return mb_substr(trim($text), 0, self::MAX_CHARS);
    }

    private function pdf(string $path): string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            return (new \Smalot\PdfParser\Parser)->parseFile($path)->getText();
        }

        $out = shell_exec('pdftotext -enc UTF-8 -nopgbrk '.escapeshellarg($path).' - 2>/dev/null');
        if (! is_string($out)) {
            throw new RuntimeException('Install smalot/pdfparser or poppler-utils to read PDFs.');
        }

        return $out;
    }

    private function zipXml(string $path, string $entryPattern, string $paragraphEnd, bool $sortNumeric = false): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Corrupt office file.');
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match($entryPattern, $name)) {
                $names[] = $name;
            }
        }
        if ($sortNumeric) {
            usort($names, fn ($a, $b) => (int) filter_var($a, FILTER_SANITIZE_NUMBER_INT) <=> (int) filter_var($b, FILTER_SANITIZE_NUMBER_INT));
        }

        $parts = [];
        foreach ($names as $name) {
            $stat = $zip->statName($name);
            if ($stat && $stat['size'] > 50 * 1024 * 1024) {
                throw new RuntimeException('Office part too large (possible zip bomb).');
            }
            $xml = (string) $zip->getFromName($name);
            $parts[] = html_entity_decode(strip_tags(str_replace($paragraphEnd, "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $zip->close();

        return implode("\n\n", $parts);
    }
}
