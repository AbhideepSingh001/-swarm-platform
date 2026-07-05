<?php

use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::apiResource('workflows', WorkflowController::class)->only(['index', 'store', 'show']);
Route::post('workflows/{id}/execute', [WorkflowController::class, 'execute'])->name('workflows.execute');

Route::prefix('workflows/executions')->group(function () {
    Route::get('{executionId}', [WorkflowController::class, 'executionStatus'])->name('workflows.executions.status');
    Route::post('{executionId}/pause', [WorkflowController::class, 'pause'])->name('workflows.executions.pause');
    Route::post('{executionId}/resume', [WorkflowController::class, 'resume'])->name('workflows.executions.resume');
    Route::post('{executionId}/cancel', [WorkflowController::class, 'cancel'])->name('workflows.executions.cancel');
    Route::get('{executionId}/results', [WorkflowController::class, 'results'])->name('workflows.executions.results');
});