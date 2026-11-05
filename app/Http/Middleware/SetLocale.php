<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Arabic (RTL) by default; English (LTR) per user preference or browser. */
final class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $request->getPreferredLanguage(self::SUPPORTED)
            ?? 'ar';
        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'ar';

        app()->setLocale($locale);
        Inertia::share(['locale' => $locale, 'dir' => $locale === 'ar' ? 'rtl' : 'ltr']);

        return $next($request);
    }
}
