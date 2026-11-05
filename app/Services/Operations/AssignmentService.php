<?php

namespace App\Services\Operations;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionAttempt;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Services\Institution\AudienceResolver;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Assign courses or lab missions to an audience, optionally mandatory with a due date.
 * Compliance for mandatory training is administrative (who finished), not assessment results:
 * people with missions.assign may see names for these; scores are never shown here.
 */
final class AssignmentService
{
    public function __construct(private AudienceResolver $audiences) {}

    public function assign(User $actor, string $type, int $id, array $audience, bool $mandatory, ?\Carbon\CarbonInterface $dueAt): int
    {
        $users = $this->audiences->query($audience, $actor->tenant_id)->get();
        if ($users->isEmpty()) {
            throw new DomainException(__('This audience has no active users.'));
        }

        if ($type === 'course') {
            $course = Course::query()->where('status', 'published')->findOrFail($id);
            foreach ($users as $user) {
                Enrollment::query()->updateOrCreate(['user_id' => $user->id, 'course_id' => $course->id],
                    ['assigned_by' => $actor->id, 'mandatory' => $mandatory, 'due_at' => $dueAt]);
            }
            $title = $course->title['ar'] ?? $course->key;
            $url = '/academy/'.$course->key;
        } elseif ($type === 'mission') {
            $mission = Mission::query()->where('status', 'published')->findOrFail($id);
            MissionAssignment::create(['mission_id' => $mission->id, 'audience' => $audience, 'mandatory' => $mandatory, 'due_at' => $dueAt, 'assigned_by' => $actor->id]);
            $title = $mission->title['ar'] ?? $mission->key;
            $url = '/lab/missions/'.$mission->key;
        } else {
            throw new DomainException('Unknown assignment type.');
        }

        Notification::send($users, new AssignmentNotification('assigned', $title, $url, $dueAt?->toDateString(), $mandatory));
        Audit::log('assignment.created', $actor, ['type' => $type, 'id' => $id, 'users' => $users->count(), 'mandatory' => $mandatory]);

        return $users->count();
    }

    /** Mandatory course compliance: who completed, who is overdue. */
    public function courseCompliance(Course $course): array
    {
        $rows = Enrollment::query()->where('course_id', $course->id)->where('mandatory', true)
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->get(['users.public_id', 'users.name', 'users.department_id', 'enrollments.progress', 'enrollments.due_at', 'enrollments.completed_at']);

        return [
            'total' => $rows->count(),
            'completed' => $rows->whereNotNull('completed_at')->count(),
            'overdue' => $rows->whereNull('completed_at')->filter(fn ($r) => $r->due_at && Carbon::parse($r->due_at)->isPast())->values()
                ->map(fn ($r) => ['id' => $r->public_id, 'name' => $r->name, 'progress' => $r->progress, 'due_at' => $r->due_at]),
        ];
    }

    /** Daily: 3-day reminder and weekly overdue notices for mandatory courses (each sent once per period). */
    public function sendReminders(): int
    {
        $sent = 0;
        $due = Enrollment::query()->where('mandatory', true)->whereNull('completed_at')->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays(3))->with('course:id,key,title')->get();

        foreach ($due as $enrollment) {
            $overdue = $enrollment->due_at->isPast();
            $key = 'remind:'.$enrollment->id.':'.($overdue ? 'over:'.now()->format('o-W') : 'soon');
            if (! cache()->add($key, true, now()->addDays(8))) {
                continue;
            }
            $user = User::query()->find($enrollment->user_id);
            $user?->notify(new AssignmentNotification($overdue ? 'overdue' : 'reminder', $enrollment->course->title['ar'] ?? '', '/academy/'.$enrollment->course->key, $enrollment->due_at->toDateString(), true));
            $sent++;
        }

        return $sent;
    }

    /** Mission assignments are evaluated against attempts at read time. */
    public function missionProgress(MissionAssignment $assignment, int $tenantId): array
    {
        $users = $this->audiences->query($assignment->audience ?? [], $tenantId)->pluck('id');
        $done = MissionAttempt::query()->where('mission_id', $assignment->mission_id)->where('status', 'completed')->whereIn('user_id', $users)->distinct('user_id')->count('user_id');

        return ['total' => $users->count(), 'completed' => $done];
    }
}
