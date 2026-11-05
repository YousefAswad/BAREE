<?php

namespace App\Http\Controllers\Learning;

use App\AI\Exceptions\AiPolicyViolation;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\Mission;
use App\Models\MissionAttempt;
use App\Services\Learning\GamificationService;
use App\Services\Learning\MissionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LabController extends Controller
{
    public function index(Request $request, GamificationService $gamification): Response
    {
        $user = $request->user();
        $completed = MissionAttempt::query()->where('user_id', $user->id)->where('status', 'completed')->pluck('score', 'mission_id');

        return Inertia::render('Lab/Index', [
            'paths' => LearningPath::query()->where('status', 'published')->with('missions')->get()
                ->filter(fn ($p) => empty($p->role_templates) || in_array($user->roleTemplateKey(), $p->role_templates, true))
                ->map(fn ($p) => ['key' => $p->key, 'title' => $p->title, 'description' => $p->description,
                    'missions' => $p->missions->where('status', 'published')->map(fn ($m) => ['key' => $m->key, 'title' => $m->title, 'minutes' => $m->minutes,
                        'xp' => $m->xp, 'difficulty' => $m->difficulty, 'score' => $completed[$m->id] ?? null])->values()])->values(),
            'xp' => $gamification->total($user),
            'badges' => $user->hasMany(\App\Models\UserBadge::class)->join('badges', 'badges.id', '=', 'user_badges.badge_id')->get(['badges.key', 'badges.name', 'badges.color']),
        ]);
    }

    public function show(Request $request, string $key, MissionService $missions): Response
    {
        $mission = Mission::query()->where('key', $key)->where('status', 'published')->firstOrFail();
        $attempt = $missions->start($request->user(), $mission);

        return Inertia::render('Lab/Mission', [
            'mission' => ['key' => $mission->key, 'title' => $mission->title, 'description' => $mission->description, 'minutes' => $mission->minutes, 'xp' => $mission->xp,
                'steps' => collect($mission->steps)->map(fn ($s) => ['key' => $s['key'], 'title' => $s['title'], 'instruction' => $s['instruction']]),
                'rubric' => $mission->rubric, 'dataset' => $mission->publicDataset()],
            'attempt' => ['id' => $attempt->id, 'status' => $attempt->status, 'step' => $attempt->step_index, 'score' => $attempt->score,
                'transcript' => collect($attempt->transcript ?? [])->map->only(['step', 'try', 'submission', 'feedback', 'hint', 'scores', 'check', 'ready'])],
        ]);
    }

    public function submit(Request $request, MissionAttempt $attempt, MissionService $missions): JsonResponse
    {
        $submission = $request->validate(['submission' => ['required', 'string', 'min:3', 'max:6000']])['submission'];

        try {
            $result = $missions->submit($request->user(), $attempt, $submission);
        } catch (AiPolicyViolation|DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['coach' => $result['coach'], 'check' => $result['check'], 'advanced' => $result['advanced'],
            'step' => $result['attempt']->step_index, 'status' => $result['attempt']->status, 'score' => $result['attempt']->score]);
    }
}
