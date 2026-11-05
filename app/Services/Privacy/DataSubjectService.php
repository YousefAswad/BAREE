<?php

namespace App\Services\Privacy;

use App\Models\AiRun;
use App\Models\AssessmentAttempt;
use App\Models\CompetencyScore;
use App\Models\Enrollment;
use App\Models\MissionAttempt;
use App\Models\Output;
use App\Models\PhoneLink;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\Workspace;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A person's right to see and take their data, and to leave.
 * Export: everything about them in one JSON. Erasure: personal data removed, institution records anonymised
 * (outputs they made for the school stay with the school, without their name).
 */
final class DataSubjectService
{
    public function export(User $user): array
    {
        Audit::log('privacy.data_exported', $user);

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => $user->only(['name', 'email', 'locale', 'profile', 'created_at', 'last_active_at']),
            'passport' => $user->passport?->only(['readiness', 'competencies', 'xp', 'refreshed_at']),
            'consents' => UserConsent::query()->where('user_id', $user->id)->get(['key', 'granted', 'decided_at']),
            'assessments' => AssessmentAttempt::query()->where('user_id', $user->id)->get(['public_id', 'status', 'overall_score', 'submitted_at', 'result']),
            'evidence' => CompetencyScore::query()->where('user_id', $user->id)->get(['source', 'score', 'recorded_at']),
            'missions' => MissionAttempt::query()->where('user_id', $user->id)->get(['mission_id', 'status', 'score', 'completed_at']),
            'courses' => Enrollment::query()->where('user_id', $user->id)->get(['course_id', 'progress', 'completed_at']),
            'ai_runs' => AiRun::query()->where('user_id', $user->id)->latest()->limit(500)->get(['public_id', 'status', 'provider', 'credits', 'created_at']),
            'workspaces' => Workspace::query()->where('owner_id', $user->id)->get(['id', 'title', 'kind', 'created_at']),
            'outputs' => Output::query()->where('created_by', $user->id)->get(['id', 'title', 'type', 'version', 'created_at']),
        ];
    }

    public function erase(User $actor, User $user): void
    {
        abort_unless($actor->id === $user->id || $actor->hasPermission('users.manage'), 403);

        DB::transaction(function () use ($user) {
            PhoneLink::query()->where('user_id', $user->id)->delete();
            UserConsent::query()->where('user_id', $user->id)->delete();
            AiRun::query()->where('user_id', $user->id)->update(['output' => null, 'redaction_map' => null]);
            CompetencyScore::query()->where('user_id', $user->id)->delete();
            $user->passport()->delete();
            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'مستخدم محذوف', 'email' => 'deleted+'.Str::ulid().'@invalid.baree', 'password' => Str::random(64),
                'profile' => null, 'status' => 'disabled', 'remember_token' => null, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            ])->save();
        });

        Audit::log('privacy.user_erased', $user);
    }
}
