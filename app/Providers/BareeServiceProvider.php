<?php

namespace App\Providers;

use App\AI\ProviderRegistry;
use App\Authorization\PermissionResolver;
use App\Models\Permission;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/** Register in bootstrap/providers.php */
final class BareeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);     // scoped = reset per request/job, safe on Octane
        $this->app->scoped(PermissionResolver::class);
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(\App\Services\Media\MediaRegistry::class);

        $this->app->bind(\App\Services\Knowledge\Embedder::class, fn () => config('knowledge.embeddings.driver') === 'fake'
            ? new \App\Services\Knowledge\FakeEmbedder((int) config('knowledge.dimensions'))
            : new \App\Services\Knowledge\OpenAiEmbedder((string) config('ai_gateway.providers.openai.key'), (string) config('knowledge.embeddings.model'), (int) config('knowledge.dimensions')));

        $this->app->bind(\App\Services\Files\Scanner::class, function () {
            if (config('baree.uploads.scan_with') === 'none') {
                abort_if(app()->isProduction(), 500, 'Virus scanning cannot be disabled in production.');

                return new \App\Services\Files\NullScanner;
            }

            return new \App\Services\Files\ClamAvScanner(config('baree.uploads.clamav_host'), config('baree.uploads.clamav_port'));
        });
    }

    public function boot(): void
    {
        // Role-based landing after login/2FA, (the /dashboard redirect lives in routes/baree.php: it must be registered AFTER the starter kit's route).
        $this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Responses\RoleAwareLoginResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\TwoFactorLoginResponse::class, \App\Http\Responses\RoleAwareLoginResponse::class);

        // Navigation: the areas the signed-in user may open (lazy, evaluated only when an Inertia response is built).
        \Inertia\Inertia::share('areas', fn () => \App\Support\HomeRedirect::areasFor(request()->user()));

        // Dynamic permissions: any registered permission key works with @can, $this->authorize(), can: middleware.
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $known = Cache::remember('baree:permission-keys', 3600, fn () => Permission::query()->pluck('key')->flip()->all());

            if (! isset($known[$ability])) {
                return null; // fall through to policies
            }

            return app(PermissionResolver::class)->allows($user, $ability, $arguments[0] ?? null);
        });

        RateLimiter::for('ai', fn (Request $request) => [
            Limit::perMinute(20)->by('user:'.$request->user()?->id),
            Limit::perMinute(300)->by('tenant:'.$request->user()?->tenant_id),
        ]);

        // Breached-password check needs the network, so it is enforced in production only.
        Password::defaults(fn () => app()->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(12)->mixedCase()->numbers());

        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('assessment', fn (Request $request) => Limit::perMinute(120)->by('user:'.$request->user()?->id));
    }
}
