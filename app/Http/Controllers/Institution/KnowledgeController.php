<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Jobs\IndexKnowledgeDocumentJob;
use App\Models\HubQuestion;
use App\Models\KnowledgeDocument;
use App\Services\Files\FileIntakeService;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Institution policies and guides that power "ask about your institution" in the Knowledge Hub. */
final class KnowledgeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('content.publish');

        return Inertia::render('Institution/Knowledge/Index', [
            'documents' => KnowledgeDocument::query()->where('tenant_id', $request->user()->tenant_id)->latest()->get(['id', 'kind', 'title', 'status', 'version', 'published_at', 'audience']),
            // What people asked but the hub could not answer: the institution's to-do list for its documents.
            'unanswered' => HubQuestion::query()->where('answered', false)->latest('created_at')->limit(20)->pluck('question'),
        ]);
    }

    public function store(Request $request, FileIntakeService $intake): RedirectResponse
    {
        $this->authorize('content.publish');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'kind' => ['required', 'in:policy,guide,prompt_template,research'],
            'body' => ['nullable', 'required_without:file', 'string', 'max:200000'],
            'file' => ['nullable', 'file', 'max:20480'],
            'audience' => ['nullable', 'array'], 'audience.*' => ['string', 'max:32'],
        ]);

        $document = KnowledgeDocument::create(['kind' => $data['kind'], 'title' => ['ar' => $data['title']], 'body' => $data['body'] ?? null,
            'audience' => $data['audience'] ?? null, 'status' => 'indexing', 'created_by' => $request->user()->id]);

        if ($request->hasFile('file')) {
            try {
                $file = $intake->store($request->file('file'), $request->user(), $document, 'off');
            } catch (DomainException $e) {
                $document->delete();

                return back()->withErrors(['file' => $e->getMessage()]);
            }
            $document->update(['source_file_id' => $file->id]);
        }

        IndexKnowledgeDocumentJob::dispatch($document->id, $request->user()->tenant_id)->delay($request->hasFile('file') ? now()->addSeconds(20) : null);
        Audit::log('knowledge.created', $document, ['kind' => $data['kind']]);

        return back()->with('status', __('The document is being indexed.'));
    }

    public function destroy(Request $request, KnowledgeDocument $document): RedirectResponse
    {
        $this->authorize('content.publish');
        abort_unless($document->tenant_id === $request->user()->tenant_id, 404); // global BAREE documents are read-only
        $document->delete();
        Audit::log('knowledge.deleted', $document);

        return back();
    }
}
