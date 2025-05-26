<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uan_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('category', 50); // ai_settings, contact_info, academic_calendar, etc.
            $table->text('description')->nullable();
            $table->enum('type', ['string', 'json', 'boolean', 'integer', 'float'])->default('string');
            $table->boolean('is_public')->default(false); // si se puede mostrar a usuarios
            $table->timestamps();

            $table->index('category');
            $table->index('is_public');
        });

        // Insertar configuraciones iniciales de la UAN
        $this->insertInitialConfigurations();
    }

    public function down(): void
    {
        Schema::dropIfExists('uan_configurations');
    }

    private function insertInitialConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'ai_model_primary',
                'value' => 'mistral:7b',
                'category' => 'ai_settings',
                'description' => 'Modelo de IA principal para respuestas',
                'type' => 'string',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'uan_contact_phone',
                'value' => '311-211-8800',
                'category' => 'contact_info',
                'description' => 'Teléfono principal de la UAN',
                'type' => 'string',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'uan_address',
                'value' => 'Ciudad de la Cultura "Amado Nervo", Tepic, Nayarit, México',
                'category' => 'contact_info',
                'description' => 'Dirección principal de la UAN',
                'type' => 'string',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'uan_website',
                'value' => 'https://www.uan.edu.mx',
                'category' => 'contact_info',
                'description' => 'Sitio web oficial de la UAN',
                'type' => 'string',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'chat_max_tokens',
                'value' => '1000',
                'category' => 'ai_settings',
                'description' => 'Máximo de tokens para respuestas de IA',
                'type' => 'integer',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'ociel_welcome_message',
                'value' => '¡Hola! Soy Ociel, tu asistente virtual de la Universidad Autónoma de Nayarit. ¿En qué puedo ayudarte hoy? 🎓',
                'category' => 'chat_settings',
                'description' => 'Mensaje de bienvenida de Ociel',
                'type' => 'string',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('uan_configurations')->insert($configurations);
    }
};
