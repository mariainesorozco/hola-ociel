<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| API Routes - ¡Hola Ociel!
|--------------------------------------------------------------------------
| Rutas para el sistema de chat inteligente de la UAN
*/

// Middleware de autenticación opcional para usuarios registrados
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    // ENDPOINTS PRINCIPALES DE CHAT
    Route::middleware(['throttle:chat'])->group(function () {
        Route::post('/chat', [ChatController::class, 'chat'])
             ->name('api.chat');

        Route::post('/chat/feedback', [ChatController::class, 'feedback'])
             ->middleware('throttle:feedback')
             ->name('api.chat.feedback');
    });
    // ENDPOINTS DE INFORMACIÓN (sin rate limiting estricto)
    Route::middleware(['throttle:api'])->group(function () {
        Route::get('/departments', [ChatController::class, 'departments'])
             ->name('api.departments');

        Route::get('/health', [ChatController::class, 'health'])
             ->name('api.health');

        Route::get('/ping', function () {
            return response()->json([
                'status' => 'ok',
                'service' => 'Hola Ociel API',
                'version' => '1.2.0',
                'timestamp' => now()->toISOString(),
                'server_time' => now()->format('Y-m-d H:i:s T')
            ]);
        })->name('api.ping');

         // Información pública de la universidad
         Route::get('/university-info', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'name' => 'Universidad Autónoma de Nayarit',
                    'acronym' => 'UAN',
                    'founded' => '1969-04-25',
                    'location' => 'Tepic, Nayarit, México',
                    'address' => 'Ciudad de la Cultura "Amado Nervo"',
                    'phone' => '311-211-8800',
                    'website' => 'https://www.uan.edu.mx',
                    'email' => 'contacto@uan.edu.mx'
                ]
            ]);
        })->name('api.university-info');
    });

    // ENDPOINTS DE BÚSQUEDA AVANZADA
    Route::middleware(['throttle:search'])->group(function () {
        Route::post('/search/knowledge', [ChatController::class, 'searchKnowledge'])
             ->name('api.search.knowledge');

        Route::get('/search/categories', [ChatController::class, 'getCategories'])
             ->name('api.search.categories');

        Route::get('/search/frequent-questions', [ChatController::class, 'getFrequentQuestions'])
             ->name('api.search.frequent-questions');
    });
});

// ===== HEALTH CHECKS ADICIONALES =====
Route::get('/health', [ChatController::class, 'health'])->name('api.health.public');

Route::get('/health/detailed', [ChatController::class, 'detailedHealth'])
     ->middleware(['auth:sanctum', 'can:view-system-health'])
     ->name('api.health.detailed');

// ===== RUTAS DE DESARROLLO (solo en entorno local) =====
if (app()->environment('local')) {
    Route::prefix('dev')->group(function () {

        Route::get('/test-chat', function () {
            return view('dev.test-chat');
        })->name('api.dev.test-chat');

        Route::post('/simulate-load', [ChatController::class, 'simulateLoad'])
             ->name('api.dev.simulate-load');

        Route::get('/clear-all-cache', function () {
            Artisan::call('ociel:clear-cache --type=all --force');
            return response()->json(['message' => 'Cache cleared']);
        })->name('api.dev.clear-cache');
    });
}
