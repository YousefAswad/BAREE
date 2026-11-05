<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Content\PostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Global posts from BAREE to every institution (tips, tool news, stories), with role/level/gap targeting. */
final class ContentController extends Controller
{
    public function index(PostService $posts): Response
    {
        $this->authorize('platform.content.publish');

        return Inertia::render('Platform/Content/Index', [
            'posts' => Post::query()->whereNull('tenant_id')->latest()->limit(50)->get()->map(fn ($p) => $p->only(['id', 'kind', 'title', 'status', 'published_at']) + $posts->stats($p)),
            'kinds' => PostService::KINDS,
        ]);
    }

    public function store(Request $request, PostService $posts): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', PostService::KINDS)], 'title' => ['required', 'string', 'max:190'], 'title_en' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:20000'], 'body_en' => ['nullable', 'string', 'max:20000'], 'cta_url' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published'], 'targeting' => ['nullable', 'array'],
        ]);
        $posts->save($request->user(), $data + ['tenant_id' => null]);

        return back();
    }
}
