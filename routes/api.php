<?php

use App\Http\Controllers\Api\V1\Admin\AttendanceAdminController;
use App\Http\Controllers\Api\V1\Admin\AdminMembershipController;
use App\Http\Controllers\Api\V1\Admin\ChatAdminController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\FaceAdminController;
use App\Http\Controllers\Api\V1\Admin\FeedbackAdminController;
use App\Http\Controllers\Api\V1\Admin\MemberController;
use App\Http\Controllers\Api\V1\Admin\NotificationAdminController;
use App\Http\Controllers\Api\V1\Admin\PackageController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\TrainerAdminController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\Member\AttendanceController;
use App\Http\Controllers\Api\V1\Member\BookingController;
use App\Http\Controllers\Api\V1\Member\ChatController;
use App\Http\Controllers\Api\V1\Member\FeedbackController;
use App\Http\Controllers\Api\V1\Member\MembershipController;
use App\Http\Controllers\Api\V1\Member\NotificationController;
use App\Http\Controllers\Api\V1\Member\ProgressController;
use App\Http\Controllers\Api\V1\Member\TrainerController;
use App\Http\Controllers\Api\V1\Member\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['rate.headers:5,1'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/auth/login/google', [AuthController::class, 'googleLogin']);
});

Route::middleware(['rate.headers:3,15'])->group(function () {
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::get('/memberships/packages', [MembershipController::class, 'packages']);
Route::get('/memberships/packages/{id}', [MembershipController::class, 'packageShow']);

Route::get('/faq', [FaqController::class, 'index']);
Route::get('/faq/categories', [FaqController::class, 'categories']);

Route::middleware(['auth.jwt', 'rate.headers:300,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateMe']);
    Route::put('/auth/me/password', [AuthController::class, 'changePassword']);

    Route::get('/memberships/active', [MembershipController::class, 'active']);
    Route::post('/memberships/renew', [MembershipController::class, 'renew']);
    Route::get('/memberships/history', [MembershipController::class, 'history']);

    Route::prefix('attendance')->group(function () {
        Route::post('/face/register', [AttendanceController::class, 'registerFace']);
        Route::get('/face/status', [AttendanceController::class, 'faceStatus']);
        Route::middleware(['rate.headers:10,60'])->post('/checkin', [AttendanceController::class, 'checkin']);
        Route::get('/history', [AttendanceController::class, 'history']);
    });

    Route::get('/trainers', [TrainerController::class, 'index']);
    Route::get('/trainers/{id}', [TrainerController::class, 'show']);
    Route::post('/trainers/{id}/booking', [TrainerController::class, 'book']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);

    Route::get('/workout-plans', [WorkoutController::class, 'plans']);
    Route::post('/workout-logs', [WorkoutController::class, 'storeLog']);
    Route::get('/workout-logs', [WorkoutController::class, 'logs']);

    Route::prefix('progress')->group(function () {
        Route::post('/weight', [ProgressController::class, 'storeWeight']);
        Route::get('/weight', [ProgressController::class, 'weightHistory']);
        Route::delete('/weight/{id}', [ProgressController::class, 'deleteWeight']);
        Route::get('/chart', [ProgressController::class, 'chart']);
        Route::get('/summary', [ProgressController::class, 'summary']);
    });

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    Route::get('/chat/conversations', [ChatController::class, 'conversations']);
    Route::post('/chat/conversations', [ChatController::class, 'storeConversation']);
    Route::get('/chat/conversations/{id}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/conversations/{id}/messages', [ChatController::class, 'sendMessage']);

    Route::post('/feedback', [FeedbackController::class, 'store']);
});

Route::middleware(['auth.jwt', 'role:admin', 'rate.headers:500,1'])->prefix('admin')->group(function () {
    Route::get('/memberships', [AdminMembershipController::class, 'index']);
    Route::post('/memberships/activate', [AdminMembershipController::class, 'activate']);
    Route::get('/memberships/renewals', [AdminMembershipController::class, 'renewals']);
    Route::post('/memberships/renewals/{id}/approve', [AdminMembershipController::class, 'approveRenewal']);
    Route::post('/memberships/renewals/{id}/reject', [AdminMembershipController::class, 'rejectRenewal']);
    Route::put('/memberships/{id}/renew', [AdminMembershipController::class, 'renew']);
    Route::get('/memberships/expired', [AdminMembershipController::class, 'expired']);

    Route::apiResource('packages', PackageController::class)->except(['show']);

    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/search', [MemberController::class, 'search']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::get('/members/export', [MemberController::class, 'export']);
    Route::post('/members/import', [MemberController::class, 'import']);
    Route::get('/members/{id}', [MemberController::class, 'show']);
    Route::put('/members/{id}', [MemberController::class, 'update']);
    Route::delete('/members/{id}', [MemberController::class, 'destroy']);

    Route::get('/attendance/live', [AttendanceAdminController::class, 'live']);
    Route::post('/attendance/verify', [AttendanceAdminController::class, 'verify']);
    Route::get('/attendance/history', [AttendanceAdminController::class, 'history']);
    Route::get('/attendance/recap', [AttendanceAdminController::class, 'recap']);
    Route::middleware(['rate.headers:120,1'])->post('/attendance/kiosk-checkin', [AttendanceAdminController::class, 'kioskCheckin']);

    Route::get('/faces', [FaceAdminController::class, 'index']);
    Route::post('/faces/{id}/verify', [FaceAdminController::class, 'verify']);
    Route::post('/faces/{id}/reject', [FaceAdminController::class, 'reject']);
    Route::delete('/faces/{id}', [FaceAdminController::class, 'destroy']);

    Route::get('/trainers', [TrainerAdminController::class, 'index']);
    Route::post('/trainers', [TrainerAdminController::class, 'store']);
    Route::put('/trainers/{id}', [TrainerAdminController::class, 'update']);
    Route::delete('/trainers/{id}', [TrainerAdminController::class, 'destroy']);
    Route::get('/trainers/{id}/schedule', [TrainerAdminController::class, 'schedule']);
    Route::post('/trainers/{id}/schedule', [TrainerAdminController::class, 'storeSchedule']);
    Route::get('/trainers/{id}/performance', [TrainerAdminController::class, 'performance']);
    Route::post('/workout-plans', [TrainerAdminController::class, 'storeWorkoutPlan']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/reports/members', [ReportController::class, 'members']);
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
    Route::get('/reports/finance', [ReportController::class, 'finance']);
    Route::get('/reports/export', [ReportController::class, 'export']);

    Route::post('/notifications/send', [NotificationAdminController::class, 'send']);

    Route::get('/chat/conversations', [ChatAdminController::class, 'conversations']);
    Route::get('/chat/conversations/{id}/messages', [ChatAdminController::class, 'messages']);
    Route::post('/chat/conversations/{id}/messages', [ChatAdminController::class, 'sendMessage']);

    Route::get('/feedback', [FeedbackAdminController::class, 'index']);
});
