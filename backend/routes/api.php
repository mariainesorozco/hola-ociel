<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // Rutas principales de chat
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/chat/feedback', [ChatController::class, 'feedback']);

    // Información de apoyo
    Route::get('/departments', [ChatController::class, 'departments']);
    Route::get('/health', [ChatController::class, 'health']);

    // Health check simple
    Route::get('/ping', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'Hola Ociel API',
            'timestamp' => now()->toISOString()
        ]);
    });
});

// Ruta de health check en la raíz de la API
Route::get('/health', [ChatController::class, 'health']);
