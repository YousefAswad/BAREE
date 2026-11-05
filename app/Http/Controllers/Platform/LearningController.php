<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Jobs\IndexKnowledgeDocumentJob;
use App\Models\Course;
use App\Models\KnowledgeDocument;
use App\Models\LearningPath;
use App\Models\Mission;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** BAREE's global learning content: paths, missions, courses and hub resources. */
final class LearningController extends Controller
{
    public function index(): Response
    {
        $this->authorize('platform.content.publish');

        return Inertia::render('Platform/Learning/Index', [
            'paths' => LearningPath::query()->whereNull('tenant_id')->withCount('missions')->get(['id', 'key', 'title', 'status']),
            'missions' => Mission::query()->whereNull('tenant_id')->get(['id', 'key', 'title', 'status', 'path_id', 'xp']),
            'courses' => Course::query()->whereNull('tenant_id')->get(['id', 'key', 'title', 'status', 'hours']),
            'documents' => KnowledgeDocument::query()->whereNull('tenant_id')->get(['id', 'kind', 'title', 'status']),
        ]);
    }

    public function toggle(Request $request, string $type, int $id): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $model = match ($type) { 'path' => LearningPath::class, 'mission' => Mission::class, 'course' => Course::class, default => abort(404) };
        $record = $model::query()->whereNull('tenant_id')->findOrFail($id);
        $record->update(['status' => $record->status === 'published' ? 'draft' : 'published']);
        Audit::log('learning.status_changed', $record, ['status' => $record->status]);

        return back();
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $data = $request->validate(['title' => ['required', 'string', 'max:190'], 'kind' => ['required', 'in:guide,prompt_template,research,video'],
            'body' => ['required', 'string', 'max:200000'], 'audience' => ['nullable', 'array']]);

        $document = KnowledgeDocument::create(['tenant_id' => null, 'kind' => $data['kind'], 'title' => ['ar' => $data['title']], 'body' => $data['body'],
            'audience' => $data['audience'] ?? null, 'status' => 'indexing', 'created_by' => $request->user()->id]);
        IndexKnowledgeDocumentJob::dispatch($document->id, null);

        return back();
    }
}
