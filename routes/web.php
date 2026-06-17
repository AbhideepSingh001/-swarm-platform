<?php

use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return 'Hello from Swarm Platform!';
});

Route::get('/', function () {
    return 'test-api.html';
});


use App\Http\Controllers\SwarmController;

Route::get('/test-swarm', [SwarmController::class, 'start']);

Route::get('/golu', function () {
    return 'My name is Abhideep Singh ';
});