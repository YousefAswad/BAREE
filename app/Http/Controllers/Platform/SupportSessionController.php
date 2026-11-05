<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SupportSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\SupportSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SupportSessionController extends Controller
{
    public function store(Request $request, string $publicId, SupportSessionService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        try {
            $session = $service->start($request->user(), Tenant::query()->where('public_id', $publicId)->firstOrFail(), $data['reason']);
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return to_route('platform.support.users', $session);
    }

    public function destroy(SupportSession $supportSession, SupportSessionService $service): RedirectResponse
    {
        abort_unless($supportSession->staff_id === auth()->id(), 403);
        $service->end($supportSession);

        return to_route('platform.tenants.index');
    }

    /** Read-only view of the institution's users while the session is active (see ReadOnlySupportSession). */
    public function users(SupportSession $supportSession): Response
    {
        return Inertia::render('Platform/Support/Users', [
            'session' => $supportSession->only(['id', 'reason', 'expires_at']),
            'users' => User::query()->where('tenant_id', $supportSession->tenant_id)
                ->select(['id', 'name', 'email', 'status', 'last_active_at'])->orderBy('name')->paginate(50),
        ]);
    }
}
