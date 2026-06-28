<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwarmController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\Api\AgentMessageController;
use App\Http\Controllers\AgentPresenceController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ResultController;

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
});

// Day 12: Message Bus Routes
Route::prefix('messages')->group(function () {
    Route::post('/publish', [AgentMessageController::class, 'publish']);
    Route::post('/direct', [AgentMessageController::class, 'sendDirect']);
    Route::get('/agent/{agentId}/pull', [AgentMessageController::class, 'pull']);
    Route::patch('/{messageId}/read', [AgentMessageController::class, 'markAsRead']);
    Route::post('/agent/{agentId}/subscribe', [AgentMessageController::class, 'subscribe']);
    Route::post('/agent/{agentId}/unsubscribe', [AgentMessageController::class, 'unsubscribe']);
    Route::get('/agent/{agentId}/history', [AgentMessageController::class, 'history']);
});

// Day 13: Agent Presence Routes
Route::prefix('presence')->group(function () {
    Route::post('/agent/{agentId}/online', [AgentPresenceController::class, 'online']);
    Route::post('/agent/{agentId}/offline', [AgentPresenceController::class, 'offline']);
    Route::post('/agent/{agentId}/heartbeat', [AgentPresenceController::class, 'heartbeat']);
    Route::get('/agent/{agentId}/status', [AgentPresenceController::class, 'status']);
    Route::get('/online', [AgentPresenceController::class, 'allOnline']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tasks', TaskController::class);

    Route::prefix('tasks')->group(function () {
        Route::post('{task}/assign', [TaskController::class, 'assign']);
        Route::post('{task}/accept', [TaskController::class, 'accept']);
        Route::post('{task}/progress', [TaskController::class, 'progress']);
        Route::post('{task}/complete', [TaskController::class, 'complete']);
        Route::post('{task}/fail', [TaskController::class, 'fail']);
        Route::post('{task}/dependencies', [TaskController::class, 'addDependency']);
        Route::post('{task}/comments', [TaskController::class, 'addComment']);
    });

    Route::post('workflows', [TaskController::class, 'createWorkflow']);
    Route::get('tasks/stats/overview', [TaskController::class, 'stats']);
});

// Results Routes
Route::prefix('results')->group(function () {
    Route::get('/', [ResultController::class, 'index']);
    Route::post('/', [ResultController::class, 'store']);
    Route::get('/task/{task}', [ResultController::class, 'byTask']);
    Route::get('/{id}', [ResultController::class, 'show']);
    Route::put('/{id}', [ResultController::class, 'update']);
    Route::delete('/{id}', [ResultController::class, 'destroy']);
    Route::get('/{id}/logs', [ResultController::class, 'logs']);
    Route::get('/{id}/artifacts', [ResultController::class, 'artifacts']);
    Route::post('/{id}/artifacts', [ResultController::class, 'storeArtifact']);
    Route::get('/{resultId}/artifacts/{artifactId}/download', [ResultController::class, 'downloadArtifact']);
});

// Analytics Routes
Route::prefix('analytics')->group(function () {
    Route::get('/task/{task}', [AnalyticsController::class, 'task']);
    Route::get('/driver/{driver}', [AnalyticsController::class, 'driver']);
    Route::get('/tasks', [AnalyticsController::class, 'taskMetrics']);
    Route::get('/agents', [AnalyticsController::class, 'agentMetrics']);
    Route::get('/workflows/{workflowExecutionId}', [AnalyticsController::class, 'workflowMetrics']);
    Route::get('/workflows/{workflowId}/trend', [AnalyticsController::class, 'workflowTrend']);
    Route::get('/drivers', [AnalyticsController::class, 'driverMetrics']);
    Route::post('/drivers/compare', [AnalyticsController::class, 'compareDrivers']);
    Route::get('/time-series', [AnalyticsController::class, 'timeSeries']);
    Route::get('/dashboard', [AnalyticsController::class, 'dashboardSummary']);
    Route::get('/summary', [AnalyticsController::class, 'summary']);
    Route::get('/status-distribution', [AnalyticsController::class, 'statusDistribution']);
    Route::get('/daily-trends', [AnalyticsController::class, 'dailyTrends']);
});