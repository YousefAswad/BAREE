<?php

namespace App\Http\Controllers\Platform;

use App\AI\Data\LlmRequest;
use App\AI\ProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiProviderCredential;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class ProviderController extends Controller
{
    public function index(): Response
    {
        $this->authorize('platform.providers.manage');

        return Inertia::render('Platform/Providers/Index', [
            'providers' => AiProvider::query()->orderBy('key')->get()->map(fn (AiProvider $p) => [
                'key' => $p->key, 'driver' => $p->driver, 'base_url' => $p->base_url, 'enabled' => $p->enabled,
                'has_platform_key' => AiProviderCredential::query()->where('provider_id', $p->id)->whereNull('tenant_id')->exists(),
                'byok_tenants' => AiProviderCredential::query()->where('provider_id', $p->id)->whereNotNull('tenant_id')->count(),
            ]),
        ]);
    }

    public function update(Request $request, AiProvider $provider): RedirectResponse
    {
        $this->authorize('platform.providers.manage');
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'base_url' => ['nullable', 'url:https,http', 'max:255']]);
        $provider->update($data);
        Audit::log('provider.updated', $provider, ['enabled' => $data['enabled']]);

        return back();
    }

    public function storeKey(Request $request, AiProvider $provider): RedirectResponse
    {
        $this->authorize('platform.providers.manage');
        $data = $request->validate(['secret' => ['required', 'string', 'min:10', 'max:500']]);

        AiProviderCredential::updateOrCreate(['provider_id' => $provider->id, 'tenant_id' => null], ['secret' => $data['secret'], 'rotated_at' => now()]);
        Audit::log('provider.key_rotated', $provider); // never log the key

        return back()->with('status', __('Key saved.'));
    }

    public function test(AiProvider $provider, ProviderRegistry $registry): RedirectResponse
    {
        $this->authorize('platform.providers.manage');

        try {
            $response = $registry->driver($provider->key, null)->send(new LlmRequest(
                system: 'Reply with the single word: ok', messages: [['role' => 'user', 'content' => 'ping']],
                model: $registry->modelFor($provider->key, 'light'), maxTokens: 10,
            ));

            return back()->with('status', __('Connected: :model', ['model' => $response->model]));
        } catch (Throwable $e) {
            return back()->withErrors(['provider' => class_basename($e)]);
        }
    }
}
