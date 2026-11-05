<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCampaign;
use App\Models\AssessmentTemplate;
use App\Models\Department;
use App\Models\Segment;
use App\Services\Institution\AnalyticsService;
use App\Services\Institution\AudienceResolver;
use App\Services\Institution\CampaignService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CampaignController extends Controller
{
    public function index(): Response
    {
        $this->authorize('assessments.campaigns.manage');

        return Inertia::render('Institution/Campaigns/Index', [
            'campaigns' => AssessmentCampaign::query()->with('template:id,name')->latest()->get(),
            'templates' => AssessmentTemplate::query()->where('status', 'published')->get(['id', 'key', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'segments' => Segment::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function audienceCount(Request $request, AudienceResolver $audiences): JsonResponse
    {
        $this->authorize('assessments.campaigns.manage');

        return response()->json(['count' => $audiences->count(array_filter($request->only(['departments', 'segments', 'role_templates', 'campuses'])), $request->user()->tenant_id)]);
    }

    public function store(Request $request, CampaignService $campaigns): RedirectResponse
    {
        $this->authorize('assessments.campaigns.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'template_id' => ['required', 'integer'],
            'departments' => ['nullable', 'array'], 'departments.*' => ['integer'],
            'segments' => ['nullable', 'array'], 'segments.*' => ['integer'],
            'role_templates' => ['nullable', 'array'], 'role_templates.*' => ['string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        try {
            $campaign = $campaigns->create($request->user(), $data);
        } catch (DomainException $e) {
            return back()->withErrors(['audience' => $e->getMessage()]);
        }

        return to_route('admin.campaigns.show', $campaign);
    }

    public function show(AssessmentCampaign $campaign, AnalyticsService $analytics): Response
    {
        $this->authorize('assessments.campaigns.manage');

        return Inertia::render('Institution/Campaigns/Show', [
            'campaign' => $campaign->load('template:id,name')->only(['id', 'name', 'status', 'starts_at', 'ends_at', 'template']),
            'progress' => $analytics->campaignProgress($campaign),
            'minGroup' => (int) config('baree.privacy.min_group_size'),
        ]);
    }

    public function launch(AssessmentCampaign $campaign, CampaignService $campaigns): RedirectResponse
    {
        $this->authorize('assessments.campaigns.manage');

        try {
            $campaigns->launch($campaign);
        } catch (DomainException $e) {
            return back()->withErrors(['campaign' => $e->getMessage()]);
        }

        return back();
    }
}
