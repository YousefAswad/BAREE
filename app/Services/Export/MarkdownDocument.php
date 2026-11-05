<?php

namespace App\Services\Export;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Markdown → a small neutral block model shared by the DOCX and PPTX renderers.
 * Blocks: heading(level,text) | paragraph(text) | list(ordered, items[]) | table(rows[][])
 */
final class MarkdownDocument
{
    public static function environment(): Environment
    {
        $environment = new Environment(['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        return $environment;
    }

    public static function toHtml(string $markdown): string
    {
        return (string) (new MarkdownConverter(self::environment()))->convert($markdown);
    }

    /** @return array<int, array<string, mixed>> */
    public static function blocks(string $markdown): array
    {
        $document = (new MarkdownParser(self::environment()))->parse($markdown);
        $blocks = [];

        foreach ($document->children() as $node) {
            $blocks[] = match (true) {
                $node instanceof Heading => ['type' => 'heading', 'level' => $node->getLevel(), 'text' => self::text($node)],
                $node instanceof Paragraph => ['type' => 'paragraph', 'text' => self::text($node)],
                $node instanceof ListBlock => ['type' => 'list', 'ordered' => $node->getListData()->type === ListBlock::TYPE_ORDERED,
                    'items' => array_values(array_map(fn ($item) => self::text($item), array_filter(iterator_to_array($node->children()), fn ($c) => $c instanceof ListItem)))],
                $node instanceof Table => ['type' => 'table', 'rows' => self::rows($node)],
                default => ['type' => 'paragraph', 'text' => self::text($node)],
            };
        }

        return array_values(array_filter($blocks, fn ($b) => ($b['text'] ?? null) !== '' || $b['type'] !== 'paragraph'));
    }

    private static function rows(Node $table): array
    {
        $rows = [];
        $walker = $table->walker();
        while ($event = $walker->next()) {
            if ($event->isEntering() && $event->getNode() instanceof TableRow) {
                $rows[] = array_values(array_map(fn ($cell) => self::text($cell), array_filter(iterator_to_array($event->getNode()->children()), fn ($c) => $c instanceof TableCell)));
            }
        }

        return $rows;
    }

    private static function text(Node $node): string
    {
        $out = '';
        $walker = $node->walker();
        while ($event = $walker->next()) {
            $current = $event->getNode();
            if ($event->isEntering() && method_exists($current, 'getLiteral') && ! $current->hasChildren()) {
                $out .= $current->getLiteral();
            }
            if (! $event->isEntering() && ($current instanceof Paragraph) && $current !== $node) {
                $out .= "\n";
            }
        }

        return trim(preg_replace('/[ \t]+/u', ' ', $out));
    }
}
