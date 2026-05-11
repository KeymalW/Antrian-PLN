<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AntrianController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/login', [AuthController::class, 'login']);

// Rute Publik (Bisa diakses pelanggan tanpa login)
Route::post('/antrian', [AntrianController::class, 'store']);
Route::get('/antrian', [AntrianController::class, 'index']);
Route::get('/antrian/aktif', [AntrianController::class, 'aktif']);
Route::get('/antrian/statistik', [AntrianController::class, 'statistik']);
Route::get('/antrian/{id}/status', [AntrianController::class, 'status']);

// Rute Terlindungi (Khusus Admin/Petugas yang udah login)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/antrian/{id}/panggil', [AntrianController::class, 'panggil']);
    Route::put('/antrian/{id}/lewati', [AntrianController::class, 'lewati']);
    Route::get('/dashboard/analitik', [DashboardController::class, 'analitik']);
    Route::get('/dashboard/export', [DashboardController::class, 'export']);
});




