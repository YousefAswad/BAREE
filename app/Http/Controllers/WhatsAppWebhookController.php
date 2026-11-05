<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppInboundJob;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WhatsAppWebhookController extends Controller
{
    /** Meta's subscription handshake. */
    public function verify(Request $request): Response
    {
        abort_unless($request->query('hub_mode') === 'subscribe' && hash_equals((string) config('whatsapp.verify_token'), (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, WhatsAppClient $client): Response
    {
        abort_unless($client->verifySignature($request->getContent(), $request->header('X-Hub-Signature-256')), 403);

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    WhatsAppMessage::query()->where('wa_message_id', $status['id'] ?? '')->update(['status' => mb_substr((string) ($status['status'] ?? ''), 0, 16)]);
                }
                foreach ($value['messages'] ?? [] as $message) {
                    if (($message['type'] ?? null) === 'text' && isset($message['from'], $message['id'])) {
                        ProcessWhatsAppInboundJob::dispatch((string) $message['from'], (string) ($message['text']['body'] ?? ''), (string) $message['id']);
                    }
                }
            }
        }

        return response('', 200); // acknowledge quickly; work happens on the queue
    }
}
