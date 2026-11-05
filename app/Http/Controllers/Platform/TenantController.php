<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SupportSession;
use App\Models\Tenant;
use App\Services\CreditService;
use App\Services\Platform\TenantProvisioningService;
use App\Support\Audit;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('platform.tenants.manage');

        $tenants = Tenant::query()
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where('name', 'like', '%'.addcslashes($s, '%_').'%'))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->withCount('users')->latest()->paginate(25)->withQueryString();

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants->through(fn (Tenant $t) => $t->only(['public_id', 'name', 'type', 'status', 'country', 'users_count', 'trial_ends_at'])),
            'plans' => Plan::query()->where('active', true)->get(['key', 'name']),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function store(Request $request, TenantProvisioningService $provisioning): RedirectResponse
    {
        $this->authorize('platform.tenants.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:school,network,university,training_center,ngo,government'],
            'country' => ['nullable', 'string', 'size:2'],
            'plan_key' => ['required', 'exists:plans,key'],
            'owner_email' => ['required', 'email:rfc', 'max:190'],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'trial_credits' => ['nullable', 'integer', 'between:0,1000000'],
            'allowed_email_domains' => ['nullable', 'array'],
            'allowed_email_domains.*' => ['string', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);

        try {
            $tenant = $provisioning->provision($request->user(), $data);
        } catch (DomainException $e) {
            return back()->withErrors(['owner_email' => $e->getMessage()]);
        }

        return to_route('platform.tenants.show', $tenant->public_id)->with('status', __('Institution created and owner invited.'));
    }

    public function show(string $publicId, CreditService $credits): Response
    {
        $this->authorize('platform.tenants.manage');
        $tenant = Tenant::query()->where('public_id', $publicId)->withCount('users')->firstOrFail();

        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => $tenant->only(['public_id', 'name', 'type', 'status', 'country', 'users_count', 'trial_ends_at', 'settings']),
            'credits' => $credits->balance($tenant->id),
            'supportSessions' => SupportSession::query()->where('tenant_id', $tenant->id)->latest()->limit(10)->get(['id', 'reason', 'started_at', 'expires_at', 'ended_at']),
        ]);
    }

    public function updateStatus(Request $request, string $publicId, TenantProvisioningService $provisioning): RedirectResponse
    {
        $this->authorize('platform.tenants.manage');
        $data = $request->validate(['status' => ['required', 'in:trial,active,suspended,cancelled'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $provisioning->setStatus(Tenant::query()->where('public_id', $publicId)->firstOrFail(), $data['status'], $data['reason']);

        return back();
    }

    public function grantCredits(Request $request, string $publicId, CreditService $credits): RedirectResponse
    {
        $this->authorize('platform.plans.manage');
        $data = $request->validate(['amount' => ['required', 'integer', 'between:1,1000000'], 'reason' => ['required', 'in:top_up,adjustment,refund']]);
        $tenant = Tenant::query()->where('public_id', $publicId)->firstOrFail();

        $credits->grant($tenant->id, $data['amount'], $data['reason']);
        Audit::log('credits.granted', $tenant, $data);

        return back();
    }
}
