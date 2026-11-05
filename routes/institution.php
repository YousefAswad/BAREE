<?php

// Include from routes/web.php:  require __DIR__.'/institution.php';

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\Institution;
use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\RequireTwoFactorForAdmins;
use Illuminate\Support\Facades\Route;

// Public, token-based acceptance. Rate-limited by IP; tokens are 64 random chars, stored hashed, single-use.
Route::middleware(['guest', 'throttle:invitations'])->group(function () {
    Route::get('/invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [AcceptInvitationController::class, 'store'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding/profile', [OnboardingController::class, 'profile'])->name('onboarding.profile');
    Route::post('/onboarding/profile', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('/assessments/{key}/intro', [OnboardingController::class, 'intro'])->name('assessments.intro');
});

Route::middleware(['auth', 'verified', RequireTwoFactorForAdmins::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Institution\DashboardController::class)->name('dashboard');

    Route::get('/users', [Institution\UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{publicId}', [Institution\UserController::class, 'update'])->name('users.update');
    Route::post('/invitations', [Institution\InvitationController::class, 'store'])->middleware('throttle:60,1')->name('invitations.store');
    Route::delete('/invitations/{invitation}', [Institution\InvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::post('/imports', [Institution\UserImportController::class, 'store'])->middleware('throttle:10,1')->name('imports.store');
    Route::get('/imports/{import}', [Institution\UserImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{import}/commit', [Institution\UserImportController::class, 'commit'])->name('imports.commit');

    Route::get('/structure', [Institution\StructureController::class, 'index'])->name('structure.index');
    Route::post('/structure/campuses', [Institution\StructureController::class, 'storeCampus'])->name('structure.campuses.store');
    Route::post('/structure/departments', [Institution\StructureController::class, 'storeDepartment'])->name('structure.departments.store');
    Route::post('/structure/job-titles', [Institution\StructureController::class, 'storeJobTitle'])->name('structure.titles.store');

    Route::get('/roles', [Institution\RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles/{role}/clone', [Institution\RoleController::class, 'clone'])->name('roles.clone');
    Route::put('/roles/{role}/permissions', [Institution\RoleController::class, 'updatePermissions'])->name('roles.permissions');
    Route::post('/roles/{role}/assignments', [Institution\RoleController::class, 'assign'])->name('roles.assign');
    Route::delete('/assignments/{assignment}', [Institution\RoleController::class, 'unassign'])->name('roles.unassign');

    Route::get('/segments', [Institution\SegmentController::class, 'index'])->name('segments.index');
    Route::post('/segments', [Institution\SegmentController::class, 'store'])->name('segments.store');
    Route::put('/segments/{segment}', [Institution\SegmentController::class, 'update'])->name('segments.update');
    Route::post('/segments/preview', [Institution\SegmentController::class, 'preview'])->middleware('throttle:30,1')->name('segments.preview');

    Route::get('/campaigns', [Institution\CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns/audience', [Institution\CampaignController::class, 'audienceCount'])->name('campaigns.audience');
    Route::post('/campaigns', [Institution\CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [Institution\CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/launch', [Institution\CampaignController::class, 'launch'])->name('campaigns.launch');

    Route::get('/agents', [Institution\AgentAccessController::class, 'index'])->name('agents.index');
    Route::put('/agents/{agent}', [Institution\AgentAccessController::class, 'update'])->name('agents.update');
    Route::put('/providers/{provider:key}/key', [Institution\AgentAccessController::class, 'storeKey'])->name('providers.key');

    Route::get('/privacy', [Institution\PrivacyController::class, 'edit'])->name('privacy.edit');
    Route::put('/privacy', [Institution\PrivacyController::class, 'update'])->name('privacy.update');
    Route::post('/privacy/terms', [Institution\PrivacyController::class, 'storeTerms'])->name('privacy.terms');
});
