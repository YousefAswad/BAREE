<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Post;
use App\Models\Segment;
use App\Services\Content\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ContentController extends Controller
{
    public function index(Request $request, PostService $posts): Response
    {
        $this->authorize('content.publish');

        return Inertia::render('Institution/Content/Index', [
            'posts' => Post::query()->where('tenant_id', $request->user()->tenant_id)->latest()->limit(50)->get()
                ->map(fn ($p) => $p->only(['id', 'kind', 'title', 'status', 'published_at', 'pinned_until']) + $posts->stats($p)),
            'departments' => Department::query()->get(['id', 'name']),
            'segments' => Segment::query()->get(['id', 'name']),
            'kinds' => PostService::KINDS,
        ]);
    }

    public function store(Request $request, PostService $posts): RedirectResponse
    {
        $this->authorize('content.publish');
        $posts->save($request->user(), $this->validated($request));

        return back()->with('status', __('Saved.'));
    }

    public function estimate(Request $request, PostService $posts): JsonResponse
    {
        $this->authorize('content.publish');

        return response()->json(['count' => $posts->estimate($request->user()->tenant_id, (array) $request->input('targeting', []))]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'in:'.implode(',', PostService::KINDS)],
            'title' => ['required', 'string', 'max:190'], 'title_en' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:20000'], 'body_en' => ['nullable', 'string', 'max:20000'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'], 'publish_at' => ['nullable', 'date', 'after:now'],
            'pinned_until' => ['nullable', 'date', 'after:now'],
            'targeting' => ['nullable', 'array'],
        ]);
    }
}
