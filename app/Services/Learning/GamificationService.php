<?php

namespace App\Services\Learning;

use App\Models\Badge;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\MissionAttempt;
use App\Models\Passport;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpEvent;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;

final class GamificationService
{
    /** Idempotent: the same key never pays twice (retries, double clicks, re-runs). */
    public function award(User $user, int $amount, string $reason, string $key): bool
    {
        try {
            XpEvent::create(['user_id' => $user->id, 'amount' => $amount, 'reason' => $reason, 'idempotency_key' => $key, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        Passport::query()->where('user_id', $user->id)->increment('xp', $amount);

        return true;
    }

    public function total(User $user): int
    {
        return (int) XpEvent::query()->where('user_id', $user->id)->sum('amount');
    }

    /** @return string[] keys of newly awarded badges */
    public function evaluateBadges(User $user): array
    {
        $owned = UserBadge::query()->where('user_id', $user->id)->pluck('badge_id')->flip();
        $new = [];

        foreach (Badge::query()->get() as $badge) {
            if (isset($owned[$badge->id]) || ! $this->meets($user, $badge->criteria)) {
                continue;
            }
            UserBadge::create(['user_id' => $user->id, 'badge_id' => $badge->id, 'awarded_at' => now()]);
            $this->award($user, 50, 'badge', "badge:{$badge->key}:{$user->id}");
            Audit::log('badge.awarded', $user, ['badge' => $badge->key]);
            $user->notify(new \App\Notifications\BadgeAwardedNotification($badge->key, $badge->name));
            $new[] = $badge->key;
        }

        return $new;
    }

    private function meets(User $user, array $criteria): bool
    {
        return match ($criteria['type'] ?? null) {
            'missions_completed' => MissionAttempt::query()->where('user_id', $user->id)->where('status', 'completed')->distinct('mission_id')->count('mission_id') >= (int) $criteria['count'],
            'path_completed' => $this->pathCompleted($user, (string) $criteria['path']),
            'competency' => (float) data_get(Passport::query()->where('user_id', $user->id)->value('competencies'), $criteria['key'], 0) >= (float) $criteria['min'],
            'course_completed' => Enrollment::query()->where('user_id', $user->id)->whereNotNull('completed_at')
                ->whereHas('course', fn ($q) => $q->where('key', $criteria['course']))->exists(),
            default => false,
        };
    }

    private function pathCompleted(User $user, string $pathKey): bool
    {
        $path = LearningPath::query()->where('key', $pathKey)->with('missions:id,path_id')->first();
        if (! $path || $path->missions->isEmpty()) {
            return false;
        }
        $done = MissionAttempt::query()->where('user_id', $user->id)->where('status', 'completed')->whereIn('mission_id', $path->missions->pluck('id'))->distinct('mission_id')->count('mission_id');

        return $done === $path->missions->count();
    }
}
