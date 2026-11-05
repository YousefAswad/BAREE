<?php

use App\Models\User;

test('logout is POST-only and a GET is rejected', function () {
    $this->actingAs(User::factory()->create())->get('/logout')->assertStatus(405);
    $this->assertAuthenticated();
});

test('logout invalidates the session, rotates the CSRF token and lands on the login page', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'old-token']);

    $this->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
    expect(session('_token'))->not->toBe('old-token');
});

test('protected endpoints reject a logged-out session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout');
    $this->assertGuest();

    foreach (['/home', '/admin', '/platform', '/settings/profile', '/notifications'] as $uri) {
        $this->get($uri)->assertRedirect('/login');
    }
});

test('authenticated responses are never cacheable (browser Back after logout)', function () {
    $user = User::factory()->create();
    $res = $this->actingAs($user)->get('/settings/profile');
    expect($res->headers->get('Cache-Control'))->toContain('no-store');
});
