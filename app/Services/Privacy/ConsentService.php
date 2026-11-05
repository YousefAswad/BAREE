<?php

namespace App\Services\Privacy;

use App\Models\User;
use App\Models\UserConsent;
use App\Support\Audit;

/** Each consent is explicit, revocable at any time, and audited. */
final class ConsentService
{
    public const KEYS = [
        'manager_visibility' => false, // my manager may see my individual level (only in "consent" privacy mode)
        'improve_agents' => true,       // my ratings and edits may help BAREE improve agents, without my identity
        'leaderboard' => false,         // show my name on leaderboards (if the institution enables them)
    ];

    public function all(User $user): array
    {
        $stored = UserConsent::query()->where('user_id', $user->id)->pluck('granted', 'key');

        return collect(self::KEYS)->mapWithKeys(fn ($default, $key) => [$key => (bool) ($stored[$key] ?? $default)])->all();
    }

    public function set(User $user, string $key, bool $granted): void
    {
        abort_unless(array_key_exists($key, self::KEYS), 422);
        UserConsent::query()->updateOrCreate(['user_id' => $user->id, 'key' => $key], ['granted' => $granted, 'decided_at' => now()]);
        Audit::log('consent.'.($granted ? 'granted' : 'revoked'), $user, ['key' => $key]);
    }
}
