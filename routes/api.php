<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwarmController;
use App\Http\Controllers\TestController;   
use App\Http\Controllers\PlanController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Your swarm route - OUTSIDE the auth middleware
Route::post('/swarm/start', [SwarmController::class, 'start']);


Route::get('/test/hello', [TestController::class, 'hello']);



/*
|--------------------------------------------------------------------------
| Day 10: Planner Agent Routes
|--------------------------------------------------------------------------
*/

Route::prefix('plans')->group(function () {
    Route::get('/', [PlanController::class, 'index']);
    Route::post('/', [PlanController::class, 'store']);
    Route::get('/{id}', [PlanController::class, 'show']);
    Route::get('/{id}/status', [PlanController::class, 'status']);
    Route::post('/{id}/execute', [PlanController::class, 'execute']);
    Route::post('/{id}/pause', [PlanController::class, 'pause']);
    Route::post('/{id}/resume', [PlanController::class, 'resume']);
    Route::post('/{id}/cancel', [PlanController::class, 'cancel']);
    Route::post('/{id}/regenerate', [PlanController::class, 'regenerate']);
    Route::get('/system/key-status', [PlanController::class, 'keyStatus']);
});