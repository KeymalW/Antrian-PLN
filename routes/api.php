<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ServiceController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    Route::middleware('role:petugas,admin')->group(function () {
        Route::put('/queue/{id}/call', [QueueController::class, 'callQueue']);
        Route::put('/queue/{id}/serve', [QueueController::class, 'serveQueue']);
        Route::put('/queue/{id}/skip', [QueueController::class, 'skipQueue']);
        Route::put('/queue/{id}/complete', [QueueController::class, 'completeQueue']);
        Route::put('/queue/{id}/restore', [QueueController::class, 'restore']);
        Route::get('/queue/trash', [QueueController::class, 'getTrash']);
        Route::delete('/queue/trash', [QueueController::class, 'emptyTrash']);
        Route::post('/queue/clear-history', [QueueController::class, 'clearHistory']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard/analitik', [DashboardController::class, 'analitik']);
        Route::get('/dashboard/export', [DashboardController::class, 'export']);

        // Kelola Akun
        Route::get('/accounts', [AccountController::class, 'index']);
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::put('/accounts/{id}', [AccountController::class, 'update']);
        Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);

        // Mutasi Kelola Layanan (GET /services publik di bawah)
        Route::post('/services', [ServiceController::class, 'store']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

        // Pengaturan — tulis (admin)
        Route::post('/settings/video-volume', [SettingsController::class, 'setVideoVolume']);

        Route::post('/settings/videos', [SettingsController::class, 'uploadVideo']);
        Route::delete('/settings/videos/{filename}', [SettingsController::class, 'deleteVideo']);

        Route::put('/settings/general', [SettingsController::class, 'updateGeneral']);
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo']);
        Route::post('/settings/ticket-text', [SettingsController::class, 'updateTicketText']);
        Route::post('/settings/video-links', [SettingsController::class, 'addVideoLink']);
        Route::delete('/settings/video-links/{id}', [SettingsController::class, 'deleteVideoLink']);
    });
});

// Publik (tanpa auth)
Route::get('/settings/video-volume', [SettingsController::class, 'getVideoVolume']);
Route::get('/settings/videos', [SettingsController::class, 'getVideos']);
Route::get('/settings/video-links', [SettingsController::class, 'getVideoLinks']);
Route::get('/settings/general', [SettingsController::class, 'getGeneral']);
Route::get('/settings/ticket-text', [SettingsController::class, 'getTicketText']);
Route::get('/services', [ServiceController::class, 'index']);

Route::get('/queue', [QueueController::class, 'index']);
Route::post('/queue/take', [QueueController::class, 'takeTicket']);
Route::get('/queue/stats', [QueueController::class, 'stats']);
Route::get('/queue/weekly', [QueueController::class, 'weekly']);
Route::get('/queue/active', [QueueController::class, 'activeCall']);
Route::get('/queue/last-called/{counterNumber}', [QueueController::class, 'lastCalled']);
Route::get('/queue/{id}', [QueueController::class, 'show']);
