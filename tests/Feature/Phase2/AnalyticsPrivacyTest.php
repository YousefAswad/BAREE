<?php

use App\Models\CompetencyFramework;
use App\Models\Department;
use App\Models\Passport;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Institution\AnalyticsService;
use Database\Seeders\BareeSeeder;

beforeEach(function () {
    $this->seed(BareeSeeder::class);
    $this->tenant = Tenant::factory()->create(['settings' => ['privacy' => ['mode' => 'consent']]]);
    bareeTenantContext()->set($this->tenant->id);
    $this->big = Department::create(['name' => 'Big']);
    $this->small = Department::create(['name' => 'Small']);
    $levelId = bareeTenantContext()->asPlatform(fn () => CompetencyFramework::where('key', 'baree_ai_readiness')->first()->levels()->where('rank', 3)->value('id'));
    bareeTenantContext()->set($this->tenant->id);

    foreach (range(1, 6) as $i) {
        $u = User::factory()->forTenant($this->tenant)->create(['department_id' => $this->big->id]);
        Passport::create(['user_id' => $u->id, 'readiness' => 60, 'level_id' => $levelId]);
    }
    foreach (range(1, 2) as $i) {
        $u = User::factory()->forTenant($this->tenant)->create(['department_id' => $this->small->id]);
        Passport::create(['user_id' => $u->id, 'readiness' => 30, 'level_id' => $levelId]);
        $this->smallUser = $u;
    }
});

it('suppresses any department smaller than the minimum group size', function () {
    $rows = collect(app(AnalyticsService::class)->readinessByDepartment())->keyBy('department');

    expect($rows['Big']['readiness'])->toEqual(60)
        ->and($rows['Small']['suppressed'])->toBeTrue()
        ->and($rows['Small']['readiness'])->toBeNull()
        ->and($rows['Small']['people'])->toBeNull();
});

it('shows an individual level only with permission AND the employee\'s consent', function () {
    $admin = userWithRole('institution_owner', $this->tenant);
    $analytics = app(AnalyticsService::class);

    expect($analytics->individualLevel($admin, $this->smallUser))->toBeNull();

    UserConsent::create(['user_id' => $this->smallUser->id, 'key' => 'manager_visibility', 'granted' => true, 'decided_at' => now()]);
    expect($analytics->individualLevel($admin, $this->smallUser))->not->toBeNull();

    $this->tenant->update(['settings' => ['privacy' => ['mode' => 'aggregate']]]);
    expect($analytics->individualLevel($admin, $this->smallUser))->toBeNull();
});
