<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->string('category', 100); // tramites, servicios, oferta_educativa, etc.
            $table->string('department', 100)->nullable();
            $table->json('user_types'); // ['student', 'employee', 'public']
            $table->json('keywords')->nullable(); // palabras clave para búsqueda
            $table->string('source_url', 500)->nullable();
            $table->string('contact_info', 200)->nullable(); // teléfono, email, extensión
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('created_by', 100)->nullable(); // quien agregó la información
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            // Índices para búsquedas eficientes
            $table->index(['category', 'is_active']);
            $table->index(['department', 'is_active']);
            $table->index('priority');

            // Full text search para MariaDB
            $table->fullText(['title', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
