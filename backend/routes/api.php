<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/chat/feedback', [ChatController::class, 'feedback']);
    Route::get('/departments', [ChatController::class, 'departments']);
    Route::get('/health', [ChatController::class, 'health']);
    Route::get('/ping', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'Hola Ociel API',
            'timestamp' => now()->toISOString()
        ]);
    });
});

Route::get('/health', [ChatController::class, 'health']);
