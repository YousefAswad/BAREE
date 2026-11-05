<?php

namespace App\Services\Content;

use App\Models\Enrollment;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use App\Services\Studio\ToolCatalog;

/** The personal home feed: targeted posts (institution + BAREE), due assignments and a tool suggestion. */
final class FeedService
{
    public function __construct(private TargetingMatcher $matcher, private ToolCatalog $catalog) {}

    public function forUser(User $user, int $limit = 20): array
    {
        $posts = Post::query()->where('status', 'published')->where('published_at', '<=', now())
            ->orderByRaw('CASE WHEN pinned_until IS NOT NULL AND pinned_until > ? THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('published_at')->limit(100)->get()
            ->filter(fn (Post $p) => $this->matcher->matches($user, $p->targeting))
            ->take($limit)->values();

        $seen = PostView::query()->where('user_id', $user->id)->whereIn('post_id', $posts->pluck('id'))->pluck('post_id')->flip();
        foreach ($posts as $post) {
            if (! isset($seen[$post->id])) {
                PostView::create(['user_id' => $user->id, 'post_id' => $post->id, 'viewed_at' => now()]);
            }
        }

        $due = Enrollment::query()->where('user_id', $user->id)->whereNull('completed_at')->where('mandatory', true)
            ->with('course:id,key,title')->orderBy('due_at')->limit(3)->get();

        return [
            'posts' => $posts->map(fn (Post $p) => ['id' => $p->id, 'kind' => $p->kind, 'title' => $p->title, 'body' => $p->body, 'cta_url' => $p->cta_url,
                'source' => $p->tenant_id ? 'institution' : 'baree', 'pinned' => $p->pinned_until?->isFuture() ?? false, 'published_at' => $p->published_at, 'new' => ! isset($seen[$p->id])]),
            'due' => $due->map(fn ($e) => ['course' => $e->course?->key, 'title' => $e->course?->title, 'due_at' => $e->due_at, 'progress' => $e->progress]),
            'suggestion' => optional($this->catalog->recommend($user, 1)->first(), fn ($r) => ['key' => $r['agent']->key, 'name' => $r['agent']->name, 'reason' => $r['reason']]),
        ];
    }

    public function click(User $user, Post $post): void
    {
        PostView::query()->updateOrCreate(['user_id' => $user->id, 'post_id' => $post->id], ['clicked_at' => now(), 'viewed_at' => now()]);
    }
}
