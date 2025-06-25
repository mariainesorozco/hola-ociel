<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Ruta para el widget de ¡Hola Ociel! - CORREGIDA
Route::get('/ociel', function () {
    return response()->file(public_path('ociel/index.php'));
});

// Ruta alternativa para servir el widget directamente
Route::get('/widget', function () {
    return response()->file(public_path('ociel/index.php'));
});

// Health check para verificar que Laravel funciona
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Hola Ociel Web',
        'timestamp' => now()->toISOString()
    ]);
});
