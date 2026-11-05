<?php

namespace App\Services\Export;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/** Right-to-left Word documents (requires phpoffice/phpword). */
final class DocxRenderer
{
    public function render(string $title, string $markdown, string $reviewer): string
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(12);
        $rtl = ['rtl' => true];
        $para = ['bidi' => true, 'alignment' => 'right', 'spaceAfter' => 120];
        $word->addTitleStyle(1, ['size' => 20, 'bold' => true, 'color' => '1E2238', 'rtl' => true], $para);
        $word->addTitleStyle(2, ['size' => 15, 'bold' => true, 'color' => '44529E', 'rtl' => true], $para);
        $word->addTitleStyle(3, ['size' => 13, 'bold' => true, 'rtl' => true], $para);

        $section = $word->addSection();
        $section->addTitle(htmlspecialchars($title), 1);

        foreach (MarkdownDocument::blocks($markdown) as $block) {
            match ($block['type']) {
                'heading' => $section->addTitle(htmlspecialchars($block['text']), min(3, max(2, $block['level']))),
                'list' => array_map(fn ($item) => $section->addListItem(htmlspecialchars($item), 0, $rtl, $block['ordered'] ? ['listType' => 7] : null, $para), $block['items']),
                'table' => $this->table($section, $block['rows'], $rtl, $para),
                default => $section->addText(htmlspecialchars($block['text']), $rtl, $para),
            };
        }

        $section->addFooter()->addText(htmlspecialchars(Renderers::disclosure($reviewer)), ['size' => 8, 'color' => '5E6178', 'rtl' => true], $para);

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function table($section, array $rows, array $rtl, array $para): void
    {
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'D8D4CE', 'bidiVisual' => true, 'cellMargin' => 80]);
        foreach ($rows as $i => $cells) {
            $table->addRow();
            foreach ($cells as $cell) {
                $table->addCell(2400, $i === 0 ? ['bgColor' => 'ECEFFC'] : [])->addText(htmlspecialchars($cell), $rtl + ($i === 0 ? ['bold' => true] : []), $para);
            }
        }
    }
}
