<?php

namespace App\Services\Export;

use App\Jobs\ExportOutputJob;
use App\Models\Export;
use App\Models\Output;
use App\Models\User;
use App\Services\Studio\OutputService;
use App\Support\Audit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ExportService
{
    public const FORMATS = ['docx', 'pptx', 'pdf', 'html', 'md'];

    private const MIME = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'pdf' => 'application/pdf', 'html' => 'text/html; charset=utf-8', 'md' => 'text/markdown; charset=utf-8',
    ];

    public function __construct(private OutputService $outputs) {}

    public function request(User $user, Output $output, string $format): Export
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException('Unsupported format.');
        }
        $this->outputs->assertCanView($output, $user);

        $export = Export::create(['user_id' => $user->id, 'output_id' => $output->id, 'format' => $format, 'status' => 'queued']);
        ExportOutputJob::dispatch($export->id, $user->tenant_id);

        return $export;
    }

    public function render(Export $export): void
    {
        $output = Output::query()->findOrFail($export->output_id);
        $markdown = (string) ($output->content['body'] ?? '');
        $reviewer = (string) (User::query()->find($export->user_id)?->name ?? '');

        $bytes = match ($export->format) {
            'docx' => app(DocxRenderer::class)->render($output->title, $markdown, $reviewer),
            'pptx' => app(PptxRenderer::class)->render($output->title, $markdown, $reviewer),
            'pdf' => app(PdfRenderer::class)->render($output->title, $markdown, $reviewer),
            'html' => app(HtmlRenderer::class)->render($output->title, $markdown, $reviewer),
            'md' => "# {$output->title}\n\n{$markdown}\n\n---\n".Renderers::disclosure($reviewer)."\n",
        };

        $disk = config('filesystems.default');
        $path = 'tenants/'.$export->tenant_id.'/exports/'.Str::ulid().'.'.$export->format;
        Storage::disk($disk)->put($path, $bytes, ['visibility' => 'private']);
        $export->update(['status' => 'ready', 'disk' => $disk, 'path' => $path, 'expires_at' => now()->addDays(7)]);
        Audit::log('output.exported', $output, ['format' => $export->format]);
    }

    public function mime(string $format): string
    {
        return self::MIME[$format];
    }
}
