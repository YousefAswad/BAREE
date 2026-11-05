<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

final class WhatsAppClient
{
    public function sendTemplate(string $to, string $template, string $language, array $params = [], ?int $tenantId = null, ?int $userId = null): ?string
    {
        $components = $params ? [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => mb_substr((string) $p, 0, 900)], $params)]] : [];

        return $this->send(['to' => $to, 'type' => 'template', 'template' => array_filter(['name' => $template, 'language' => ['code' => $language], 'components' => $components])],
            'template', $template, $tenantId, $userId);
    }

    /** Free text — only valid inside the 24-hour customer-service window. */
    public function sendText(string $to, string $text, ?int $tenantId = null, ?int $userId = null): ?string
    {
        return $this->send(['to' => $to, 'type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000), 'preview_url' => false]], 'text', null, $tenantId, $userId);
    }

    public function verifySignature(string $body, ?string $header): bool
    {
        $secret = (string) config('whatsapp.app_secret');
        if ($secret === '' || ! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), substr($header, 7));
    }

    private function send(array $payload, string $kind, ?string $template, ?int $tenantId, ?int $userId): ?string
    {
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', config('whatsapp.graph_version'), config('whatsapp.phone_number_id'));
        $response = Http::withToken((string) config('whatsapp.access_token'))->acceptJson()->timeout(20)
            ->post($url, ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual'] + $payload)->throw();

        $id = $response->json('messages.0.id');
        // Message content is not stored; only delivery metadata for support and audit.
        // The message is already sent: recording it must never fail the caller. wa_message_id is unique, so a repeated id is the same message.
        $record = ['tenant_id' => $tenantId, 'user_id' => $userId, 'direction' => 'out', 'wa_message_id' => $id, 'kind' => $kind, 'template' => $template, 'status' => 'sent', 'created_at' => now()];
        $id === null ? WhatsAppMessage::create($record) : WhatsAppMessage::query()->firstOrCreate(['wa_message_id' => $id], $record);

        return $id;
    }
}
