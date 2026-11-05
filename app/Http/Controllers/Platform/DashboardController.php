<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformMetrics;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(PlatformMetrics $metrics): Response
    {
        return Inertia::render('Platform/Dashboard', ['metrics' => $metrics->overview()]);
    }
}
