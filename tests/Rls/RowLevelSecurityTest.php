<?php

use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 | These tests deliberately bypass Eloquent (raw query builder) to prove that PostgreSQL itself
 | enforces isolation for the application role. They only make sense on PostgreSQL as baree_app.
 */
beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL only.');
    }
    expect(DB::selectOne('select rolbypassrls, rolsuper from pg_roles where rolname = current_user'))
        ->rolbypassrls->toBeFalse()->rolsuper->toBeFalse();

    [$this->a, $this->b] = bareeTenantContext()->asPlatform(fn () => [Tenant::factory()->create(), Tenant::factory()->create()]);
    bareeTenantContext()->runAs($this->a->id, fn () => Department::create(['name' => 'A-only', 'kind' => 'academic']));
    bareeTenantContext()->runAs($this->b->id, fn () => Department::create(['name' => 'B-only', 'kind' => 'academic']));
});

it('returns only the current institution\'s rows to raw SQL', function () {
    $names = bareeTenantContext()->runAs($this->a->id, fn () => DB::table('departments')->pluck('name')->all());

    expect($names)->toContain('A-only')->not->toContain('B-only');
});

it('returns nothing when no institution is set (fail closed)', function () {
    bareeTenantContext()->clear();

    expect(DB::table('departments')->whereIn('name', ['A-only', 'B-only'])->count())->toBe(0);
});

it('refuses raw writes into another institution', function () {
    expect(fn () => bareeTenantContext()->runAs($this->a->id, fn () => DB::table('departments')->insert([
        'tenant_id' => $this->b->id, 'name' => 'smuggled', 'kind' => 'academic', 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('keeps the audit log append-only for the application role', function () {
    bareeTenantContext()->asPlatform(fn () => \App\Support\Audit::log('rls.test', null));

    expect(fn () => bareeTenantContext()->asPlatform(fn () => DB::table('audit_logs')->where('action', 'rls.test')->delete()))->toThrow(QueryException::class);
});
