<?php

namespace App\Notifications\Channels;

use App\Models\PhoneLink;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Notifications\Notification;

/** Sends approved templates to users who verified their number and opted in. */
final class WhatsAppChannel
{
    public function __construct(private WhatsAppClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }
        $link = PhoneLink::query()->where('user_id', $notifiable->id)->first();
        if (! $link?->canReceive()) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable); // ['template' => key, 'params' => [...]]
        $this->client->sendTemplate($link->phone, (string) config('whatsapp.templates.'.$message['template']),
            $notifiable->locale === 'en' ? 'en' : 'ar', $message['params'] ?? [], $notifiable->tenant_id, $notifiable->id);
    }
}
