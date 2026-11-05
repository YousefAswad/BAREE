<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\MediaJob;
use App\Models\Output;
use App\Models\ShareLink;
use App\Services\Export\MarkdownDocument;
use App\Services\Studio\ScenarioService;
use App\Services\Studio\ShareLinkService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ShareController extends Controller
{
    public function store(Request $request, Output $output, ShareLinkService $links): RedirectResponse
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', 'between:1,30']])['days'];
        [, $raw] = $links->create($request->user(), $output, $days);

        return back()->with('status', url('/s/'.$raw)); // shown once; only the hash is stored
    }

    public function revoke(Request $request, ShareLink $link, ShareLinkService $links): RedirectResponse
    {
        $links->revoke($request->user(), $link);

        return back();
    }

    /** Public, read-only, no account needed. Rendered without any personal data of the author. */
    public function view(string $token, ShareLinkService $links, ScenarioService $scenarios, TenantContext $context): Response
    {
        $resolved = $links->resolve($token) ?? abort(404);
        [$link, $output] = $resolved;

        return $context->runAs($link->tenant_id, fn () => Inertia::render('Share/View', [
            'title' => $output->title,
            'type' => $output->type,
            'html' => $output->type === 'scenario' ? null : MarkdownDocument::toHtml($output->content['body'] ?? ''), // HTML input escaped
            'scenes' => $output->type === 'scenario' ? collect($scenarios->present($output))->map(fn ($s) => [
                'type' => $s['type'], 'title' => $s['title'], 'html' => $s['body'] ? MarkdownDocument::toHtml($s['body']) : null,
                'media' => isset($s['media']) && $s['media']['status'] === 'succeeded' ? url("/s/{$token}/media/{$s['media']['id']}") : null,
                'mime' => $s['media']['mime'] ?? null,
            ]) : [],
            'token' => $token,
        ]));
    }

    public function media(string $token, string $mediaId, ShareLinkService $links, TenantContext $context)
    {
        $resolved = $links->resolve($token) ?? abort(404);
        [$link, $output] = $resolved;

        return $context->runAs($link->tenant_id, function () use ($output, $mediaId) {
            $ids = collect($output->content['scenes'] ?? [])->pluck('media_job_id')->filter();
            $job = MediaJob::query()->where('public_id', $mediaId)->whereIn('id', $ids)->where('status', 'succeeded')->firstOrFail(); // only media of this scenario

            return Storage::disk($job->disk)->response($job->path, null, ['Content-Type' => $job->mime, 'X-Content-Type-Options' => 'nosniff']);
        });
    }
}
