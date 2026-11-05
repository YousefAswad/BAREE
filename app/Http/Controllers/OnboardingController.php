<?php

namespace App\Http\Controllers;

use App\Models\AssessmentTemplate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OnboardingController extends Controller
{
    public function profile(Request $request): Response
    {
        $user = $request->user()->load(['department:id,name', 'jobTitle:id,name,role_template_key']);

        return Inertia::render('Onboarding/Profile', [
            'user' => ['name' => $user->name, 'department' => $user->department?->name, 'job_title' => $user->jobTitle?->name, 'profile' => $user->profile ?? []],
            'isTeacher' => $user->roleTemplateKey() === 'teacher',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'years' => ['nullable', 'in:0-2,3-7,8-15,15+'],
            'grades' => ['nullable', 'array'], 'grades.*' => ['integer', 'between:1,12'],
            'subjects' => ['nullable', 'array', 'max:6'], 'subjects.*' => ['string', 'max:60'],
            'responsibilities' => ['nullable', 'array', 'max:8'], 'responsibilities.*' => ['string', 'max:60'],
            'ai_usage' => ['nullable', 'in:never,sometimes,weekly,daily'],
            'locale' => ['required', 'in:ar,en'],
        ]);

        $user = $request->user();
        $user->update(['profile' => array_merge($user->profile ?? [], collect($data)->except('locale')->all()), 'locale' => $data['locale']]);
        Audit::log('onboarding.profile_saved', $user);

        // Pick the assessment for the user's role template, falling back to the general one.
        $template = AssessmentTemplate::query()->where('status', 'published')->get()
            ->first(fn ($t) => in_array($user->roleTemplateKey(), $t->target_roles ?? [], true))
            ?? AssessmentTemplate::query()->where('status', 'published')->first();

        return $template ? redirect()->route('assessments.intro', $template->key) : redirect('/');
    }

    public function intro(Request $request, string $key): Response
    {
        $template = AssessmentTemplate::query()->where('key', $key)->where('status', 'published')->firstOrFail();

        return Inertia::render('Assessment/Intro', [
            'template' => $template->only(['key', 'name', 'description', 'time_limit_minutes']),
            'questions' => $template->items()->count(),
            // Tell people honestly who will see their result before they start.
            'privacyMode' => $request->user()->tenant?->setting('privacy.mode', config('baree.privacy.default_mode')),
        ]);
    }
}
