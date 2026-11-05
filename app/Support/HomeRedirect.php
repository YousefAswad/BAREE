<?php

namespace App\Support;

use App\Models\User;

/** Where a signed-in user lands. Only chooses a destination; each destination still enforces its own authorization. */
final class HomeRedirect
{
    public static function pathFor(?User $user): string
    {
        if ($user === null) {
            return route('login', absolute: false);
        }
        if ($user->is_platform_admin) {
            return route('platform.dashboard', absolute: false);
        }
        if ($user->can('analytics.view_aggregate')) {
            return route('admin.dashboard', absolute: false);
        }

        return route('feed', absolute: false);
    }

    /**
     * The areas this user may open, for navigation only (every area still enforces its own authorization).
     *
     * @return list<array{key:string,label:string,href:string}>
     */
    public static function areasFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $areas = [];
        if ($user->is_platform_admin) {
            $areas[] = ['key' => 'platform', 'label' => 'لوحة المنصة', 'href' => route('platform.dashboard', absolute: false)];
        }
        if (! $user->is_platform_admin && $user->can('analytics.view_aggregate')) {
            $areas[] = ['key' => 'admin', 'label' => 'إدارة المؤسسة', 'href' => route('admin.dashboard', absolute: false)];
        }
        if (! $user->is_platform_admin) {
            $areas[] = ['key' => 'home', 'label' => 'الرئيسية', 'href' => route('feed', absolute: false)];
        }

        return $areas;
    }
}
