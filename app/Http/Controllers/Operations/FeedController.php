<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Content\FeedService;
use App\Services\Content\TargetingMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FeedController extends Controller
{
    public function index(Request $request, FeedService $feed): Response
    {
        $user = $request->user()->load('passport.level');

        return Inertia::render('Feed/Index', $feed->forUser($request->user()) + [
            'passport' => $user->passport ? ['readiness' => $user->passport->readiness, 'level' => $user->passport->level?->name, 'next' => $user->passport->next_level] : null,
        ]);
    }

    public function click(Request $request, Post $post, FeedService $feed, TargetingMatcher $matcher): JsonResponse
    {
        abort_unless($post->status === 'published' && $matcher->matches($request->user(), $post->targeting), 404);
        $feed->click($request->user(), $post);

        return response()->json(['url' => $post->cta_url]);
    }
}
