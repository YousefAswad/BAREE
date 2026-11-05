<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Services\Operations\InstitutionAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AnalyticsController extends Controller
{
    public function __invoke(Request $request, InstitutionAnalytics $analytics): Response
    {
        $this->authorize('analytics.view_aggregate');

        return Inertia::render('Institution/Analytics/Index', $analytics->overview($request->user()->tenant_id) + ['minGroup' => (int) config('baree.privacy.min_group_size')]);
    }
}
