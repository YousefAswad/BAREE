<?php

// Include from routes/web.php:  require __DIR__.'/learning.php';

use App\Http\Controllers\Institution\KnowledgeController;
use App\Http\Controllers\Learning;
use App\Http\Controllers\Platform;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

// Public certificate verification (no personal data beyond what is printed on the certificate).
Route::get('/certificates/{serial}', [Learning\AcademyController::class, 'verify'])->middleware('throttle:30,1')->name('certificates.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/lab', [Learning\LabController::class, 'index'])->name('lab.index');
    Route::get('/lab/missions/{key}', [Learning\LabController::class, 'show'])->name('lab.show');
    Route::post('/lab/attempts/{attempt}/submit', [Learning\LabController::class, 'submit'])->middleware('throttle:ai')->name('lab.submit');

    Route::get('/academy', [Learning\AcademyController::class, 'index'])->name('academy.index');
    Route::get('/academy/{key}', [Learning\AcademyController::class, 'show'])->name('academy.show');
    Route::post('/academy/lessons/{lesson}/complete', [Learning\AcademyController::class, 'complete'])->middleware('throttle:60,1')->name('academy.complete');
    Route::get('/certificates/{serial}/download', [Learning\AcademyController::class, 'download'])->name('certificates.download');

    Route::get('/hub', [Learning\HubController::class, 'index'])->name('hub.index');
    Route::post('/hub/ask', [Learning\HubController::class, 'ask'])->middleware('throttle:ai')->name('hub.ask');
    Route::get('/hub/documents/{document}', [Learning\HubController::class, 'document'])->name('hub.document');
    Route::post('/hub/questions/{question}/helpful', [Learning\HubController::class, 'helpful'])->name('hub.helpful');

    Route::post('/runs/{run}/feedback', [Learning\FeedbackController::class, 'store'])->middleware('throttle:60,1')->name('runs.feedback');

    Route::get('/settings/ai-apps', [Learning\AiAppsController::class, 'index'])->name('ai-apps.index');
    Route::post('/settings/ai-apps', [Learning\AiAppsController::class, 'store'])->middleware('throttle:10,1')->name('ai-apps.store');
    Route::delete('/settings/ai-apps/{id}', [Learning\AiAppsController::class, 'destroy'])->name('ai-apps.destroy');

    Route::get('/admin/knowledge', [KnowledgeController::class, 'index'])->name('admin.knowledge.index');
    Route::post('/admin/knowledge', [KnowledgeController::class, 'store'])->middleware('throttle:20,1')->name('admin.knowledge.store');
    Route::delete('/admin/knowledge/{document}', [KnowledgeController::class, 'destroy'])->name('admin.knowledge.destroy');
});

Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/feedback', [Platform\FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/learning', [Platform\LearningController::class, 'index'])->name('learning.index');
    Route::post('/learning/{type}/{id}/toggle', [Platform\LearningController::class, 'toggle'])->name('learning.toggle');
    Route::post('/learning/documents', [Platform\LearningController::class, 'storeDocument'])->name('learning.documents.store');
});

// Phase 6: Super Admin editors and personal settings
Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/prompts', [Platform\PromptBlockController::class, 'index'])->name('prompts.index');
    Route::post('/prompts', [Platform\PromptBlockController::class, 'store'])->name('prompts.store');
    Route::post('/prompts/{block}/publish', [Platform\PromptBlockController::class, 'publish'])->name('prompts.publish');
    Route::get('/questions', [Platform\QuestionBankController::class, 'index'])->name('questions.index');
    Route::post('/questions', [Platform\QuestionBankController::class, 'store'])->name('questions.store');
    Route::post('/questions/{question}/retire', [Platform\QuestionBankController::class, 'retire'])->name('questions.retire');
    Route::get('/plans', [Platform\PlanController::class, 'index'])->name('plans.index');
    Route::put('/plans/{plan}', [Platform\PlanController::class, 'update'])->name('plans.update');
    Route::post('/authoring/courses', [Platform\AuthoringController::class, 'storeCourse'])->name('authoring.courses.store');
    Route::get('/authoring/courses/{key}', [Platform\AuthoringController::class, 'course'])->name('authoring.course');
    Route::post('/authoring/courses/{course}/modules', [Platform\AuthoringController::class, 'storeModule'])->name('authoring.modules.store');
    Route::post('/authoring/courses/{course}/lessons', [Platform\AuthoringController::class, 'storeLesson'])->name('authoring.lessons.store');
    Route::post('/authoring/missions', [Platform\AuthoringController::class, 'storeMission'])->name('authoring.missions.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/passport', \App\Http\Controllers\Me\PassportController::class)->name('passport');
    Route::get('/settings/privacy', [\App\Http\Controllers\Me\PrivacyController::class, 'show'])->name('privacy.show');
    Route::put('/settings/privacy', [\App\Http\Controllers\Me\PrivacyController::class, 'update'])->name('privacy.update');
    Route::get('/settings/privacy/export', [\App\Http\Controllers\Me\PrivacyController::class, 'export'])->middleware('throttle:3,60')->name('privacy.export');
    Route::delete('/settings/account', [\App\Http\Controllers\Me\PrivacyController::class, 'destroy'])->middleware('throttle:3,60')->name('account.destroy');
    Route::get('/studio/tools/{key}/settings', [\App\Http\Controllers\Me\AgentPreferenceController::class, 'show'])->name('agents.preferences');
    Route::put('/studio/tools/{key}/settings', [\App\Http\Controllers\Me\AgentPreferenceController::class, 'update'])->name('agents.preferences.update');
    Route::post('/studio/tools/{key}/settings/files', [\App\Http\Controllers\Me\AgentPreferenceController::class, 'upload'])->middleware('throttle:20,1')->name('agents.preferences.files');
    Route::delete('/studio/personal-files/{file}', [\App\Http\Controllers\Me\AgentPreferenceController::class, 'destroyFile'])->name('agents.preferences.files.destroy');
});
