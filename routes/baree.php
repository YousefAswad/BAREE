<?php

// Include from routes/web.php:  require __DIR__.'/baree.php';

use App\Http\Controllers\AgentRunController;
use App\Http\Controllers\AssessmentController;
use App\Http\Middleware\SetTenantContext;
use App\Support\HomeRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', SetTenantContext::class])->group(function () {
    Route::middleware('throttle:assessment')->group(function () {
        Route::post('/assessments/{template:key}/start', [AssessmentController::class, 'start'])->name('assessments.start');
        Route::get('/assessments/attempts/{attempt}', [AssessmentController::class, 'show'])->name('assessments.show');
        Route::post('/assessments/attempts/{attempt}/answers', [AssessmentController::class, 'answer'])->name('assessments.answer');
        Route::post('/assessments/attempts/{attempt}/submit', [AssessmentController::class, 'submit'])->name('assessments.submit');
    });

    Route::middleware('throttle:ai')->group(function () {
        Route::post('/agents/{agentKey}/runs', [AgentRunController::class, 'store'])->name('agents.runs.store');
        Route::get('/runs/{run}', [AgentRunController::class, 'show'])->name('agents.runs.show');
    });
});

// The starter kit's generic /dashboard is a legacy URL: send each user to their own area (platform staff -> /platform, admins -> /admin,
// everyone else -> /home). Declared here (loaded after the starter kit's routes) because the last route registered for a URI wins.
Route::middleware(['auth', 'verified'])->get('/dashboard', fn (Request $request) => redirect(HomeRedirect::pathFor($request->user())))->name('dashboard');
