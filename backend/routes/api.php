<?php

use App\Http\Controllers\Api\EnhancedChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes - ¡Hola Ociel! - USANDO ENHANCED CONTROLLER
|--------------------------------------------------------------------------
*/

// Middleware de autenticación opcional para usuarios registrados
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {

    // ========================================================================
    // RUTAS PRINCIPALES (USANDO EnhancedChatController)
    // ========================================================================
    Route::middleware(['throttle:chat'])->group(function () {
        // CAMBIAR: Usar EnhancedChatController en lugar de ChatController
        Route::post('/chat', [EnhancedChatController::class, 'chat'])
             ->name('api.chat');

        Route::post('/chat/feedback', [EnhancedChatController::class, 'enhancedFeedback'])
             ->middleware('throttle:feedback')
             ->name('api.chat.feedback');
    });

    // ENDPOINTS DE INFORMACIÓN
    Route::middleware(['throttle:api'])->group(function () {
        Route::get('/departments', [EnhancedChatController::class, 'enhancedDepartments'])
             ->name('api.departments');

        Route::get('/health', [EnhancedChatController::class, 'healthAdvanced'])
             ->name('api.health');

        Route::get('/ping', function () {
            return response()->json([
                'status' => 'ok',
                'service' => 'Enhanced Hola Ociel API',
                'version' => '2.0.0',
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

    // ENDPOINTS DE BÚSQUEDA (Implementar métodos simples en EnhancedChatController)
    Route::middleware(['throttle:search'])->group(function () {
        // Implementar estos métodos básicos en EnhancedChatController
        Route::post('/search/knowledge', function(Request $request) {
            // Búsqueda simple por ahora
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Búsqueda en desarrollo'
            ]);
        })->name('api.search.knowledge');

        Route::get('/search/categories', function() {
            // Categorías básicas
            return response()->json([
                'success' => true,
                'data' => [
                    'tramites_estudiantes',
                    'servicios_academicos',
                    'oferta_educativa',
                    'directorio',
                    'informacion_general'
                ]
            ]);
        })->name('api.search.categories');

        Route::get('/search/frequent-questions', function() {
            return response()->json([
                'success' => true,
                'data' => [
                    ['question' => '¿Cómo me inscribo?', 'category' => 'tramites_estudiantes'],
                    ['question' => '¿Qué carreras hay?', 'category' => 'oferta_educativa'],
                    ['question' => '¿Dónde está la biblioteca?', 'category' => 'servicios_academicos']
                ]
            ]);
        })->name('api.search.frequent-questions');
    });

    // ========================================================================
    // RUTAS DE DEBUG - AGREGAR ESTA SECCIÓN
    // ========================================================================
    Route::prefix('debug')->group(function () {

        // Test básico - verificar que funciona
        Route::get('/test', function() {
            return response()->json([
                'status' => 'debug_routes_working',
                'message' => 'Rutas de debug funcionando correctamente',
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment()
            ]);
        });

        // Test de knowledge base
        Route::get('/knowledge-test', function(Request $request) {
            $query = $request->get('q', 'correo');

            try {
                // 1. Verificar contenido en BD
                $totalContent = DB::table('knowledge_base')->count();
                $activeContent = DB::table('knowledge_base')->where('is_active', true)->count();

                // 2. Búsqueda directa
                $directSearch = DB::table('knowledge_base')
                    ->where('is_active', true)
                    ->where(function($q) use ($query) {
                        $q->where('title', 'LIKE', "%{$query}%")
                          ->orWhere('content', 'LIKE', "%{$query}%");
                    })
                    ->limit(5)
                    ->get(['id', 'title', 'category', 'department']);

                // 3. Test KnowledgeBaseService
                $knowledgeServiceResult = null;
                try {
                    $knowledgeService = app(\App\Services\KnowledgeBaseService::class);
                    $serviceResults = $knowledgeService->searchRelevantContent($query, 'student');
                    $knowledgeServiceResult = [
                        'available' => true,
                        'healthy' => $knowledgeService->isHealthy(),
                        'results_count' => count($serviceResults),
                        'results_preview' => array_map(fn($r) => substr($r, 0, 100) . '...', array_slice($serviceResults, 0, 2))
                    ];
                } catch (Exception $e) {
                    $knowledgeServiceResult = [
                        'available' => false,
                        'error' => $e->getMessage()
                    ];
                }

                return response()->json([
                    'query' => $query,
                    'database_status' => [
                        'total_content' => $totalContent,
                        'active_content' => $activeContent,
                        'connection_ok' => true
                    ],
                    'direct_search' => [
                        'count' => $directSearch->count(),
                        'results' => $directSearch->toArray()
                    ],
                    'knowledge_service' => $knowledgeServiceResult,
                    'debug_info' => [
                        'route_working' => true,
                        'timestamp' => now()->toISOString()
                    ]
                ]);

            } catch (Exception $e) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'query' => $query,
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
        });

        // Estadísticas básicas
        Route::get('/stats', function() {
            try {
                $stats = [
                    'knowledge_base' => [
                        'total' => DB::table('knowledge_base')->count(),
                        'active' => DB::table('knowledge_base')->where('is_active', true)->count(),
                        'by_category' => [],
                        'sample_records' => []
                    ],
                    'services' => [],
                    'environment' => app()->environment(),
                    'timestamp' => now()->toISOString()
                ];

                // Obtener categorías si hay contenido
                if ($stats['knowledge_base']['active'] > 0) {
                    try {
                        $stats['knowledge_base']['by_category'] = DB::table('knowledge_base')
                            ->where('is_active', true)
                            ->groupBy('category')
                            ->selectRaw('category, COUNT(*) as count')
                            ->get()
                            ->pluck('count', 'category')
                            ->toArray();

                        $stats['knowledge_base']['sample_records'] = DB::table('knowledge_base')
                            ->where('is_active', true)
                            ->limit(3)
                            ->get(['id', 'title', 'category', 'department'])
                            ->toArray();
                    } catch (Exception $e) {
                        $stats['knowledge_base']['category_error'] = $e->getMessage();
                    }
                }

                // Verificar servicios
                try {
                    $ollamaService = app(\App\Services\OllamaService::class);
                    $stats['services']['ollama'] = $ollamaService->isHealthy();
                } catch (Exception $e) {
                    $stats['services']['ollama'] = 'error: ' . $e->getMessage();
                }

                try {
                    $knowledgeService = app(\App\Services\KnowledgeBaseService::class);
                    $stats['services']['knowledge_base'] = $knowledgeService->isHealthy();
                } catch (Exception $e) {
                    $stats['services']['knowledge_base'] = 'error: ' . $e->getMessage();
                }

                return response()->json($stats);

            } catch (Exception $e) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage()
                ], 500);
            }
        });

        // Test manual de chat completo
        Route::post('/chat-test', function(Request $request) {
            $message = $request->input('message', '¿Cómo activar mi correo?');
            $userType = $request->input('user_type', 'student');

            try {
                // Test directo del EnhancedChatController
                $controller = app(\App\Http\Controllers\Api\EnhancedChatController::class);

                // Crear request simulado
                $testRequest = new Request();
                $testRequest->merge([
                    'message' => $message,
                    'user_type' => $userType
                ]);

                // Intentar llamar al método chat
                $response = $controller->chat($testRequest);

                return response()->json([
                    'test_message' => $message,
                    'user_type' => $userType,
                    'controller_response' => $response->getData(),
                    'test_successful' => true
                ]);

            } catch (Exception $e) {
                return response()->json([
                    'test_message' => $message,
                    'user_type' => $userType,
                    'error' => $e->getMessage(),
                    'test_successful' => false,
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
        });
    });
});

// ========================================================================
// RUTAS V2 (Ya existentes con Enhanced)
// ========================================================================
Route::prefix('v2')->group(function () {
    Route::middleware(['throttle:enhanced_chat'])->group(function () {
        Route::post('/chat', [EnhancedChatController::class, 'chat'])
             ->name('api.v2.chat');

        Route::post('/chat/feedback', [EnhancedChatController::class, 'enhancedFeedback'])
             ->middleware('throttle:feedback')
             ->name('api.v2.chat.feedback');
    });

    Route::middleware(['throttle:api'])->group(function () {
        Route::get('/departments', [EnhancedChatController::class, 'enhancedDepartments'])
             ->name('api.v2.departments');

        Route::get('/health', [EnhancedChatController::class, 'healthAdvanced'])
             ->name('api.v2.health');

        Route::get('/performance-metrics', [EnhancedChatController::class, 'performanceMetrics'])
             ->name('api.v2.performance');
    });
});

// Health check público
Route::get('/health', [EnhancedChatController::class, 'healthAdvanced'])->name('api.health.public');

// ========================================================================
// RUTA SIMPLE DE TEST (fuera de cualquier grupo)
// ========================================================================
Route::get('/simple-test', function() {
    return response()->json([
        'message' => 'API funcionando correctamente',
        'timestamp' => now()->toISOString(),
        'knowledge_base_count' => DB::table('knowledge_base')->count(),
        'environment' => app()->environment()
    ]);
});
