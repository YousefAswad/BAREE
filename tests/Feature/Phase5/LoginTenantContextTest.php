<?php

require_once __DIR__.'/Phase5Helpers.php';

use App\Http\Middleware\TrackActivity;
use App\Models\UserActivityDay;
use App\Support\HomeRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->ahmad = opsSetup();
});

it('records activity for the signed-in user even when no tenant context was set (login request)', function () {
    bareeTenantContext()->clear();
    cache()->forget('active:'.$this->ahmad->id.':'.now()->toDateString());

    $request = Request::create('/login', 'POST');
    $request->setUserResolver(fn () => $this->ahmad);
    app(TrackActivity::class)->handle($request, fn () => response('ok'));

    expect(bareeTenantContext()->id())->toBeNull(); // context restored, nothing leaks
    expect(bareeTenantContext()->asPlatform(fn () => UserActivityDay::where('user_id', $this->ahmad->id)->count()))->toBe(1);
});

it('still rejects a cross-tenant activity write at the database (RLS intact)', function () {
    bareeTenantContext()->set($this->ahmad->tenant_id);
    $otherTenant = $this->ahmad->tenant_id + 999;

    expect(fn () => DB::table('user_activity_days')->insert(['user_id' => $this->ahmad->id, 'day' => now()->toDateString(), 'tenant_id' => $otherTenant]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('picks the landing page by role', function () {
    expect(HomeRedirect::pathFor($this->ahmad))->toBe('/home');
});
