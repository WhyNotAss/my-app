<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::get('/tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus'])
    ->whereNumber('id')
    ->middleware('auth:sanctum');