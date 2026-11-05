<?php

namespace App\Jobs;

use App\Models\PhoneLink;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\Knowledge\HubService;
use App\Services\WhatsApp\PhoneLinkService;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/** A verified, opted-in user can ask the knowledge hub over WhatsApp. Unknown numbers get no reply. */
final class ProcessWhatsAppInboundJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $from, public string $text, public string $messageId) {}

    public function handle(TenantContext $context, HubService $hub, WhatsAppClient $client, PhoneLinkService $links): void
    {
        $phone = '+'.ltrim($this->from, '+');
        $link = $context->asPlatform(fn () => PhoneLink::query()->where('phone_hash', PhoneLink::hashPhone($phone))->first());
        if (! $link || ! $link->verified_at) {
            return; // never reveal whether a number is known
        }

        $context->runAs($link->tenant_id, function () use ($link, $hub, $client, $links) {
            WhatsAppMessage::create(['tenant_id' => $link->tenant_id, 'user_id' => $link->user_id, 'direction' => 'in', 'wa_message_id' => $this->messageId, 'kind' => 'text', 'status' => 'received', 'created_at' => now()]);
            $link->update(['last_inbound_at' => now()]);
            $text = trim($this->text);

            if (in_array(mb_strtolower($text), config('whatsapp.stop_words'), true)) {
                $links->optOut($link);
                $client->sendText($link->phone, 'تم إيقاف رسائل BAREE على واتساب. بتقدر ترجعها من إعدادات حسابك.', $link->tenant_id, $link->user_id);

                return;
            }
            if (! $link->canReceive()) {
                return;
            }
            if (RateLimiter::tooManyAttempts('wa-in:'.$link->id, (int) config('whatsapp.daily_inbound_limit'))) {
                $client->sendText($link->phone, 'وصلت للحد اليومي للأسئلة على واتساب. كمّل من المنصة.', $link->tenant_id, $link->user_id);

                return;
            }
            RateLimiter::hit('wa-in:'.$link->id, 86400);

            $user = User::query()->find($link->user_id);
            if (! $user || $user->status !== 'active') {
                return;
            }

            try {
                $result = $hub->ask($user, mb_substr($text, 0, 1000));
                $sources = collect($result['citations'])->map(fn ($c) => '['.$c['n'].'] '.($c['title']['ar'] ?? ''))->implode("\n");
                $client->sendText($link->phone, mb_substr($result['answer'], 0, 1500).($sources ? "\n\nالمصادر:\n".$sources : ''), $link->tenant_id, $link->user_id);
            } catch (Throwable $e) {
                report($e);
                $client->sendText($link->phone, 'صار خلل مؤقت. جرّب بعد شوي.', $link->tenant_id, $link->user_id);
            }
        });
    }
}
