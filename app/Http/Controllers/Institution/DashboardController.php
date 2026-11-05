<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\AssessmentCampaign;
use App\Models\User;
use App\Services\CreditService;
use App\Services\Institution\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics, CreditService $credits): Response
    {
        $this->authorize('analytics.view_aggregate');
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Institution/Dashboard', [
            'people' => [
                'total' => User::query()->where('tenant_id', $tenantId)->count(),
                'active30' => User::query()->where('tenant_id', $tenantId)->where('last_active_at', '>=', now()->subDays(30))->count(),
            ],
            'levels' => $analytics->levelDistribution(),
            'departments' => $analytics->readinessByDepartment(),
            'credits' => $request->user()->can('credits.view') ? $credits->balance($tenantId) : null,
            'campaigns' => AssessmentCampaign::query()->whereIn('status', ['scheduled', 'active'])->latest()->limit(5)->get(['id', 'name', 'status', 'ends_at']),
        ]);
    }
}
