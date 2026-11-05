<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\Segment;
use App\Services\Institution\AudienceResolver;
use App\Services\Operations\AssignmentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class AssignmentController extends Controller
{
    public function index(Request $request, AssignmentService $assignments): Response
    {
        $this->authorize('missions.assign');
        $tenantId = $request->user()->tenant_id;
        $assignedCourses = Course::query()->whereIn('id', \App\Models\Enrollment::query()->where('mandatory', true)->distinct()->pluck('course_id'))->get();

        return Inertia::render('Institution/Assignments/Index', [
            'courses' => Course::query()->where('status', 'published')->get(['id', 'key', 'title', 'hours']),
            'missions' => Mission::query()->where('status', 'published')->get(['id', 'key', 'title', 'minutes']),
            'departments' => Department::query()->get(['id', 'name']),
            'segments' => Segment::query()->get(['id', 'name']),
            'compliance' => $assignedCourses->map(fn ($c) => ['course' => $c->title] + $assignments->courseCompliance($c)),
            'missionAssignments' => MissionAssignment::query()->latest()->limit(20)->get()
                ->map(fn ($a) => ['id' => $a->id, 'mission' => Mission::query()->whereKey($a->mission_id)->value('title'), 'due_at' => $a->due_at, 'mandatory' => $a->mandatory] + $assignments->missionProgress($a, $tenantId)),
        ]);
    }

    public function store(Request $request, AssignmentService $assignments): RedirectResponse
    {
        $this->authorize('missions.assign');
        $data = $request->validate([
            'type' => ['required', 'in:course,mission'], 'id' => ['required', 'integer'],
            'departments' => ['nullable', 'array'], 'departments.*' => ['integer'],
            'segments' => ['nullable', 'array'], 'segments.*' => ['integer'],
            'role_templates' => ['nullable', 'array'], 'role_templates.*' => ['string'],
            'mandatory' => ['required', 'boolean'], 'due_at' => ['nullable', 'date', 'after:today'],
        ]);

        try {
            $count = $assignments->assign($request->user(), $data['type'], $data['id'],
                array_filter(['departments' => $data['departments'] ?? [], 'segments' => $data['segments'] ?? [], 'role_templates' => $data['role_templates'] ?? []]),
                $data['mandatory'], isset($data['due_at']) ? Carbon::parse($data['due_at'])->endOfDay() : null);
        } catch (DomainException $e) {
            return back()->withErrors(['assignment' => $e->getMessage()]);
        }

        return back()->with('status', __('Assigned to :n people.', ['n' => $count]));
    }
}
