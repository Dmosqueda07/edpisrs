<?php

use App\Http\Controllers\ItSupportRequestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('it-support-requests/{it_support_request}/attachment', [ItSupportRequestController::class, 'downloadAttachment'])
        ->name('it-support-requests.attachment');
    Route::get('it-support-requests/{it_support_request}/attachments/{attachment}', [ItSupportRequestController::class, 'downloadRequestAttachment'])
        ->name('it-support-requests.attachments.download');
    Route::get('it-support-requests/{it_support_request}/comments/{comment}/proof', [ItSupportRequestController::class, 'downloadProof'])
        ->name('it-support-requests.comment-proof');
    Route::post('it-support-requests/{it_support_request}/comments', [ItSupportRequestController::class, 'storeComment'])
        ->name('it-support-requests.comments.store');
    Route::patch('it-support-requests/{it_support_request}/start', [ItSupportRequestController::class, 'startWork'])
        ->name('it-support-requests.start');
    Route::patch('it-support-requests/{it_support_request}/resolve', [ItSupportRequestController::class, 'resolve'])
        ->name('it-support-requests.resolve');
    Route::patch('it-support-requests/{it_support_request}/complete', [ItSupportRequestController::class, 'confirmCompletion'])
        ->name('it-support-requests.complete');
    Route::patch('it-support-requests/{it_support_request}/cancel', [ItSupportRequestController::class, 'cancel'])
        ->name('it-support-requests.cancel');
    Route::patch('it-support-requests/{it_support_request}/reopen', [ItSupportRequestController::class, 'reopen'])
        ->name('it-support-requests.reopen');
    Route::patch('it-support-requests/{it_support_request}/approval', [ItSupportRequestController::class, 'decideApproval'])
        ->name('it-support-requests.approval');

    Route::get('approvals/it-support-requests', [ItSupportRequestController::class, 'approvals'])
        ->name('approvals.it-support-requests.index');
    Route::get('edp/it-support-requests', [ItSupportRequestController::class, 'manage'])
        ->name('edp.it-support-requests.index');
    Route::get('edp/assigned-requests', [ItSupportRequestController::class, 'assigned'])
        ->name('edp.assigned-requests.index');
    Route::patch('edp/it-support-requests/{it_support_request}/assign', [ItSupportRequestController::class, 'assign'])
        ->name('edp.it-support-requests.assign');

    Route::resource('it-support-requests', ItSupportRequestController::class)
        ->only(['index', 'create', 'store', 'show']);
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
