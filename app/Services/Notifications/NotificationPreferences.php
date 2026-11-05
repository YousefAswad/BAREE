<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\PhoneLink;
use App\Models\User;

/** Who gets what, where. Defaults are sensible; the user can switch any type/channel off. */
final class NotificationPreferences
{
    public const TYPES = ['assignment', 'achievement', 'institution', 'tools', 'billing', 'security'];

    private const DEFAULTS = [
        'assignment' => ['database', 'mail', 'whatsapp'],
        'achievement' => ['database'],
        'institution' => ['database', 'mail'],
        'tools' => ['database'],
        'billing' => ['database', 'mail'],
        'security' => ['database', 'mail'], // cannot be switched off
    ];

    public function channels(User $user, string $type): array
    {
        $prefs = NotificationPreference::query()->where('user_id', $user->id)->where('type', $type)->pluck('enabled', 'channel');
        $channels = [];

        foreach (self::DEFAULTS[$type] ?? ['database'] as $channel) {
            $enabled = $type === 'security' ? true : ($prefs[$channel] ?? true);
            if (! $enabled) {
                continue;
            }
            if ($channel === 'whatsapp' && ! $this->whatsappReady($user)) {
                continue;
            }
            $channels[] = $channel === 'whatsapp' ? \App\Notifications\Channels\WhatsAppChannel::class : $channel;
        }

        return $channels;
    }

    public function set(User $user, string $type, string $channel, bool $enabled): void
    {
        if ($type === 'security' || ! in_array($type, self::TYPES, true) || ! in_array($channel, ['database', 'mail', 'whatsapp'], true)) {
            return;
        }
        NotificationPreference::query()->updateOrCreate(['user_id' => $user->id, 'type' => $type, 'channel' => $channel], ['enabled' => $enabled]);
    }

    public function matrix(User $user): array
    {
        $prefs = NotificationPreference::query()->where('user_id', $user->id)->get()->groupBy('type');

        return collect(self::DEFAULTS)->map(fn ($channels, $type) => collect($channels)->mapWithKeys(function ($c) use ($prefs, $type) {
            if ($type === 'security') {
                return [$c => true];
            }
            $row = ($prefs[$type] ?? collect())->firstWhere('channel', $c);

            return [$c => $row === null ? true : (bool) $row->enabled];
        }))->all();
    }

    private function whatsappReady(User $user): bool
    {
        return config('whatsapp.enabled') && (bool) $user->tenant?->setting('whatsapp.enabled', false)
            && (PhoneLink::query()->where('user_id', $user->id)->first()?->canReceive() ?? false);
    }
}
