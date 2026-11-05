<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\ProtectedTerm;
use App\Models\Tenant;
use App\Services\Institution\ProtectedTermsRepository;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PrivacyController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorize('tenant.settings.manage');
        $tenant = Tenant::query()->findOrFail($request->user()->tenant_id);

        return Inertia::render('Institution/Privacy/Edit', [
            'mode' => $tenant->setting('privacy.mode', config('baree.privacy.default_mode')),
            'leaderboards' => (bool) $tenant->setting('leaderboards', false),
            'allowedDomains' => $tenant->setting('allowed_email_domains', []),
            'protectedTermsCount' => ProtectedTerm::query()->count(), // the terms themselves are never sent to the browser
            'minGroup' => (int) config('baree.privacy.min_group_size'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('tenant.settings.manage');
        $data = $request->validate([
            'mode' => ['required', 'in:aggregate,consent,department_heads'],
            'leaderboards' => ['required', 'boolean'],
            'allowed_email_domains' => ['nullable', 'array'], 'allowed_email_domains.*' => ['regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);

        $tenant = Tenant::query()->findOrFail($request->user()->tenant_id);
        $settings = $tenant->settings ?? [];
        data_set($settings, 'privacy.mode', $data['mode']);
        data_set($settings, 'leaderboards', $data['leaderboards']);
        data_set($settings, 'allowed_email_domains', $data['allowed_email_domains'] ?? []);
        $tenant->update(['settings' => $settings]);
        Audit::log('privacy.settings_updated', $tenant, ['mode' => $data['mode'], 'leaderboards' => $data['leaderboards']]);

        return back()->with('status', __('Saved.'));
    }

    public function storeTerms(Request $request, ProtectedTermsRepository $terms): RedirectResponse
    {
        $this->authorize('tenant.settings.manage');
        $request->validate(['terms' => ['nullable', 'string', 'max:200000'], 'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048']]);

        $added = $request->hasFile('file')
            ? $terms->importRoster($request->file('file')->get())
            : $terms->add(preg_split('/\r\n|\r|\n|،|,/', (string) $request->input('terms')));

        return back()->with('status', __(':n names added to the protection list.', ['n' => $added]));
    }
}
