<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LearningPath;
use App\Models\Mission;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Authoring for BAREE's global courses and lab missions. New items start as drafts. */
final class AuthoringController extends Controller
{
    public function course(string $key): Response
    {
        $this->authorize('platform.content.publish');
        $course = Course::query()->whereNull('tenant_id')->where('key', $key)->with('modules.lessons')->firstOrFail();

        return Inertia::render('Platform/Learning/Course', [
            'course' => $course->only(['id', 'key', 'title', 'description', 'hours', 'certificate', 'status', 'role_templates']),
            'modules' => $course->modules->map(fn ($m) => ['id' => $m->id, 'title' => $m->title, 'lessons' => $m->lessons->map(fn (CourseLesson $l) => $l->makeVisible('quiz')
                ->only(['id', 'type', 'title', 'body', 'video_url', 'mission_id', 'quiz', 'minutes']))]),
            'missions' => Mission::query()->whereNull('tenant_id')->get(['id', 'key', 'title']),
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $data = $request->validate(['key' => ['required', 'regex:/^[a-z][a-z0-9_]{2,60}$/', 'unique:courses,key'], 'title' => ['required', 'string', 'max:160'],
            'hours' => ['required', 'numeric', 'between:0.25,200'], 'role_templates' => ['nullable', 'array']]);
        $course = Course::create(['tenant_id' => null, 'key' => $data['key'], 'title' => ['ar' => $data['title']], 'hours' => $data['hours'],
            'role_templates' => $data['role_templates'] ?? null, 'certificate' => true, 'status' => 'draft']);
        Audit::log('course.created', $course);

        return to_route('platform.authoring.course', $course->key);
    }

    public function storeModule(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $title = $request->validate(['title' => ['required', 'string', 'max:160']])['title'];
        $course->modules()->create(['position' => (int) $course->modules()->max('position') + 1, 'title' => ['ar' => $title]]);

        return back();
    }

    public function storeLesson(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $data = $request->validate([
            'module_id' => ['required', 'integer'], 'type' => ['required', 'in:reading,video,quiz,lab'], 'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:60000'], 'video_url' => ['nullable', 'url:https', 'max:500'], 'mission_id' => ['nullable', 'exists:missions,id'],
            'quiz' => ['nullable', 'array', 'max:30'], 'quiz.*.q' => ['required', 'string', 'max:600'], 'quiz.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'quiz.*.answer' => ['required', 'integer', 'min:0'], 'minutes' => ['required', 'integer', 'between:1,180'],
        ]);
        $module = $course->modules()->findOrFail($data['module_id']);
        foreach ($data['quiz'] ?? [] as $i => $q) {
            if ($q['answer'] >= count($q['options'])) {
                throw ValidationException::withMessages(["quiz.{$i}.answer" => __('The answer must be one of the options.')]);
            }
        }
        if ($data['type'] === 'lab' && empty($data['mission_id'])) {
            throw ValidationException::withMessages(['mission_id' => __('A lab lesson needs a mission.')]);
        }

        $module->lessons()->create(['position' => (int) $module->lessons()->max('position') + 1, 'type' => $data['type'], 'title' => ['ar' => $data['title']],
            'body' => $data['body'] ?? null, 'video_url' => $data['video_url'] ?? null, 'mission_id' => $data['mission_id'] ?? null, 'quiz' => $data['quiz'] ?? null, 'minutes' => $data['minutes']]);

        return back();
    }

    public function storeMission(Request $request): RedirectResponse
    {
        $this->authorize('platform.content.publish');
        $data = $request->validate([
            'key' => ['required', 'regex:/^[a-z][a-z0-9_]{2,60}$/', 'unique:missions,key'], 'path_id' => ['nullable', 'exists:learning_paths,id'],
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'],
            'xp' => ['required', 'integer', 'between:10,1000'], 'minutes' => ['required', 'integer', 'between:5,120'], 'difficulty' => ['required', 'integer', 'between:1,5'],
            'steps' => ['required', 'array', 'min:1', 'max:8'], 'steps.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]{1,30}$/'],
            'steps.*.title' => ['required', 'string', 'max:120'], 'steps.*.instruction' => ['required', 'string', 'max:1200'],
            'rubric' => ['required', 'array', 'min:1', 'max:6'], 'rubric.*.criterion' => ['required', 'regex:/^[a-z_]{3,30}$/'], 'rubric.*.weight' => ['required', 'integer', 'between:1,100'],
            'competency_impact' => ['required', 'array', 'min:1'],
            'dataset' => ['nullable', 'array'], 'dataset.table' => ['nullable', 'array', 'max:200'], 'dataset.answers' => ['nullable', 'array'],
        ]);

        $mission = Mission::create([
            'tenant_id' => null, 'key' => $data['key'], 'path_id' => $data['path_id'] ?? null,
            'position' => (int) Mission::query()->where('path_id', $data['path_id'] ?? null)->max('position') + 1,
            'title' => ['ar' => $data['title']], 'description' => ['ar' => $data['description'] ?? ''], 'xp' => $data['xp'], 'minutes' => $data['minutes'],
            'difficulty' => $data['difficulty'], 'status' => 'draft', 'rubric' => $data['rubric'], 'competency_impact' => $data['competency_impact'], 'dataset' => $data['dataset'] ?? null,
            'agent_id' => \App\Models\Agent::query()->whereNull('tenant_id')->where('key', 'lab_coach')->value('id'),
            'steps' => array_map(fn ($s) => ['key' => $s['key'], 'title' => ['ar' => $s['title']], 'instruction' => ['ar' => $s['instruction']]], $data['steps']),
        ]);
        Audit::log('mission.created', $mission);

        return back()->with('status', __('Mission saved as draft. Publish it from the list after a test run.'));
    }
}
