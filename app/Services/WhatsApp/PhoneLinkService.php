<?php

namespace App\Services\WhatsApp;

use App\Models\PhoneLink;
use App\Models\User;
use App\Support\Audit;
use DomainException;

/** Link a phone to a user with a one-time code sent over WhatsApp, then explicit opt-in. */
final class PhoneLinkService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(private WhatsAppClient $client) {}

    public function start(User $user, string $rawPhone): PhoneLink
    {
        $phone = $this->normalize($rawPhone);
        $hash = PhoneLink::hashPhone($phone);

        $taken = PhoneLink::query()->withoutGlobalScopes()->where('phone_hash', $hash)->where('user_id', '!=', $user->id)->exists();
        if ($taken) {
            throw new DomainException(__('This number is linked to another account.'));
        }

        $code = (string) random_int(100000, 999999);
        $link = PhoneLink::query()->updateOrCreate(['user_id' => $user->id], [
            'phone' => $phone, 'phone_hash' => $hash, 'otp_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'otp_attempts' => 0, 'otp_expires_at' => now()->addMinutes(10), 'verified_at' => null, 'opted_in_at' => null, 'opted_out_at' => null,
        ]);

        $this->client->sendTemplate($phone, (string) config('whatsapp.templates.otp'), $user->locale === 'en' ? 'en' : 'ar', [$code], $user->tenant_id, $user->id);
        Audit::log('whatsapp.link_started', $user);

        return $link;
    }

    public function verify(User $user, string $code): PhoneLink
    {
        $link = PhoneLink::query()->where('user_id', $user->id)->firstOrFail();

        if ($link->otp_attempts >= self::MAX_ATTEMPTS || ! $link->otp_expires_at?->isFuture()) {
            throw new DomainException(__('The code has expired. Request a new one.'));
        }
        $link->increment('otp_attempts');

        if (! hash_equals((string) $link->otp_hash, hash_hmac('sha256', trim($code), (string) config('app.key')))) {
            throw new DomainException(__('The code is not correct.'));
        }

        $link->update(['verified_at' => now(), 'opted_in_at' => now(), 'otp_hash' => null, 'otp_expires_at' => null]);
        Audit::log('whatsapp.linked', $user);

        return $link;
    }

    public function optOut(PhoneLink $link): void
    {
        $link->update(['opted_out_at' => now()]);
        Audit::log('whatsapp.opted_out', null, ['user' => $link->user_id]);
    }

    public function normalize(string $raw): string
    {
        $digits = preg_replace('/[^\d+]/', '', strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        $digits = str_starts_with($digits, '00') ? '+'.substr($digits, 2) : $digits;
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $digits)) {
            throw new DomainException(__('Enter the number in international format, e.g. +970599123456.'));
        }

        return $digits;
    }
}
