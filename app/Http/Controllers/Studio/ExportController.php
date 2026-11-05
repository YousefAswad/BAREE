<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Export;
use App\Models\Output;
use App\Services\Export\ExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController extends Controller
{
    public function store(Request $request, Output $output, ExportService $exports): RedirectResponse
    {
        $format = $request->validate(['format' => ['required', 'in:'.implode(',', ExportService::FORMATS)]])['format'];
        $exports->request($request->user(), $output, $format);

        return back()->with('status', __('Your file is being prepared.'));
    }

    public function download(Request $request, Export $export, ExportService $exports): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()->id && $export->status === 'ready' && $export->expires_at?->isFuture(), 404);
        $output = Output::query()->findOrFail($export->output_id);
        $name = Str::slug(Str::limit($output->title, 60, ''), '-', 'ar') ?: 'baree';

        return Storage::disk($export->disk)->download($export->path, "{$name}.{$export->format}", ['Content-Type' => $exports->mime($export->format)]);
    }
}
