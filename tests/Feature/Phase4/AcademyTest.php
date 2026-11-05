<?php

require_once __DIR__.'/Phase4Helpers.php';

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Mission;
use App\Models\MissionAttempt;
use App\Services\Learning\CertificateService;
use App\Services\Learning\CourseService;

beforeEach(function () {
    $this->ahmad = learningSetup();
    $this->course = Course::where('key', 'ai_for_teachers')->first();
    $this->lessons = $this->course->lessons()->orderBy('course_modules.position')->orderBy('course_lessons.position')->get();
});

it('grades quizzes on the server and never sends the answer key', function () {
    $page = $this->actingAs($this->ahmad)->get('/academy/ai_for_teachers')->viewData('page');
    expect(json_encode($page['props']))->not->toContain('"answer"');

    $quiz = $this->lessons->firstWhere('type', 'quiz');
    $fail = app(CourseService::class)->completeLesson($this->ahmad, $quiz, [0, 0, 0]);
    $pass = app(CourseService::class)->completeLesson($this->ahmad, $quiz, [1, 1, 1]);

    expect($fail['passed'])->toBeFalse()->and($fail['score'])->toEqual(0)->and($pass['passed'])->toBeTrue();
});

it('requires the lab mission before a lab lesson counts', function () {
    $lab = $this->lessons->firstWhere('type', 'lab');
    expect(app(CourseService::class)->completeLesson($this->ahmad, $lab)['passed'])->toBeFalse();

    MissionAttempt::create(['mission_id' => $lab->mission_id, 'user_id' => $this->ahmad->id, 'status' => 'completed', 'score' => 90, 'completed_at' => now()]);
    expect(app(CourseService::class)->completeLesson($this->ahmad, $lab)['passed'])->toBeTrue();
});

it('issues a verifiable certificate at 100% and detects tampering', function () {
    MissionAttempt::create(['mission_id' => Mission::where('key', 'privacy_check')->value('id'), 'user_id' => $this->ahmad->id, 'status' => 'completed', 'score' => 90, 'completed_at' => now()]);
    $result = null;
    foreach ($this->lessons as $lesson) {
        $result = app(CourseService::class)->completeLesson($this->ahmad, $lesson, [1, 1, 1]);
    }

    expect($result['progress'])->toBe(100)->and($result['certificate'])->not->toBeNull();
    bareeTenantContext()->clear();
    $this->get("/certificates/{$result['certificate']}")->assertOk();
    expect(app(CertificateService::class)->verify($result['certificate'])['valid'])->toBeTrue();

    bareeTenantContext()->asPlatform(fn () => Certificate::where('serial', $result['certificate'])->update(['hours' => 40]));
    expect(app(CertificateService::class)->verify($result['certificate'])['valid'])->toBeFalse();
});

it('keeps role-restricted courses out of reach unless assigned', function () {
    $hr = \App\Models\User::factory()->forTenant($this->ahmad->tenant)->create();

    expect(fn () => app(CourseService::class)->enroll($hr, $this->course))->toThrow(DomainException::class)
        ->and(app(CourseService::class)->enroll($hr, $this->course, assignedBy: $this->ahmad, mandatory: true)->mandatory)->toBeTrue();
});
