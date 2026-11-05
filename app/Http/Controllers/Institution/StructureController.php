<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Department;
use App\Models\JobTitle;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class StructureController extends Controller
{
    public const ROLE_TEMPLATES = ['teacher', 'department_head', 'curriculum', 'leadership', 'hr', 'finance', 'administration', 'student_affairs', 'communications', 'it', 'quality', 'training', 'admissions'];

    public function index(): Response
    {
        $this->authorize('structure.manage');

        return Inertia::render('Institution/Structure/Index', [
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'name', 'city']),
            'departments' => Department::query()->withCount('users')->orderBy('name')->get(['id', 'name', 'kind', 'campus_id', 'parent_id']),
            'jobTitles' => JobTitle::query()->orderBy('name')->get(['id', 'name', 'role_template_key']),
            'roleTemplates' => self::ROLE_TEMPLATES,
        ]);
    }

    public function storeCampus(Request $request): RedirectResponse
    {
        $this->authorize('structure.manage');
        $campus = Campus::create($request->validate(['name' => ['required', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:80']]));
        Audit::log('structure.campus_created', $campus);

        return back();
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $this->authorize('structure.manage');
        $tenantId = $request->user()->tenant_id;
        $department = Department::create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:academic,administrative'],
            'campus_id' => ['nullable', Rule::exists('campuses', 'id')->where('tenant_id', $tenantId)],
            'parent_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
        ]));
        Audit::log('structure.department_created', $department);

        return back();
    }

    public function storeJobTitle(Request $request): RedirectResponse
    {
        $this->authorize('structure.manage');
        $title = JobTitle::create($request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('job_titles')->where('tenant_id', $request->user()->tenant_id)],
            'role_template_key' => ['required', Rule::in(self::ROLE_TEMPLATES)],
        ]));
        Audit::log('structure.job_title_created', $title);

        return back();
    }
}
