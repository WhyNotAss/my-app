<?php

use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus'])->whereNumber('id');