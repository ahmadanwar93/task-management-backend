<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('workspaces')->group(function () {
        Route::post('/', [WorkspaceController::class, 'store']);
        Route::get('/', [WorkspaceController::class, 'index']);
        Route::get('/{slug}', [WorkspaceController::class, 'show']);
        Route::patch('/{slug}/settings', [WorkspaceController::class, 'update']);
        Route::delete('/{slug}', [WorkspaceController::class, 'destroy']);
        Route::post('/{slug}/invite', [WorkspaceController::class, 'inviteMember']);
        Route::delete('/{slug}/members/{userId}', [WorkspaceController::class, 'removeMember']);

        Route::get('/{slug}/sprints', [SprintController::class, 'index']);
        Route::post('/{slug}/sprints', [SprintController::class, 'store']); // new API
        Route::get('/{slug}/sprints/{sprintId}', [SprintController::class, 'show']);
        Route::patch('/{slug}/sprints/{sprintId}/complete', [SprintController::class, 'complete']);
        Route::patch('/{slug}/sprints/{sprintId}', [SprintController::class, 'edit']);

        Route::get('/{slug}/tasks', [TaskController::class, 'index']);
        Route::post('/{slug}/tasks', [TaskController::class, 'store']);
        Route::get('/{slug}/tasks/{taskId}', [TaskController::class, 'show']);
        Route::patch('/{slug}/tasks/{taskId}', [TaskController::class, 'update']);
        Route::delete('/{slug}/tasks/{taskId}', [TaskController::class, 'destroy']);
        Route::patch('/{slug}/tasks/{taskId}/move', [TaskController::class, 'move']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
});
