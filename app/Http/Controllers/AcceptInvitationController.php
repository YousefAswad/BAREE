<?php

namespace App\Http\Controllers;

use App\Services\Institution\InvitationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class AcceptInvitationController extends Controller
{
    public function show(string $token, InvitationService $invitations): Response
    {
        $invitation = $invitations->findUsable($token);

        return Inertia::render('Invitations/Accept', [
            'valid' => $invitation !== null,
            'email' => $invitation?->email,
            'name' => $invitation?->name,
            'token' => $invitation ? $token : null,
        ]);
    }

    public function store(Request $request, string $token, InvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'accept_policy' => ['accepted'],
        ]);

        try {
            $user = $invitations->accept($token, $data['name'], $data['password']);
        } catch (DomainException $e) {
            return back()->withErrors(['token' => $e->getMessage()]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/onboarding/profile');
    }
}
