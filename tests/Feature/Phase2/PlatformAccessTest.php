<?php

use App\Models\SupportSession;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SupportAccessNotification;
use Database\Seeders\BareeSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    $this->staff = User::where('email', 'staff@baree.test')->first();
    $this->admin = User::where('email', 'admin@future.test')->first();
    $this->tenant = $this->admin->tenant;
});

it('hides the platform area from institution admins entirely', function () {
    $this->actingAs($this->admin)->get('/platform')->assertNotFound();
    $this->actingAs($this->admin)->get('/platform/tenants')->assertNotFound();
});

it('lets BAREE staff in', function () {
    $this->actingAs($this->staff)->get('/platform')->assertOk();
    $this->actingAs($this->staff)->get('/platform/tenants')->assertOk();
});

it('provisions an institution with credits, agent access and an owner invitation', function () {
    Notification::fake();

    $this->actingAs($this->staff)->post('/platform/tenants', [
        'name' => 'مدرسة الأمل', 'type' => 'school', 'plan_key' => 'professional', 'owner_email' => 'owner@amal.ps', 'trial_credits' => 3000,
    ])->assertRedirect();

    $tenant = bareeTenantContext()->asPlatform(fn () => Tenant::where('name', 'مدرسة الأمل')->first());
    expect($tenant->status)->toBe('trial');
    bareeTenantContext()->runAs($tenant->id, function () use ($tenant) {
        expect(app(\App\Services\CreditService::class)->balance($tenant->id))->toBe(3000)
            ->and(\App\Models\Invitation::where('email', 'owner@amal.ps')->value('role_key'))->toBe('institution_owner')
            ->and(\App\Models\TenantAgentSetting::count())->toBeGreaterThan(0);
    });
});

it('opens audited, read-only, time-boxed support sessions and tells the owner', function () {
    Notification::fake();
    $owner = userWithRole('institution_owner', $this->tenant);

    $this->actingAs($this->staff)->post("/platform/tenants/{$this->tenant->public_id}/support", ['reason' => 'short'])->assertSessionHasErrors('reason');
    $this->actingAs($this->staff)->post("/platform/tenants/{$this->tenant->public_id}/support", ['reason' => 'مشكلة في رفع ملفات الطلاب'])->assertRedirect();

    $session = SupportSession::latest('id')->first();
    Notification::assertSentTo($owner, SupportAccessNotification::class);
    $this->actingAs($this->staff)->get("/platform/support/{$session->id}/users")->assertOk();

    $session->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($this->staff)->get("/platform/support/{$session->id}/users")->assertForbidden();
});
