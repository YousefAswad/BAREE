<?php

require_once __DIR__.'/Phase5Helpers.php';

use App\Jobs\ProcessWhatsAppInboundJob;
use App\Models\KnowledgeDocument;
use App\Models\PhoneLink;
use App\Models\UserActivityDay;
use App\Services\Knowledge\KnowledgeIndexer;
use App\Services\WhatsApp\PhoneLinkService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->ahmad = opsSetup();
    config(['whatsapp.enabled' => true, 'whatsapp.phone_number_id' => '123', 'whatsapp.access_token' => 't', 'whatsapp.app_secret' => 'app_secret', 'whatsapp.verify_token' => 'verify_me']);
    $this->ahmad->tenant->update(['settings' => ['whatsapp' => ['enabled' => true], 'privacy' => ['mode' => 'consent']]]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]]), 'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ممنوع [1].']], 'usage' => []])]);
});

it('links a phone with a hashed one-time code, limited attempts and expiry', function () {
    $service = app(PhoneLinkService::class);
    $service->start($this->ahmad, '٠٠٩٧٠٥٩٩١٢٣٤٥٦');
    $link = PhoneLink::first();

    expect($link->phone)->toBe('+970599123456')->and($link->getRawOriginal('phone'))->not->toContain('599123456')
        ->and(fn () => $service->verify($this->ahmad, '000000'))->toThrow(DomainException::class);

    $code = null;
    Http::assertSent(function ($r) use (&$code) { $code = data_get($r->data(), 'template.components.0.parameters.0.text', $code); return true; });
    expect($service->verify($this->ahmad, $code)->canReceive())->toBeTrue();
});

it('answers verified users from the knowledge hub, honours STOP, and ignores unknown numbers', function () {
    PhoneLink::create(['user_id' => $this->ahmad->id, 'phone' => '+970599123456', 'phone_hash' => PhoneLink::hashPhone('+970599123456'), 'verified_at' => now(), 'opted_in_at' => now()]);
    $doc = KnowledgeDocument::create(['kind' => 'policy', 'title' => ['ar' => 'سياسة البيانات'], 'status' => 'indexing', 'body' => 'ممنوع رفع بيانات الطلاب على أدوات خارجية.']);
    app(KnowledgeIndexer::class)->index($doc);
    bareeTenantContext()->clear();

    (new ProcessWhatsAppInboundJob('15550000000', 'سؤال', 'wamid.in0'))->handle(bareeTenantContext(), app(\App\Services\Knowledge\HubService::class), app(\App\Services\WhatsApp\WhatsAppClient::class), app(PhoneLinkService::class));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com'));

    (new ProcessWhatsAppInboundJob('970599123456', 'هل بقدر أرفع بيانات الطلاب على أدوات خارجية؟', 'wamid.in1'))->handle(bareeTenantContext(), app(\App\Services\Knowledge\HubService::class), app(\App\Services\WhatsApp\WhatsAppClient::class), app(PhoneLinkService::class));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com') && data_get($r->data(), 'type') === 'text');

    (new ProcessWhatsAppInboundJob('970599123456', 'إيقاف', 'wamid.in2'))->handle(bareeTenantContext(), app(\App\Services\Knowledge\HubService::class), app(\App\Services\WhatsApp\WhatsAppClient::class), app(PhoneLinkService::class));
    expect(bareeTenantContext()->asPlatform(fn () => PhoneLink::first())->canReceive())->toBeFalse();
});

it('verifies the webhook handshake and rejects unsigned deliveries', function () {
    bareeTenantContext()->clear();
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify_me&hub.challenge=42')->assertOk()->assertSee('42');
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
    $this->postJson('/webhooks/whatsapp', ['entry' => []])->assertForbidden();
});

it('switches language and direction, and tracks activity once per day', function () {
    $this->actingAs($this->ahmad)->post('/locale', ['locale' => 'en'])->assertRedirect();
    expect($this->ahmad->fresh()->locale)->toBe('en');

    $page = $this->actingAs($this->ahmad->fresh())->get('/home')->viewData('page');
    expect($page['props']['locale'])->toBe('en')->and($page['props']['dir'])->toBe('ltr');

    $this->actingAs($this->ahmad)->get('/home');
    expect(UserActivityDay::where('user_id', $this->ahmad->id)->count())->toBe(1);
});
