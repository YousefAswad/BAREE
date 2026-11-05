<?php

require_once __DIR__.'/Phase5Helpers.php';

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\PhoneLink;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Operations\AssignmentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->ahmad = opsSetup();
    $this->admin = User::where('email', 'admin@future.test')->first();
    $this->prefs = app(NotificationPreferences::class);
});

it('routes notifications by user preference; security cannot be switched off', function () {
    expect($this->prefs->channels($this->ahmad, 'assignment'))->toBe(['database', 'mail']); // WhatsApp not linked

    $this->prefs->set($this->ahmad, 'assignment', 'mail', false);
    $this->prefs->set($this->ahmad, 'security', 'mail', false);

    expect($this->prefs->channels($this->ahmad, 'assignment'))->toBe(['database'])
        ->and($this->prefs->channels($this->ahmad, 'security'))->toBe(['database', 'mail']);
});

it('uses WhatsApp only for verified, opted-in numbers in institutions that enabled it', function () {
    config(['whatsapp.enabled' => true, 'whatsapp.phone_number_id' => '123', 'whatsapp.access_token' => 't']);
    $this->ahmad->tenant->update(['settings' => ['whatsapp' => ['enabled' => true]]]);
    PhoneLink::create(['user_id' => $this->ahmad->id, 'phone' => '+970599123456', 'phone_hash' => PhoneLink::hashPhone('+970599123456'), 'verified_at' => now(), 'opted_in_at' => now()]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    $user = $this->ahmad->fresh();
    expect($this->prefs->channels($user, 'assignment'))->toContain(WhatsAppChannel::class);

    $user->notifyNow(new AssignmentNotification('assigned', 'سياسة AI', '/academy/x', '2026-12-01', true), [WhatsAppChannel::class]);
    Http::assertSent(fn ($r) => data_get($r->data(), 'type') === 'template' && data_get($r->data(), 'to') === '+970599123456');
});

it('assigns a mandatory course to a department, notifies everyone, and reports compliance', function () {
    Notification::fake();
    $math = Department::where('name', 'الرياضيات')->first();
    $course = Course::where('key', 'ai_for_teachers')->first();

    $count = app(AssignmentService::class)->assign($this->admin, 'course', $course->id, ['departments' => [$math->id]], true, now()->subDay());

    expect($count)->toBe(1)->and(Enrollment::where('user_id', $this->ahmad->id)->first()->mandatory)->toBeTrue();
    Notification::assertSentTo($this->ahmad, AssignmentNotification::class);
    expect(app(AssignmentService::class)->courseCompliance($course)['overdue'])->toHaveCount(1);
});

it('sends each reminder once per period', function () {
    Notification::fake();
    $course = Course::where('key', 'ai_for_teachers')->first();
    Enrollment::create(['user_id' => $this->ahmad->id, 'course_id' => $course->id, 'mandatory' => true, 'due_at' => now()->addDays(2)]);

    expect(app(AssignmentService::class)->sendReminders())->toBe(1)
        ->and(app(AssignmentService::class)->sendReminders())->toBe(0);
});
