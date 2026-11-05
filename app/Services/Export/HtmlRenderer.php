<?php

namespace App\Services\Export;

final class HtmlRenderer
{
    public function render(string $title, string $markdown, string $reviewer): string
    {
        $body = MarkdownDocument::toHtml($markdown);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $footer = htmlspecialchars(Renderers::disclosure($reviewer), ENT_QUOTES);

        return <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>{$safeTitle}</title>
<style>
  @page { size: A4; margin: 18mm 16mm; }
  body { font-family: 'Noto Naskh Arabic', 'Noto Sans Arabic', 'Almarai', sans-serif; color: #1E2238; line-height: 1.9; font-size: 12.5pt; }
  h1 { font-size: 22pt; color: #1E2238; border-bottom: 3px solid #FFB48A; padding-bottom: 6pt; }
  h2 { font-size: 16pt; color: #44529E; margin-top: 18pt; }
  h3 { font-size: 13pt; }
  table { border-collapse: collapse; width: 100%; margin: 10pt 0; }
  th, td { border: 1px solid #D8D4CE; padding: 6pt 8pt; text-align: right; vertical-align: top; }
  th { background: #ECEFFC; }
  footer { margin-top: 24pt; padding-top: 8pt; border-top: 1px solid #E8E5E0; font-size: 9pt; color: #5E6178; }
</style>
</head>
<body>
<h1>{$safeTitle}</h1>
{$body}
<footer>{$footer}</footer>
</body>
</html>
HTML;
    }
}
