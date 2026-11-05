<?php

// Include from routes/web.php:  require __DIR__.'/platform.php';

use App\Http\Controllers\Platform;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\ReadOnlySupportSession;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', Platform\DashboardController::class)->name('dashboard');

    Route::get('/tenants', [Platform\TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [Platform\TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{publicId}', [Platform\TenantController::class, 'show'])->name('tenants.show');
    Route::patch('/tenants/{publicId}/status', [Platform\TenantController::class, 'updateStatus'])->name('tenants.status');
    Route::post('/tenants/{publicId}/credits', [Platform\TenantController::class, 'grantCredits'])->name('tenants.credits');
    Route::post('/tenants/{publicId}/support', [Platform\SupportSessionController::class, 'store'])->name('support.start');

    Route::get('/support/{supportSession}/users', [Platform\SupportSessionController::class, 'users'])
        ->middleware(ReadOnlySupportSession::class)->name('support.users');
    Route::delete('/support/{supportSession}', [Platform\SupportSessionController::class, 'destroy'])->name('support.end');

    Route::get('/providers', [Platform\ProviderController::class, 'index'])->name('providers.index');
    Route::patch('/providers/{provider:key}', [Platform\ProviderController::class, 'update'])->name('providers.update');
    Route::put('/providers/{provider:key}/key', [Platform\ProviderController::class, 'storeKey'])->name('providers.key');
    Route::post('/providers/{provider:key}/test', [Platform\ProviderController::class, 'test'])->middleware('throttle:6,1')->name('providers.test');

    Route::get('/agents', [Platform\AgentController::class, 'index'])->name('agents.index');
    Route::post('/agents', [Platform\AgentController::class, 'store'])->name('agents.store');
    Route::get('/agents/{key}', [Platform\AgentController::class, 'show'])->name('agents.show');
    Route::post('/agents/{key}/drafts', [Platform\AgentVersionController::class, 'storeDraft'])->name('agents.drafts.store');
    Route::post('/agents/{key}/test-cases', [Platform\AgentTestCaseController::class, 'store'])->name('agents.cases.store');
    Route::delete('/test-cases/{case}', [Platform\AgentTestCaseController::class, 'destroy'])->name('agents.cases.destroy');

    Route::patch('/versions/{version}', [Platform\AgentVersionController::class, 'update'])->name('versions.update');
    Route::post('/versions/{version}/submit', [Platform\AgentVersionController::class, 'submit'])->name('versions.submit');
    Route::post('/versions/{version}/tests', [Platform\AgentVersionController::class, 'runTests'])->middleware('throttle:10,1')->name('versions.tests');
    Route::post('/versions/{version}/rollout', [Platform\AgentVersionController::class, 'rollout'])->name('versions.rollout');
    Route::post('/versions/{version}/promote', [Platform\AgentVersionController::class, 'promote'])->name('versions.promote');
    Route::post('/versions/{version}/rollback', [Platform\AgentVersionController::class, 'rollback'])->name('versions.rollback');

    Route::get('/framework', [Platform\FrameworkController::class, 'edit'])->name('framework.edit');
    Route::post('/framework/preview', [Platform\FrameworkController::class, 'preview'])->name('framework.preview');
    Route::put('/framework', [Platform\FrameworkController::class, 'update'])->name('framework.update');

    Route::get('/audit', [Platform\AuditController::class, 'index'])->name('audit.index');
});
