<?php

namespace App\Services\Content;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use App\Support\Audit;
use InvalidArgumentException;

final class PostService
{
    public const KINDS = ['article', 'tip', 'video', 'news', 'tool', 'story', 'event', 'policy'];

    public function __construct(private TargetingMatcher $matcher) {}

    public function save(User $author, array $data, ?Post $post = null): Post
    {
        $targeting = $this->cleanTargeting($data['targeting'] ?? []);
        if (! in_array($data['kind'], self::KINDS, true)) {
            throw new InvalidArgumentException('Unknown kind.');
        }

        $attributes = [
            'kind' => $data['kind'], 'title' => ['ar' => $data['title'], 'en' => $data['title_en'] ?? null],
            'body' => ['ar' => $data['body'] ?? '', 'en' => $data['body_en'] ?? null], 'targeting' => $targeting,
            'cta_url' => $this->safeUrl($data['cta_url'] ?? null), 'pinned_until' => $data['pinned_until'] ?? null,
            'status' => isset($data['publish_at']) && now()->lt($data['publish_at']) ? 'scheduled' : ($data['status'] ?? 'draft'),
            'published_at' => $data['publish_at'] ?? (($data['status'] ?? null) === 'published' ? now() : null),
        ];

        $post ??= new Post(['author_id' => $author->id]);
        $post->fill($attributes)->save();
        Audit::log('post.saved', $post, ['status' => $post->status]);

        return $post;
    }

    /** Estimated reach inside the current institution (aggregate count only). */
    public function estimate(int $tenantId, array $targeting): int
    {
        $targeting = $this->cleanTargeting($targeting);
        $count = 0;
        User::query()->where('tenant_id', $tenantId)->where('status', 'active')->chunkById(500, function ($users) use ($targeting, &$count) {
            $count += $users->filter(fn ($u) => $this->matcher->matches($u, $targeting))->count();
        });

        return $count;
    }

    public function stats(Post $post): array
    {
        $views = PostView::query()->where('post_id', $post->id);

        return ['views' => (clone $views)->count(), 'clicks' => (clone $views)->whereNotNull('clicked_at')->count()];
    }

    private function cleanTargeting(array $t): array
    {
        return array_filter([
            'roles' => array_values(array_map('strval', $t['roles'] ?? [])),
            'levels' => array_values(array_map('intval', $t['levels'] ?? [])),
            'departments' => array_values(array_map('intval', $t['departments'] ?? [])),
            'segments' => array_values(array_map('intval', $t['segments'] ?? [])),
            'campuses' => array_values(array_map('intval', $t['campuses'] ?? [])),
            'gap' => ! empty($t['gap']['competency']) ? ['competency' => (string) $t['gap']['competency'], 'below' => (float) ($t['gap']['below'] ?? 60)] : null,
        ]);
    }

    /** Only https links or internal paths: no javascript:, data: or open-redirect tricks. */
    private function safeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }
}
