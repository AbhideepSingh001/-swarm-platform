<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwarmController;
use App\Http\Controllers\TestController;   
use App\Http\Controllers\PlanController;
use App\Http\Controllers\Api\AgentMessageController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/swarm/start', [SwarmController::class, 'start']);
Route::get('/test/hello', [TestController::class, 'hello']);

// Day 10: Planner Agent Routes
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
}); // <-- MUST CLOSE THE GROUP HERE

// Day 12: Message Bus Routes - MUST BE OUTSIDE the plans group
Route::prefix('messages')->group(function () {
    Route::post('/publish', [AgentMessageController::class, 'publish']);
    Route::post('/direct', [AgentMessageController::class, 'sendDirect']);
    Route::get('/agent/{agentId}/pull', [AgentMessageController::class, 'pull']);
    Route::patch('/{messageId}/read', [AgentMessageController::class, 'markAsRead']);
    Route::post('/agent/{agentId}/subscribe', [AgentMessageController::class, 'subscribe']);
    Route::post('/agent/{agentId}/unsubscribe', [AgentMessageController::class, 'unsubscribe']);
    Route::get('/agent/{agentId}/history', [AgentMessageController::class, 'history']);
});