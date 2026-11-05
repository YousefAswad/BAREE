<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\Http;

/** HTML → PDF through a Gotenberg container (Chromium with proper Arabic shaping). */
final class PdfRenderer
{
    public function __construct(private HtmlRenderer $html) {}

    public function render(string $title, string $markdown, string $reviewer): string
    {
        $response = Http::timeout(120)
            ->attach('files', $this->html->render($title, $markdown, $reviewer), 'index.html')
            ->post(rtrim((string) config('services.gotenberg.url', 'http://127.0.0.1:3000'), '/').'/forms/chromium/convert/html', [
                'printBackground' => 'true', 'preferCssPageSize' => 'true',
            ])->throw();

        return $response->body();
    }
}
