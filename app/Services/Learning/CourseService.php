<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\MissionAttempt;
use App\Models\User;
use App\Support\Audit;
use DomainException;

final class CourseService
{
    public const PASS_MARK = 70;

    public function __construct(private CertificateService $certificates, private GamificationService $gamification) {}

    public function enroll(User $user, Course $course, ?User $assignedBy = null, bool $mandatory = false, $dueAt = null): Enrollment
    {
        if ($course->role_templates && ! $assignedBy && ! in_array($user->roleTemplateKey(), $course->role_templates, true)) {
            throw new DomainException(__('This course is not available for your role.'));
        }

        return Enrollment::query()->firstOrCreate(['user_id' => $user->id, 'course_id' => $course->id],
            ['assigned_by' => $assignedBy?->id, 'mandatory' => $mandatory, 'due_at' => $dueAt]);
    }

    /** @return array{passed: bool, score: ?float, progress: int, certificate: ?string} */
    public function completeLesson(User $user, CourseLesson $lesson, array $answers = []): array
    {
        $course = Course::query()->findOrFail(\App\Models\CourseModule::query()->whereKey($lesson->module_id)->value('course_id'));
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->first() ?? $this->enroll($user, $course);

        [$passed, $score] = match ($lesson->type) {
            'quiz' => $this->gradeQuiz($lesson, $answers),
            'lab' => [MissionAttempt::query()->where('user_id', $user->id)->where('mission_id', $lesson->mission_id)->where('status', 'completed')->exists(), null],
            default => [true, null],
        };

        if ($passed) {
            LessonProgress::query()->updateOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id], ['score' => $score, 'completed_at' => now()]);
        }

        $total = $course->lessons()->count();
        $done = LessonProgress::query()->where('user_id', $user->id)->whereNotNull('completed_at')->whereIn('lesson_id', $course->lessons()->pluck('course_lessons.id'))->count();
        $progress = $total ? (int) floor(100 * $done / $total) : 0;
        $enrollment->update(['progress' => $progress]);

        $serial = null;
        if ($progress === 100 && $enrollment->completed_at === null) {
            $enrollment->update(['completed_at' => now()]);
            $this->gamification->award($user, (int) round($course->hours * 50), 'course', "course:{$course->id}:{$user->id}");
            if ($course->certificate) {
                $serial = $this->certificates->issue($user, $course)->serial;
            }
            $this->gamification->evaluateBadges($user);
            Audit::log('course.completed', $enrollment, ['course' => $course->key]);
        }

        return ['passed' => $passed, 'score' => $score, 'progress' => $progress, 'certificate' => $serial];
    }

    /** Server-side grading; the browser never receives the answer key. */
    private function gradeQuiz(CourseLesson $lesson, array $answers): array
    {
        $quiz = $lesson->quiz ?? [];
        if ($quiz === []) {
            return [true, null];
        }
        $correct = 0;
        foreach ($quiz as $i => $question) {
            $correct += (int) (isset($answers[$i]) && (int) $answers[$i] === (int) $question['answer']);
        }
        $score = round(100 * $correct / count($quiz), 2);

        return [$score >= self::PASS_MARK, $score];
    }
}
