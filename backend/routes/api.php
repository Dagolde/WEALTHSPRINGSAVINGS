<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint (no authentication required)
Route::get('v1/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'rotational-contribution-api'
    ]);
});

// Public authentication routes
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,15'); // 5 attempts per 15 minutes
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])
        ->middleware('throttle:5,15'); // 5 attempts per 15 minutes
});

// Webhook routes (no authentication required - external services)
Route::prefix('v1/webhooks')->group(function () {
    Route::post('/paystack', [WebhookController::class, 'handlePaystackWebhook']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Protected routes that require authentication and active user status
Route::middleware(['auth:sanctum', 'check.user.status'])->group(function () {
    // User KYC routes
    Route::prefix('v1/user')->group(function () {
        Route::post('/kyc/submit', [UserController::class, 'submitKyc']);
        Route::get('/kyc/status', [UserController::class, 'getKycStatus']);
        
        // Bank account routes
        Route::post('/bank-account', [UserController::class, 'addBankAccount']);
        Route::get('/bank-accounts', [UserController::class, 'getBankAccounts']);
        
        // Profile management routes
        Route::get('/profile', [UserController::class, 'getProfile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/profile/picture', [UserController::class, 'uploadProfilePicture']);
    });

    // Group management routes
    Route::prefix('v1/groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::post('/', [GroupController::class, 'store']);
        Route::post('/join', [GroupController::class, 'joinByCode']); // Join by group code
        Route::get('/{id}', [GroupController::class, 'show']);
        Route::post('/{id}/join', [GroupController::class, 'join']); // Join by group ID
        Route::post('/{id}/start', [GroupController::class, 'start']);
        Route::get('/{id}/members', [GroupController::class, 'members']);
        Route::get('/{id}/schedule', [GroupController::class, 'schedule']);
    });

    // Contribution management routes
    Route::prefix('v1/contributions')->group(function () {
        Route::get('/', [ContributionController::class, 'index']);
        Route::post('/', [ContributionController::class, 'store']);
        Route::post('/verify', [ContributionController::class, 'verify']);
        Route::get('/missed', [ContributionController::class, 'missed']);
    });

    // Wallet management routes
    Route::prefix('v1/wallet')->group(function () {
        Route::post('/fund', [WalletController::class, 'fund']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/balance', [WalletController::class, 'getBalance']);
        Route::get('/transactions', [WalletController::class, 'getTransactions']);
        Route::get('/transactions/{id}', [WalletController::class, 'getTransaction']);
    });

    // Payment management routes
    Route::prefix('v1/payments')->group(function () {
        Route::get('/banks', [WalletController::class, 'getBanks']);
    });

    // Payout management routes
    Route::prefix('v1/payouts')->group(function () {
        Route::get('/schedule/{groupId}', [PayoutController::class, 'schedule']);
        Route::get('/history', [PayoutController::class, 'history']);
        Route::get('/{id}', [PayoutController::class, 'show']);
        Route::post('/{id}/retry', [PayoutController::class, 'retry']);
    });

    // Group-specific contribution routes
    Route::get('v1/groups/{groupId}/contributions', [ContributionController::class, 'groupContributions']);
});

// Admin routes - require authentication and admin role
Route::middleware(['auth:sanctum', 'admin'])->prefix('v1/admin')->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);

    // User management
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{id}', [AdminController::class, 'getUserDetails']);
    Route::put('/users/{id}/suspend', [AdminController::class, 'suspendUser']);
    Route::put('/users/{id}/activate', [AdminController::class, 'activateUser']);

    // KYC management
    Route::get('/kyc/pending', [AdminController::class, 'listPendingKyc']);
    Route::post('/kyc/{id}/approve', [AdminController::class, 'approveKyc']);
    Route::post('/kyc/{id}/reject', [AdminController::class, 'rejectKyc']);

    // Group management
    Route::get('/groups', [AdminController::class, 'listGroups']);
    Route::get('/groups/{id}', [AdminController::class, 'getGroupDetails']);

    // Withdrawal management
    Route::get('/withdrawals/pending', [AdminController::class, 'listPendingWithdrawals']);
    Route::post('/withdrawals/{id}/approve', [AdminController::class, 'approveWithdrawal']);
    Route::post('/withdrawals/{id}/reject', [AdminController::class, 'rejectWithdrawal']);

    // Analytics
    Route::get('/analytics/users', [AdminController::class, 'getUserAnalytics']);
    Route::get('/analytics/groups', [AdminController::class, 'getGroupAnalytics']);
    Route::get('/analytics/transactions', [AdminController::class, 'getTransactionAnalytics']);
    Route::get('/analytics/revenue', [AdminController::class, 'getRevenueAnalytics']);

    // System Settings
    Route::get('/settings', [AdminController::class, 'getSettings']);
    Route::put('/settings', [AdminController::class, 'updateSettings']);

    // Contribution management
    Route::get('/contributions', [AdminController::class, 'listContributions']);
    Route::get('/contributions/{id}', [AdminController::class, 'getContributionDetails']);
    Route::post('/contributions', [AdminController::class, 'recordContribution']);
    Route::post('/contributions/{id}/verify', [AdminController::class, 'verifyContribution']);

    // Group members
    Route::get('/groups/{id}/members', [AdminController::class, 'getGroupMembers']);

    // Permission management
    Route::put('/users/{id}/permissions', [AdminController::class, 'updatePermissions']);

    // Transaction management
    Route::prefix('transactions')->group(function () {
        Route::get('/', [AdminController::class, 'getTransactions']);
        Route::get('/stats', [AdminController::class, 'getTransactionStats']);
        Route::get('/{id}', [AdminController::class, 'getTransaction']);
        Route::post('/{id}/approve', [AdminController::class, 'approveTransaction']);
        Route::post('/{id}/reject', [AdminController::class, 'rejectTransaction']);
        Route::delete('/{id}', [AdminController::class, 'deleteTransaction']);
    });

    // Mobile app control
    Route::prefix('mobile')->group(function () {
        Route::get('/settings', [AdminController::class, 'getMobileSettings']);
        Route::put('/settings', [AdminController::class, 'updateMobileSettings']);
        Route::get('/sessions', [AdminController::class, 'getActiveSessions']);
        Route::delete('/sessions/{id}', [AdminController::class, 'revokeSession']);
        Route::delete('/users/{id}/sessions', [AdminController::class, 'revokeAllUserSessions']);
        Route::post('/notifications/push', [AdminController::class, 'sendPushNotification']);
        Route::get('/usage', [AdminController::class, 'getAppUsage']);
    });
});
