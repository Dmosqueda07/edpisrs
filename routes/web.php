<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItSupportRequestController;
use App\Http\Controllers\ProfileSignatureController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'password.change'])->group(function () {
    Route::get('password/change', [ForcedPasswordChangeController::class, 'edit'])->name('password.change.edit');
    Route::put('password/change', [ForcedPasswordChangeController::class, 'update'])->name('password.change.update');

    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('reports', [ReportsController::class, 'index'])->middleware('can:viewReports')->name('reports.index');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('can:viewActivityLogs')->name('activity-logs.index');
    Route::patch('users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
    Route::patch('users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
    Route::patch('users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    Route::patch('users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role');
    Route::patch('users/{user}/division', [UserManagementController::class, 'updateDivision'])->name('users.division');
    Route::put('users/{user}/password', [UserManagementController::class, 'resetPassword'])->name('users.password');
    Route::post('profile/signature', [ProfileSignatureController::class, 'store'])->name('profile.signature.store');
    Route::get('users/{user}/signature', [ProfileSignatureController::class, 'show'])->name('users.signature');

    Route::get('it-support-requests/{it_support_request}/attachment', [ItSupportRequestController::class, 'downloadAttachment'])
        ->name('it-support-requests.attachment');
    Route::get('it-support-requests/{it_support_request}/attachments/{attachment}', [ItSupportRequestController::class, 'downloadRequestAttachment'])
        ->name('it-support-requests.attachments.download');
    Route::post('it-support-requests/{it_support_request}/attachments/proof', [ItSupportRequestController::class, 'storeProofAttachment'])
        ->name('it-support-requests.attachments.store-proof');
    Route::patch('it-support-requests/{it_support_request}/start', [ItSupportRequestController::class, 'startWork'])
        ->name('it-support-requests.start');
    Route::patch('it-support-requests/{it_support_request}/resolve', [ItSupportRequestController::class, 'resolve'])
        ->name('it-support-requests.resolve');
    Route::patch('it-support-requests/{it_support_request}/hold', [ItSupportRequestController::class, 'hold'])
        ->name('it-support-requests.hold');
    Route::patch('it-support-requests/{it_support_request}/resume', [ItSupportRequestController::class, 'resume'])
        ->name('it-support-requests.resume');
    Route::patch('it-support-requests/{it_support_request}/complete', [ItSupportRequestController::class, 'confirmCompletion'])
        ->name('it-support-requests.complete');
    Route::patch('it-support-requests/{it_support_request}/cancel', [ItSupportRequestController::class, 'cancel'])
        ->name('it-support-requests.cancel');
    Route::patch('it-support-requests/{it_support_request}/reopen', [ItSupportRequestController::class, 'reopen'])
        ->name('it-support-requests.reopen');
    Route::get('edp/it-support-requests', [ItSupportRequestController::class, 'manage'])
        ->name('edp.it-support-requests.index');
    Route::get('edp/assigned-requests', [ItSupportRequestController::class, 'assigned'])
        ->name('edp.assigned-requests.index');
    Route::patch('edp/it-support-requests/{it_support_request}/claim', [ItSupportRequestController::class, 'claim'])
        ->name('edp.it-support-requests.claim');
    Route::patch('edp/it-support-requests/{it_support_request}/release', [ItSupportRequestController::class, 'release'])
        ->name('edp.it-support-requests.release');

    Route::resource('it-support-requests', ItSupportRequestController::class)
        ->only(['index', 'create', 'store', 'show']);
});

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'password.change'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'password.change'])
    ->name('profile');

require __DIR__.'/auth.php';
