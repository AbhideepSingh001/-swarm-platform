<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwarmController;
use App\Http\Controllers\TestController;       
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