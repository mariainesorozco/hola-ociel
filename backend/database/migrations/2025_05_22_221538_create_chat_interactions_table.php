<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->nullable()->index();
            $table->enum('user_type', ['student', 'employee', 'public'])->default('public');
            $table->string('department', 100)->nullable();
            $table->string('user_identification', 100)->nullable(); // Matrícula o número empleado
            $table->text('message');
            $table->text('response');
            $table->json('context_used')->nullable(); // Contexto de la base de conocimientos utilizado
            $table->float('confidence', 3, 2)->default(0.0);
            $table->string('model_used', 50)->nullable();
            $table->integer('response_time')->nullable(); // en milisegundos
            $table->string('ip_address', 45)->nullable(); // IPv4 e IPv6
            $table->string('channel', 20)->default('web'); // web, whatsapp, telegram, etc.
            $table->boolean('was_helpful')->nullable(); // feedback del usuario
            $table->text('feedback_comment')->nullable();
            $table->boolean('requires_human_follow_up')->default(false);
            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['user_type', 'created_at']);
            $table->index(['department', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index('requires_human_follow_up');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_interactions');
    }
};
