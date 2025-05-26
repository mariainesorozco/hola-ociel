<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // DGSA, DEMS, etc.
            $table->string('name', 200);
            $table->string('short_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 50); // secretaria, dependencia, unidad_academica
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('location', 300)->nullable();
            $table->string('schedule', 150)->nullable(); // horario de atención
            $table->json('services')->nullable(); // servicios que ofrece
            $table->string('head_name', 100)->nullable(); // director/secretario
            $table->boolean('has_specialized_agent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        // Insertar departamentos iniciales de la UAN
        $this->insertInitialDepartments();
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }

    private function insertInitialDepartments(): void
    {
        $departments = [
            [
                'code' => 'DGSA',
                'name' => 'Dirección General de Servicios Académicos',
                'short_name' => 'DGSA',
                'description' => 'Encargada de los servicios académicos institucionales',
                'type' => 'dependencia',
                'contact_phone' => '311-211-8800 ext. 8530',
                'contact_email' => 'dgsa@uan.edu.mx',
                'location' => 'Edificio de Rectoría, 2do piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Servicios escolares',
                    'Control escolar',
                    'Titulación',
                    'Intercambio académico'
                ]),
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'DGS',
                'name' => 'Dirección General de Sistemas',
                'short_name' => 'Sistemas',
                'description' => 'Responsable de la infraestructura tecnológica universitaria',
                'type' => 'dependencia',
                'contact_phone' => '311-211-8800 ext. 8540',
                'contact_email' => 'sistemas@uan.edu.mx',
                'location' => 'Edificio de Sistemas',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Soporte técnico',
                    'Desarrollo de sistemas',
                    'Infraestructura de red',
                    'Correo electrónico institucional'
                ]),
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SECRETARIA_GENERAL',
                'name' => 'Secretaría General',
                'short_name' => 'Secretaría General',
                'description' => 'Órgano de apoyo a la Rectoría',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8510',
                'contact_email' => 'secretaria.general@uan.edu.mx',
                'location' => 'Edificio de Rectoría, 1er piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Trámites administrativos',
                    'Certificaciones',
                    'Apoyo a órganos colegiados'
                ]),
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'GENERAL',
                'name' => 'Información General',
                'short_name' => 'General',
                'description' => 'Información general de la Universidad',
                'type' => 'general',
                'contact_phone' => '311-211-8800',
                'contact_email' => 'info@uan.edu.mx',
                'location' => 'Ciudad de la Cultura Amado Nervo',
                'schedule' => '24/7 (Asistente Virtual)',
                'services' => json_encode([
                    'Información general',
                    'Oferta educativa',
                    'Eventos y actividades',
                    'Directorio institucional'
                ]),
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('departments')->insert($departments);
    }
};
