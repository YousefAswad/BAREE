<?php

namespace App\Http\Controllers\Learning;

use App\AI\Exceptions\AiPolicyViolation;
use App\Http\Controllers\Controller;
use App\Models\HubQuestion;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\HubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class HubController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $request->user()->roleTemplateKey();

        return Inertia::render('Hub/Index', [
            'resources' => KnowledgeDocument::query()->where('status', 'published')->latest('published_at')->limit(60)->get()
                ->filter(fn ($d) => empty($d->audience) || in_array($role, $d->audience, true))
                ->map(fn ($d) => ['id' => $d->id, 'kind' => $d->kind, 'title' => $d->title, 'source' => $d->tenant_id ? 'institution' : 'baree',
                    'excerpt' => mb_substr((string) $d->body, 0, 180)])->values(),
        ]);
    }

    public function ask(Request $request, HubService $hub): JsonResponse
    {
        $question = $request->validate(['question' => ['required', 'string', 'min:5', 'max:1000']])['question'];

        try {
            return response()->json($hub->ask($request->user(), $question));
        } catch (AiPolicyViolation $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function document(Request $request, KnowledgeDocument $document): Response
    {
        abort_unless($document->status === 'published' && (empty($document->audience) || in_array($request->user()->roleTemplateKey(), $document->audience, true)), 404);

        return Inertia::render('Hub/Document', ['document' => $document->only(['id', 'kind', 'title', 'body', 'published_at', 'version'])]);
    }

    public function helpful(Request $request, HubQuestion $question): JsonResponse
    {
        abort_unless($question->user_id === $request->user()->id, 404);
        $question->update(['helpful' => $request->boolean('helpful') ? 1 : 0]);

        return response()->json(['ok' => true]);
    }
}
