<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('the legacy dashboard URL sends users to their role-based home', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // BAREE: /dashboard is a legacy URL. A plain employee lands on /home (staff -> /platform, admins -> /admin).
    $this->get(route('dashboard'))->assertRedirect(route('feed', absolute: false));
});
