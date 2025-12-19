<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WatermarkController;
use App\Http\Controllers\WatermarkController2;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Proteksi dengan API Token Middleware
Route::middleware('api.token')->group(function () {
    Route::post('/watermark', [WatermarkController::class, 'process']);
    Route::post('/watermark/slide2', [WatermarkController2::class, 'process']);
});