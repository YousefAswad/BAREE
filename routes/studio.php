<?php

// Include from routes/web.php:  require __DIR__.'/studio.php';

use App\Http\Controllers\Studio;
use Illuminate\Support\Facades\Route;

// Public read-only share links (rate-limited, hashed tokens).
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/s/{token}', [Studio\ShareController::class, 'view'])->name('share.view');
    Route::get('/s/{token}/media/{mediaId}', [Studio\ShareController::class, 'media'])->name('share.media');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/studio', [Studio\StudioController::class, 'home'])->name('studio.home');
    Route::get('/studio/tools/{key}', [Studio\StudioController::class, 'tool'])->name('studio.tool');

    Route::middleware('throttle:ai')->group(function () {
        Route::post('/studio/tools/{key}/runs', [Studio\RunController::class, 'store'])->name('studio.runs.store');
        Route::post('/studio/workflows/{workflow}/steps/{step}/regenerate', [Studio\RunController::class, 'regenerate'])->name('studio.workflows.regenerate');
        Route::post('/studio/media', [Studio\MediaController::class, 'store'])->name('studio.media.store');
        Route::post('/outputs/{output}/refine', [Studio\OutputController::class, 'refine'])->name('outputs.refine');
        Route::post('/scenarios/{output}/scenes/{scene}/generate', [Studio\ScenarioController::class, 'generate'])->name('scenarios.generate');
    });

    Route::get('/studio/runs/{run}', [Studio\RunController::class, 'showRun'])->name('studio.runs.show');
    Route::get('/studio/workflows/{workflow}', [Studio\RunController::class, 'showWorkflow'])->name('studio.workflows.show');
    Route::get('/studio/media/{media}', [Studio\MediaController::class, 'show'])->name('studio.media.show');
    Route::get('/studio/media/{media}/file', [Studio\MediaController::class, 'file'])->name('studio.media.file');

    Route::post('/files', [Studio\FileController::class, 'store'])->middleware('throttle:30,1')->name('files.store');
    Route::get('/files/{file}', [Studio\FileController::class, 'show'])->name('files.show');
    Route::delete('/files/{file}', [Studio\FileController::class, 'destroy'])->name('files.destroy');

    Route::get('/workspaces', [Studio\WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [Studio\WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{workspace}', [Studio\WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('/workspaces/{workspace}', [Studio\WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::put('/workspaces/{workspace}/memory', [Studio\WorkspaceController::class, 'memory'])->name('workspaces.memory');
    Route::post('/workspaces/{workspace}/members', [Studio\WorkspaceController::class, 'share'])->name('workspaces.share');

    Route::get('/outputs/{output}', [Studio\OutputController::class, 'show'])->name('outputs.show');
    Route::put('/outputs/{output}', [Studio\OutputController::class, 'update'])->name('outputs.update');
    Route::post('/outputs/{output}/archive', [Studio\OutputController::class, 'archive'])->name('outputs.archive');
    Route::post('/outputs/{output}/exports', [Studio\ExportController::class, 'store'])->middleware('throttle:20,1')->name('exports.store');
    Route::get('/exports/{export}', [Studio\ExportController::class, 'download'])->name('exports.download');
    Route::post('/outputs/{output}/share', [Studio\ShareController::class, 'store'])->name('share.store');
    Route::delete('/share-links/{link}', [Studio\ShareController::class, 'revoke'])->name('share.revoke');

    Route::post('/scenarios', [Studio\ScenarioController::class, 'store'])->name('scenarios.store');
    Route::post('/scenarios/{output}/scenes', [Studio\ScenarioController::class, 'addScene'])->name('scenarios.scenes.store');
    Route::patch('/scenarios/{output}/scenes/{scene}', [Studio\ScenarioController::class, 'updateScene'])->name('scenarios.scenes.update');
    Route::put('/scenarios/{output}/order', [Studio\ScenarioController::class, 'reorder'])->name('scenarios.reorder');
    Route::delete('/scenarios/{output}/scenes/{scene}', [Studio\ScenarioController::class, 'destroyScene'])->name('scenarios.scenes.destroy');
});
