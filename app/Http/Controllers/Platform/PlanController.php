<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Plans are data: limits, price per seat and features. Existing subscriptions keep their price until renewal. */
final class PlanController extends Controller
{
    public function index(): Response
    {
        $this->authorize('platform.plans.manage');

        return Inertia::render('Platform/Plans/Index', ['plans' => Plan::query()->orderBy('price_per_seat_cents')->get()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('platform.plans.manage');
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:60'], 'name_en' => ['required', 'string', 'max:60'],
            'price_per_seat_cents' => ['required', 'integer', 'between:0,10000000'], 'currency' => ['required', 'string', 'size:3'], 'interval' => ['required', 'in:month,year'],
            'limits' => ['required', 'array'], 'limits.users' => ['nullable', 'integer', 'min:1'], 'limits.monthly_credits' => ['nullable', 'integer', 'min:0'],
            'limits.storage_gb' => ['nullable', 'integer', 'min:1'], 'limits.custom_agents' => ['boolean'], 'limits.media' => ['boolean'], 'limits.api' => ['boolean'],
            'active' => ['required', 'boolean'],
        ]);

        $plan->update(['name' => ['ar' => $data['name_ar'], 'en' => $data['name_en']], 'price_per_seat_cents' => $data['price_per_seat_cents'],
            'currency' => strtoupper($data['currency']), 'interval' => $data['interval'], 'limits' => $data['limits'], 'active' => $data['active']]);
        Audit::log('plan.updated', $plan, ['price' => $plan->price_per_seat_cents]);

        return back();
    }
}
