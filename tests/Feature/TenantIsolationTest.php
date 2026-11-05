<?php

use App\Models\AssessmentAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\BareeSeeder;

it('only ever returns rows of the current institution', function () {
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];
    [$userA, $userB] = [User::factory()->forTenant($a)->create(), User::factory()->forTenant($b)->create()];

    bareeTenantContext()->runAs($a->id, fn () => Workspace::create(['owner_id' => $userA->id, 'kind' => 'project', 'title' => 'A']));
    bareeTenantContext()->runAs($b->id, fn () => Workspace::create(['owner_id' => $userB->id, 'kind' => 'project', 'title' => 'B']));

    bareeTenantContext()->runAs($a->id, fn () => expect(Workspace::pluck('title')->all())->toBe(['A']));
    bareeTenantContext()->runAs($b->id, fn () => expect(Workspace::pluck('title')->all())->toBe(['B']));
});

it('fails closed when no tenant is set', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    bareeTenantContext()->runAs($tenant->id, fn () => Workspace::create(['owner_id' => $user->id, 'kind' => 'project', 'title' => 'A']));

    bareeTenantContext()->clear();

    expect(Workspace::count())->toBe(0)
        ->and(fn () => Workspace::create(['owner_id' => $user->id, 'kind' => 'project', 'title' => 'X']))
        ->toThrow(RuntimeException::class);
});

it('blocks cross-tenant writes and tenant_id changes', function () {
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];
    $user = User::factory()->forTenant($a)->create();

    bareeTenantContext()->runAs($a->id, function () use ($b, $user) {
        expect(fn () => Workspace::create(['tenant_id' => $b->id, 'owner_id' => $user->id, 'kind' => 'project', 'title' => 'X']))
            ->toThrow(RuntimeException::class);

        $ws = Workspace::create(['owner_id' => $user->id, 'kind' => 'project', 'title' => 'A']);
        $ws->tenant_id = $b->id;
        expect(fn () => $ws->save())->toThrow(RuntimeException::class);
    });
});

it('returns 404 for another institution\'s assessment attempt', function () {
    $this->seed(BareeSeeder::class);
    $ahmad = User::where('email', 'ahmad@future.test')->first();
    $this->actingAs($ahmad)->post(route('assessments.start', 'teacher_initial'));
    $attempt = bareeTenantContext()->runAs($ahmad->tenant_id, fn () => AssessmentAttempt::first());

    $outsider = User::factory()->create(); // different tenant

    $this->actingAs($outsider)->get(route('assessments.show', $attempt->public_id))->assertNotFound();
});
