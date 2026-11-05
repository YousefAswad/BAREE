<?php

use App\Models\AssessmentAttempt;
use App\Models\Passport;
use App\Models\QuestionOption;
use App\Models\User;
use Database\Seeders\BareeSeeder;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    $this->ahmad = User::where('email', 'ahmad@future.test')->first();
});

it('takes the assessment end to end and builds the passport', function () {
    $this->actingAs($this->ahmad)->post(route('assessments.start', 'teacher_initial'))->assertRedirect();
    $attempt = bareeTenantContext()->runAs($this->ahmad->tenant_id, fn () => AssessmentAttempt::with('template.items')->first());

    $this->get(route('assessments.show', $attempt->public_id))->assertOk();

    foreach ($attempt->template->items as $item) {
        $best = QuestionOption::where('question_id', $item->question_id)->orderByDesc('score')->first();
        $this->post(route('assessments.answer', $attempt->public_id), [
            'question_id' => $item->question_id, 'option_id' => $best->id, 'confidence' => 3, 'time_ms' => 12000,
        ])->assertSessionHasNoErrors();
    }

    $this->post(route('assessments.submit', $attempt->public_id))->assertRedirect(route('assessments.show', $attempt->public_id));

    bareeTenantContext()->runAs($this->ahmad->tenant_id, function () use ($attempt) {
        $attempt->refresh();
        expect($attempt->status)->toBe('scored')
            ->and($attempt->overall_score)->toBe(100.0)
            ->and($attempt->result['level_key'])->toBe('champion')
            ->and(Passport::where('user_id', $this->ahmad->id)->value('readiness'))->toEqual(100);
    });
});

it('does not reveal option scores to the browser', function () {
    $this->actingAs($this->ahmad)->post(route('assessments.start', 'teacher_initial'));
    $attempt = bareeTenantContext()->runAs($this->ahmad->tenant_id, fn () => AssessmentAttempt::first());

    $page = $this->get(route('assessments.show', $attempt->public_id))->viewData('page');

    expect(json_encode($page['props']['sections']))->not->toContain('"score"')
        ->and($page['component'])->toBe('Assessment/Take');
});

it('blocks colleagues from answering someone else\'s attempt', function () {
    $this->actingAs($this->ahmad)->post(route('assessments.start', 'teacher_initial'));
    $attempt = bareeTenantContext()->runAs($this->ahmad->tenant_id, fn () => AssessmentAttempt::first());
    $admin = User::where('email', 'admin@future.test')->first(); // same institution

    $this->actingAs($admin)->post(route('assessments.answer', $attempt->public_id), ['question_id' => 1, 'option_id' => 1])
        ->assertForbidden();
});

it('cannot be submitted before every question is answered', function () {
    $this->actingAs($this->ahmad)->post(route('assessments.start', 'teacher_initial'));
    $attempt = bareeTenantContext()->runAs($this->ahmad->tenant_id, fn () => AssessmentAttempt::first());

    $this->post(route('assessments.submit', $attempt->public_id))->assertSessionHasErrors('submit');
});
