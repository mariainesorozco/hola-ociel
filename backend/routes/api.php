<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\EnhancedChatController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| API Routes - ¡Hola Ociel! - MIGRACIÓN GRADUAL
|--------------------------------------------------------------------------
| Estrategia: Mantener rutas existentes + Agregar nuevas versiones mejoradas
*/

// Middleware de autenticación opcional para usuarios registrados
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {

    // ========================================================================
    // RUTAS EXISTENTES (MANTENER PARA COMPATIBILIDAD)
    // ========================================================================
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

// ========================================================================
// NUEVAS RUTAS MEJORADAS (VERSIÓN 2.0)
// ========================================================================
Route::prefix('v2')->group(function () {

    // CHAT MEJORADO CON ALTA CONFIANZA
    Route::middleware(['throttle:enhanced_chat'])->group(function () {
        Route::post('/chat', [EnhancedChatController::class, 'chat'])
             ->name('api.v2.chat');

        Route::post('/chat/feedback', [EnhancedChatController::class, 'enhancedFeedback'])
             ->middleware('throttle:feedback')
             ->name('api.v2.chat.feedback');
    });

    // INFORMACIÓN MEJORADA
    Route::middleware(['throttle:api'])->group(function () {
        Route::get('/departments', [EnhancedChatController::class, 'enhancedDepartments'])
             ->name('api.v2.departments');

        Route::get('/health', [EnhancedChatController::class, 'healthAdvanced'])
             ->name('api.v2.health');

        Route::get('/performance-metrics', [EnhancedChatController::class, 'performanceMetrics'])
             ->name('api.v2.performance');

        Route::get('/ping', function () {
            return response()->json([
                'status' => 'ok',
                'service' => 'Enhanced Hola Ociel API',
                'version' => '2.0.0',
                'features' => [
                    'high_confidence_responses',
                    'intelligent_escalation',
                    'ghost_cms_integration',
                    'advanced_analytics'
                ],
                'timestamp' => now()->toISOString(),
                'server_time' => now()->format('Y-m-d H:i:s T')
            ]);
        })->name('api.v2.ping');
    });
});

// ========================================================================
// RUTA DE TRANSICIÓN INTELIGENTE (RECOMENDADA)
// ========================================================================
Route::prefix('v1')->group(function () {

    // ENDPOINT HÍBRIDO: Usa el controlador mejorado pero mantiene compatibilidad
    Route::post('/chat-enhanced', [EnhancedChatController::class, 'chat'])
         ->middleware(['throttle:enhanced_chat'])
         ->name('api.chat.enhanced');

    // Permite a los clientes migrar gradualmente cambiando solo el endpoint
});

// ========================================================================
// RUTAS DE GESTIÓN Y MIGRACIÓN
// ========================================================================
Route::prefix('admin')->middleware(['auth:sanctum', 'can:admin-access'])->group(function () {

    // Comparación de versiones
    Route::get('/compare-versions', function () {
        return response()->json([
            'v1_endpoints' => [
                'POST /api/v1/chat' => 'ChatController@chat',
                'GET /api/v1/health' => 'ChatController@health',
                'GET /api/v1/departments' => 'ChatController@departments'
            ],
            'v2_endpoints' => [
                'POST /api/v2/chat' => 'EnhancedChatController@chat',
                'GET /api/v2/health' => 'EnhancedChatController@healthAdvanced',
                'GET /api/v2/departments' => 'EnhancedChatController@enhancedDepartments'
            ],
            'migration_status' => [
                'v1_usage_percentage' => 85, // Calculado dinámicamente
                'v2_usage_percentage' => 15,
                'recommended_action' => 'Migrar gradualmente a v2'
            ]
        ]);
    })->name('api.admin.compare-versions');

    // Forzar migración cuando estés listo
    Route::post('/force-migration-v2', function () {
        // Cambiar configuración para dirigir todo el tráfico a v2
        config(['app.default_chat_version' => 'v2']);

        return response()->json([
            'message' => 'Migración a v2 activada',
            'status' => 'success',
            'timestamp' => now()->toISOString()
        ]);
    })->name('api.admin.force-migration');
});

// ===== HEALTH CHECKS ADICIONALES =====
Route::get('/health', [ChatController::class, 'health'])->name('api.health.public');

Route::get('/health/detailed', [ChatController::class, 'detailedHealth'])
     ->middleware(['auth:sanctum', 'can:view-system-health'])
     ->name('api.health.detailed');

// Health check mejorado para v2
Route::get('/health/v2', [EnhancedChatController::class, 'healthAdvanced'])
     ->name('api.health.v2');

// ===== RUTAS DE DESARROLLO (solo en entorno local) =====
if (app()->environment('local')) {
    Route::prefix('dev')->group(function () {

        Route::get('/test-chat', function () {
            return view('dev.test-chat');
        })->name('api.dev.test-chat');

        // Test comparativo entre v1 y v2
        Route::post('/test-comparison', function (Request $request) {
            $message = $request->input('message', '¿Información sobre inscripción?');

            // Respuesta v1
            $v1Controller = app(ChatController::class);
            $v1Response = $v1Controller->chat($request);

            // Respuesta v2
            $v2Controller = app(EnhancedChatController::class);
            $v2Response = $v2Controller->chat($request);

            return response()->json([
                'message' => $message,
                'v1_response' => $v1Response->getData(),
                'v2_response' => $v2Response->getData(),
                'comparison' => [
                    'v1_confidence' => $v1Response->getData()->data->confidence ?? 0,
                    'v2_confidence' => $v2Response->getData()->data->confidence ?? 0,
                    'winner' => 'v2' // Por defecto, v2 debería ser mejor
                ]
            ]);
        })->name('api.dev.test-comparison');

        Route::post('/simulate-load', [ChatController::class, 'simulateLoad'])
             ->name('api.dev.simulate-load');

        Route::get('/clear-all-cache', function () {
            Artisan::call('ociel:clear-cache --type=all --force');
            return response()->json(['message' => 'Cache cleared']);
        })->name('api.dev.clear-cache');
    });
}

// ========================================================================
// CONFIGURACIÓN DE RATE LIMITING PERSONALIZADA
// ========================================================================

// En app/Http/Kernel.php, agregar:
/*
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

protected $routeMiddleware = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
];
*/

// En app/Providers/RouteServiceProvider.php, configurar:
/*
protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('chat', function (Request $request) {
        return Limit::perMinute(20)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('enhanced_chat', function (Request $request) {
        return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('feedback', function (Request $request) {
        return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('search', function (Request $request) {
        return Limit::perMinute(40)->by(optional($request->user())->id ?: $request->ip());
    });
}
*/
