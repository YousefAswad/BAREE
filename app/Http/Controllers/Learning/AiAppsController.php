<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Personal tokens for connecting BAREE to the user's own AI client over MCP. */
final class AiAppsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Account/AiApps', [
            'tokens' => $request->user()->tokens()->where('abilities', 'like', '%mcp%')->get(['id', 'name', 'last_used_at', 'created_at', 'expires_at']),
            'endpoint' => url('/mcp/baree'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('ai.agents.use');
        $name = $request->validate(['name' => ['required', 'string', 'max:60']])['name'];
        $token = $request->user()->createToken($name, ['mcp'], now()->addDays(90)); // expiring by default
        Audit::log('mcp.token_created', $request->user(), ['name' => $name]);

        return back()->with('status', $token->plainTextToken); // shown once
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();
        Audit::log('mcp.token_revoked', $request->user(), ['token' => $id]);

        return back();
    }
}
