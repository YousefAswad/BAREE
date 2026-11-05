<?php

// Include from routes/web.php:  require __DIR__.'/operations.php';

use App\Http\Controllers\Institution;
use App\Http\Controllers\Operations;
use App\Http\Controllers\Platform;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

// Webhooks: CSRF-exempt (see bootstrap/app.php), authenticated by signatures.
Route::post('/webhooks/payments/{provider}', Operations\PaymentWebhookController::class)->middleware('throttle:120,1')->name('webhooks.payments');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1')->name('webhooks.whatsapp');

Route::get('/health/deep', \App\Http\Controllers\HealthController::class)->middleware('throttle:30,1')->name('health.deep');

Route::post('/locale', Operations\LocaleController::class)->middleware('throttle:30,1')->name('locale');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/home', [Operations\FeedController::class, 'index'])->name('feed');
    Route::post('/posts/{post}/click', [Operations\FeedController::class, 'click'])->name('posts.click');

    Route::get('/notifications', [Operations\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [Operations\NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [Operations\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/settings/notifications', [Operations\NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/settings/notifications', [Operations\NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

    Route::post('/settings/whatsapp', [Operations\PhoneController::class, 'start'])->middleware('throttle:3,10')->name('whatsapp.start');
    Route::post('/settings/whatsapp/verify', [Operations\PhoneController::class, 'verify'])->middleware('throttle:10,10')->name('whatsapp.verify');
    Route::delete('/settings/whatsapp', [Operations\PhoneController::class, 'optOut'])->name('whatsapp.opt-out');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/content', [Institution\ContentController::class, 'index'])->name('content.index');
        Route::post('/content', [Institution\ContentController::class, 'store'])->name('content.store');
        Route::post('/content/estimate', [Institution\ContentController::class, 'estimate'])->middleware('throttle:30,1')->name('content.estimate');
        Route::get('/assignments', [Institution\AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments', [Institution\AssignmentController::class, 'store'])->middleware('throttle:20,1')->name('assignments.store');
        Route::get('/analytics', Institution\AnalyticsController::class)->name('analytics');
        Route::get('/billing', [Institution\BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/invoices/{invoice}/pay', [Institution\BillingController::class, 'pay'])->name('billing.pay');
        Route::post('/billing/seats', [Institution\BillingController::class, 'seats'])->name('billing.seats');
        Route::post('/billing/change', [Institution\BillingController::class, 'change'])->name('billing.change');
    });
});

Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/billing', [Platform\BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscriptions', [Platform\BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/billing/invoices/{publicId}/paid', [Platform\BillingController::class, 'markPaid'])->name('billing.paid');
    Route::get('/content', [Platform\ContentController::class, 'index'])->name('content.index');
    Route::post('/content', [Platform\ContentController::class, 'store'])->name('content.store');
    Route::post('/feedback/{agentKey}/convert', [Platform\FeedbackController::class, 'convert'])->name('feedback.convert');
});
