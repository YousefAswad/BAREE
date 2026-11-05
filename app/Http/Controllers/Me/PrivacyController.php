<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Services\Privacy\ConsentService;
use App\Services\Privacy\DataSubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PrivacyController extends Controller
{
    public function show(Request $request, ConsentService $consents): Response
    {
        return Inertia::render('Account/Privacy', [
            'consents' => $consents->all($request->user()),
            'mode' => $request->user()->tenant?->setting('privacy.mode', config('baree.privacy.default_mode')),
            'leaderboards' => (bool) $request->user()->tenant?->setting('leaderboards', false),
        ]);
    }

    public function update(Request $request, ConsentService $consents): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'in:'.implode(',', array_keys(ConsentService::KEYS))], 'granted' => ['required', 'boolean']]);
        $consents->set($request->user(), $data['key'], $data['granted']);

        return back();
    }

    public function export(Request $request, DataSubjectService $subjects): JsonResponse
    {
        return response()->json($subjects->export($request->user()), 200, ['Content-Disposition' => 'attachment; filename="my-baree-data.json"'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function destroy(Request $request, DataSubjectService $subjects): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        $subjects->erase($user, $user);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
