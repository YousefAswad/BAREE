<?php

use App\AI\Exceptions\AiPolicyViolation;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\TrackActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

// Based on the official Laravel 13 Vue starter kit bootstrap file, plus BAREE middleware.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->prepend(AssignRequestId::class); // payment and WhatsApp webhooks are signature-verified

        $middleware->web(append: [
            SecurityHeaders::class,
            SetTenantContext::class,
            SetLocale::class,
            TrackActivity::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The tenant must be known BEFORE route-model binding, so bound models are tenant-scoped.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenantContext::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsurePlatformAdmin::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(fn (AiPolicyViolation $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => $e->getMessage()], 422)
            : back()->withErrors(['ai' => $e->getMessage()]));

        $exceptions->dontFlash(['password', 'password_confirmation', 'secret', 'api_key']);
    })->create();
