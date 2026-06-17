<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
      

class TestController extends Controller
{
public function hello() {
    return response()->json([
        'message' => 'Hello from TestController!',
        'time' => now()->toDateTimeString()
    ]);
}}
