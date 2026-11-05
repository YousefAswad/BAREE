<?php

namespace App\Services\Export;

use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

/** One slide per H2 section, short bullets (requires phpoffice/phppresentation). */
final class PptxRenderer
{
    public function render(string $title, string $markdown, string $reviewer): string
    {
        $deck = new PhpPresentation;
        $deck->removeSlideByIndex(0);
        $slides = $this->sections($title, MarkdownDocument::blocks($markdown));

        foreach ($slides as $i => $slideData) {
            $slide = $deck->createSlide();
            $titleShape = $slide->createRichTextShape()->setWidth(880)->setHeight(90)->setOffsetX(40)->setOffsetY(30);
            $this->rtl($titleShape->getActiveParagraph()->getAlignment());
            $titleShape->createTextRun($slideData['title'])->getFont()->setBold(true)->setSize($i === 0 ? 36 : 28)->setColor(new Color('FF1E2238'));

            if ($slideData['lines']) {
                $body = $slide->createRichTextShape()->setWidth(880)->setHeight(420)->setOffsetX(40)->setOffsetY(140);
                foreach (array_slice($slideData['lines'], 0, 7) as $n => $line) { // keep slides readable
                    $paragraph = $n === 0 ? $body->getActiveParagraph() : $body->createParagraph();
                    $this->rtl($paragraph->getAlignment());
                    $paragraph->createTextRun('• '.mb_strimwidth($line, 0, 140, '…'))->getFont()->setSize(18)->setColor(new Color('FF3B3F57'));
                }
            }

            $footer = $slide->createRichTextShape()->setWidth(880)->setHeight(30)->setOffsetX(40)->setOffsetY(660);
            $this->rtl($footer->getActiveParagraph()->getAlignment());
            $footer->createTextRun(Renderers::disclosure($reviewer))->getFont()->setSize(9)->setColor(new Color('FF5E6178'));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pptx');
        IOFactory::createWriter($deck, 'PowerPoint2007')->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function sections(string $title, array $blocks): array
    {
        $slides = [['title' => $title, 'lines' => []]];
        foreach ($blocks as $block) {
            if ($block['type'] === 'heading' && $block['level'] <= 2) {
                $slides[] = ['title' => $block['text'], 'lines' => []];

                continue;
            }
            $current = array_key_last($slides);
            $lines = match ($block['type']) {
                'list' => $block['items'],
                'table' => array_map(fn ($r) => implode(' | ', $r), $block['rows']),
                default => [$block['text']],
            };
            $slides[$current]['lines'] = array_merge($slides[$current]['lines'], array_filter($lines));
        }

        return $slides;
    }

    private function rtl(Alignment $alignment): void
    {
        $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if (method_exists($alignment, 'setIsRTL')) {
            $alignment->setIsRTL(true);
        }
    }
}
