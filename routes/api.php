<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SettingsController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    Route::put('/queue/{id}/call', [QueueController::class, 'callQueue']);
    Route::put('/queue/{id}/serve', [QueueController::class, 'serveQueue']);
    Route::put('/queue/{id}/skip', [QueueController::class, 'skipQueue']);
    Route::put('/queue/{id}/complete', [QueueController::class, 'completeQueue']);
    Route::put('/queue/{id}/restore', [QueueController::class, 'restore']);
    Route::get('/queue/trash', [QueueController::class, 'getTrash']);
    Route::delete('/queue/trash', [QueueController::class, 'emptyTrash']);
    Route::post('/queue/clear-history', [QueueController::class, 'clearHistory']);

    Route::get('/dashboard/analitik', [DashboardController::class, 'analitik']);
    Route::get('/dashboard/export', [DashboardController::class, 'export']);

    Route::post('/settings/video-volume', [SettingsController::class, 'setVideoVolume']);

    Route::post('/settings/videos', [SettingsController::class, 'uploadVideo']);
    Route::delete('/settings/videos/{filename}', [SettingsController::class, 'deleteVideo']);
});

// Publik (tanpa auth)
Route::get('/settings/video-volume', [SettingsController::class, 'getVideoVolume']);
Route::get('/settings/videos', [SettingsController::class, 'getVideos']);

Route::get('/queue', [QueueController::class, 'index']);
Route::post('/queue/take', [QueueController::class, 'takeTicket']);
Route::get('/queue/stats', [QueueController::class, 'stats']);
Route::get('/queue/last-called/{counterNumber}', [QueueController::class, 'lastCalled']);
Route::get('/queue/{id}', [QueueController::class, 'show']);
