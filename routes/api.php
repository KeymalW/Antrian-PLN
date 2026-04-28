<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AntrianController;
use App\Http\Controllers\DashboardController;
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
Route::post('/antrian', [AntrianController::class, 'store']);
Route::get('/antrian', [AntrianController::class, 'index']);
Route::put('/antrian/{id}/panggil', [AntrianController::class, 'panggil']);
Route::get('/antrian/aktif', [AntrianController::class, 'aktif']);
Route::put('/antrian/{id}/lewati', [AntrianController::class, 'lewati']);
Route::get('/antrian/statistik', [AntrianController::class, 'statistik']);
Route::get('/dashboard/analitik', [DashboardController::class, 'analitik']);

