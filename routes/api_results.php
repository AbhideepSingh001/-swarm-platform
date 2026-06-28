<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('results')->group(function () {
    Route::get('/', [ResultController::class, 'index']);
    Route::get('/{id}', [ResultController::class, 'show']);
    Route::get('/task/{taskId}', [ResultController::class, 'byTask']);
    Route::get('/{id}/logs', [ResultController::class, 'logs']);
    Route::get('/{id}/artifacts', [ResultController::class, 'artifacts']);
});

Route::prefix('analytics')->group(function () {
    Route::get('/summary', [AnalyticsController::class, 'summary']);
    Route::get('/status-distribution', [AnalyticsController::class, 'statusDistribution']);
    Route::get('/daily-trends', [AnalyticsController::class, 'dailyTrends']);
    Route::get('/task/{taskId}', [AnalyticsController::class, 'task']);
    Route::get('/driver/{driver}', [AnalyticsController::class, 'driver']);
});