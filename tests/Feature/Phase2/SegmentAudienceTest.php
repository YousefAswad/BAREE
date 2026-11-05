<?php

use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Institution\AudienceResolver;
use App\Services\Institution\SegmentEvaluator;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    bareeTenantContext()->set($this->tenant->id);
    $this->math = Department::create(['name' => 'Math']);
    $this->hr = Department::create(['name' => 'HR']);
    $teacher = JobTitle::create(['name' => 'معلّم', 'role_template_key' => 'teacher']);
    $officer = JobTitle::create(['name' => 'موظف موارد', 'role_template_key' => 'hr']);

    $this->t7 = User::factory()->forTenant($this->tenant)->create(['department_id' => $this->math->id, 'job_title_id' => $teacher->id, 'profile' => ['grades' => [7, 8]]]);
    $this->t3 = User::factory()->forTenant($this->tenant)->create(['department_id' => $this->math->id, 'job_title_id' => $teacher->id, 'profile' => ['grades' => [3]]]);
    $this->h = User::factory()->forTenant($this->tenant)->create(['department_id' => $this->hr->id, 'job_title_id' => $officer->id]);
});

it('matches rule-based segments and syncs members', function () {
    $segment = Segment::create(['name' => 'معلّمي ٧–٩', 'mode' => 'rules', 'rules' => ['all' => [
        ['field' => 'role_template', 'op' => 'in', 'value' => ['teacher']],
        ['field' => 'grades', 'op' => 'overlaps', 'value' => [7, 8, 9]],
    ]]]);

    expect(app(SegmentEvaluator::class)->sync($segment))->toBe(1)
        ->and($segment->users()->pluck('users.id')->all())->toBe([$this->t7->id]);
});

it('supports nested any/all groups and rejects unknown fields', function () {
    $evaluator = app(SegmentEvaluator::class);
    $rules = ['any' => [['field' => 'role_template', 'op' => 'in', 'value' => ['hr']], ['all' => [['field' => 'grades', 'op' => 'overlaps', 'value' => [3]]]]]];

    expect($evaluator->matches($this->h, $rules))->toBeTrue()
        ->and($evaluator->matches($this->t3, $rules))->toBeTrue()
        ->and($evaluator->matches($this->t7, $rules))->toBeFalse()
        ->and(fn () => $evaluator->validate(['all' => [['field' => 'password', 'op' => 'eq', 'value' => 'x']]]))->toThrow(InvalidArgumentException::class);
});

it('resolves audiences with OR inside a key and AND across keys', function () {
    $resolver = app(AudienceResolver::class);

    expect($resolver->count(['departments' => [$this->math->id, $this->hr->id]], $this->tenant->id))->toBe(3)
        ->and($resolver->count(['departments' => [$this->math->id, $this->hr->id], 'role_templates' => ['hr']], $this->tenant->id))->toBe(1)
        ->and($resolver->count([], $this->tenant->id))->toBe(3);
});
