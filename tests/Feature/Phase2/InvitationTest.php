<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Services\Institution\InvitationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedRoles();
    Notification::fake();
    $this->tenant = Tenant::factory()->create();
    $this->admin = userWithRole('institution_admin', $this->tenant);
    bareeTenantContext()->set($this->tenant->id);
});

it('stores only a hash of the token and e-mails the raw link once', function () {
    [$invitation, $raw] = app(InvitationService::class)->invite($this->admin, ['email' => 'New@School.ps', 'role_key' => 'staff']);

    expect($invitation->email)->toBe('new@school.ps')
        ->and($invitation->getRawOriginal('token_hash'))->toBe(hash('sha256', $raw))
        ->and($invitation->getRawOriginal('token_hash'))->not->toBe($raw);
    Notification::assertSentOnDemand(InvitationNotification::class);
});

it('creates the user inside the right institution with the invited role, exactly once', function () {
    [, $raw] = app(InvitationService::class)->invite($this->admin, ['email' => 'teacher@school.ps', 'role_key' => 'staff']);
    bareeTenantContext()->clear(); // the guest accepting has no tenant yet

    $user = app(InvitationService::class)->accept($raw, 'معلّمة جديدة', 'Str0ngPassword!');

    expect($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->status)->toBe('active')
        ->and($user->hasPermission('ai.agents.use'))->toBeTrue()
        ->and($user->hasPermission('users.invite'))->toBeFalse()
        ->and(fn () => app(InvitationService::class)->accept($raw, 'x', 'Str0ngPassword!'))->toThrow(DomainException::class);
});

it('rejects expired and revoked invitations', function () {
    [$invitation, $raw] = app(InvitationService::class)->invite($this->admin, ['email' => 'late@school.ps', 'role_key' => 'staff']);
    $invitation->update(['expires_at' => now()->subMinute()]);

    expect(app(InvitationService::class)->findUsable($raw))->toBeNull();
});

it('enforces the institution e-mail domain allow-list', function () {
    $this->tenant->update(['settings' => ['allowed_email_domains' => ['school.ps']]]);

    expect(fn () => app(InvitationService::class)->invite($this->admin->fresh(), ['email' => 'someone@gmail.com', 'role_key' => 'staff']))
        ->toThrow(DomainException::class);
});

it('never lets an institution grant a platform role, and only role managers invite admins', function () {
    $service = app(InvitationService::class);

    expect(fn () => $service->invite($this->admin, ['email' => 'a@school.ps', 'role_key' => 'platform_owner']))->toThrow(DomainException::class)
        ->and(fn () => $service->invite($this->admin, ['email' => 'b@school.ps', 'role_key' => 'institution_owner']))->toThrow(DomainException::class);

    $owner = userWithRole('institution_owner', $this->tenant);
    [$invitation] = $service->invite($owner, ['email' => 'c@school.ps', 'role_key' => 'institution_admin']);
    expect($invitation->role_key)->toBe('institution_admin');
});

it('accepts through the public page and logs the new user in', function () {
    [, $raw] = app(InvitationService::class)->invite($this->admin, ['email' => 'web@school.ps', 'role_key' => 'staff']);
    bareeTenantContext()->clear();

    $this->post("/invitations/{$raw}", [
        'name' => 'مستخدم', 'password' => 'Str0ngPassword!', 'password_confirmation' => 'Str0ngPassword!', 'accept_policy' => '1',
    ])->assertRedirect('/onboarding/profile');

    $this->assertAuthenticatedAs(User::where('email', 'web@school.ps')->first());
});
