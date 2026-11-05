<?php

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BareeSeeder;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    $this->admin = User::where('email', 'admin@future.test')->first();
    $this->ahmad = User::where('email', 'ahmad@future.test')->first();
});

it('keeps staff out of the admin area and lets admins in', function () {
    $this->actingAs($this->ahmad)->get('/admin/users')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    $this->actingAs($this->admin)->get('/admin/segments')->assertOk();
});

it('refuses ids that belong to another institution', function () {
    $other = Tenant::factory()->create();
    $foreign = bareeTenantContext()->runAs($other->id, fn () => Department::create(['name' => 'Other']));

    $this->actingAs($this->admin)->post('/admin/invitations', ['email' => 'x@future.test', 'role_key' => 'staff', 'department_id' => $foreign->id])
        ->assertSessionHasErrors('department_id');
});

it('limits privacy settings to owners and never sends protected names to the browser', function () {
    $owner = userWithRole('institution_owner', $this->admin->tenant); // settings need the owner's permission
    $this->actingAs($this->admin)->get('/admin/privacy')->assertForbidden();
    $page = $this->actingAs($owner)->get('/admin/privacy')->viewData('page');

    expect($page['props'])->toHaveKey('protectedTermsCount')->not->toHaveKey('terms');
});
