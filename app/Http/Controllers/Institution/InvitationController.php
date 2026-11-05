<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Institution\InvitationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InvitationController extends Controller
{
    public function store(Request $request, InvitationService $invitations): RedirectResponse
    {
        $this->authorize('users.invite');
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'job_title_id' => ['nullable', Rule::exists('job_titles', 'id')->where('tenant_id', $tenantId)],
            'role_key' => ['required', 'string', 'max:64'],
        ]);

        try {
            $invitations->invite($request->user(), $data);
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('status', __('Invitation sent.'));
    }

    public function destroy(Invitation $invitation, InvitationService $invitations): RedirectResponse
    {
        $this->authorize('users.invite');
        $invitations->revoke($invitation); // tenant scope already guarantees it is ours

        return back();
    }
}
