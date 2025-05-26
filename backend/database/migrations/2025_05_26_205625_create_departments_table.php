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
            $table->string('code', 30)->unique(); // DGSA, DEMS, etc.
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
            $table->string('head_title', 100)->nullable(); // NUEVA COLUMNA AGREGADA
            $table->boolean('has_specialized_agent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        // Insertar departamentos iniciales de la UAN
        $this->insertUANDepartments();
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }

    private function insertUANDepartments(): void
    {
        $departments = [
            [
                'code' => 'RECTORIA',
                'name' => 'Rectoría',
                'short_name' => 'Rectoría',
                'description' => 'Máxima autoridad ejecutiva de la Universidad Autónoma de Nayarit',
                'type' => 'rectoria',
                'contact_phone' => '311-211-8800 ext. 8500',
                'contact_email' => 'rectoria@uan.edu.mx',
                'location' => 'Torre de Rectoría, Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Dirección general de la universidad',
                    'Coordinación institucional',
                    'Relaciones interinstitucionales'
                ]),
                'head_name' => 'Dra. Norma Liliana Galván Meza',
                'head_title' => 'Rectora',
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SGRAL',
                'name' => 'Secretaría General',
                'short_name' => 'Secretaría General',
                'description' => 'Órgano de apoyo a la Rectoría en funciones de gobierno universitario',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8510',
                'contact_email' => 'secretaria.general@uan.edu.mx',
                'location' => 'Torre de Rectoría, 1er piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Normatividad universitaria',
                    'Fomento editorial',
                    'Comunicación institucional',
                    'Seguridad universitaria'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SACAD',
                'name' => 'Secretaría Académica',
                'short_name' => 'Secretaría Académica',
                'description' => 'Encargada de la consolidación del área académica y programas educativos',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8520',
                'contact_email' => 'secretaria.academica@uan.edu.mx',
                'location' => 'Torre de Rectoría, 2do piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Desarrollo del profesorado',
                    'Programas académicos',
                    'Administración escolar',
                    'Desarrollo estudiantil',
                    'Educación virtual'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SPPI',
                'name' => 'Secretaría de Planeación, Programación e Infraestructura',
                'short_name' => 'SPPI',
                'description' => 'Responsable de la planeación integral y desarrollo de infraestructura universitaria',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8530',
                'contact_email' => 'planeacion@uan.edu.mx',
                'location' => 'Torre de Rectoría, 2do piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Planeación institucional',
                    'Proyectos estratégicos',
                    'Obra universitaria',
                    'Servicios generales',
                    'Infraestructura tecnológica'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SEMS',
                'name' => 'Secretaría de Educación Media Superior',
                'short_name' => 'SEMS',
                'description' => 'Coordinación del subsistema de educación media superior de la UAN',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8540',
                'contact_email' => 'sems@uan.edu.mx',
                'location' => 'Edificio de Educación Media Superior',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Fortalecimiento al bachillerato',
                    'Planeación organizacional',
                    'Coordinación de preparatorias'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SIP',
                'name' => 'Secretaría de Investigación y Posgrado',
                'short_name' => 'SIP',
                'description' => 'Fomento y coordinación de la investigación científica y programas de posgrado',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8550',
                'contact_email' => 'investigacion@uan.edu.mx',
                'location' => 'Torre de Rectoría, 3er piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Dirección de posgrado',
                    'Vinculación de la investigación',
                    'Fortalecimiento a la investigación'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SADM',
                'name' => 'Secretaría de Administración',
                'short_name' => 'Secretaría de Administración',
                'description' => 'Administración de recursos humanos, materiales y servicios universitarios',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8560',
                'contact_email' => 'administracion@uan.edu.mx',
                'location' => 'Edificio Administrativo',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Recursos humanos',
                    'Patrimonio universitario',
                    'Compras y adquisiciones'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SFIN',
                'name' => 'Secretaría de Finanzas',
                'short_name' => 'Secretaría de Finanzas',
                'description' => 'Administración y control de recursos financieros de la universidad',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8570',
                'contact_email' => 'finanzas@uan.edu.mx',
                'location' => 'Edificio Administrativo',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Planeación financiera',
                    'Contabilidad',
                    'Ingresos y egresos',
                    'Entidades productivas'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'SEXT',
                'name' => 'Secretaría de Extensión y Vinculación',
                'short_name' => 'Extensión y Vinculación',
                'description' => 'Vinculación universitaria con los sectores productivo y social',
                'type' => 'secretaria',
                'contact_phone' => '311-211-8800 ext. 8580',
                'contact_email' => 'extension@uan.edu.mx',
                'location' => 'Torre de Rectoría, 3er piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Vinculación cultural y artística',
                    'Responsabilidad social',
                    'Vinculación estratégica',
                    'Vinculación profesional'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'DGS',
                'name' => 'Dirección General de Sistemas',
                'short_name' => 'Sistemas',
                'description' => 'Responsable de los sistemas de información y tecnología',
                'type' => 'dependencia',
                'contact_phone' => '311-211-8800 ext. 8540',
                'contact_email' => 'dgs@uan.edu.mx',
                'location' => 'Edificio de Sistemas',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Desarrollo de sistemas',
                    'Infraestructura tecnológica',
                    'Plataformas educativas',
                    'Soporte técnico'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'DGAE',
                'name' => 'Dirección General de Administración Escolar',
                'short_name' => 'DGAE',
                'description' => 'Servicios académicos y control escolar institucional',
                'type' => 'dependencia',
                'contact_phone' => '311-211-8800 ext. 8530',
                'contact_email' => 'dgae@uan.edu.mx',
                'location' => 'Edificio de Rectoría, 2do piso',
                'schedule' => 'Lunes a Viernes de 8:00 a 15:00 hrs',
                'services' => json_encode([
                    'Control escolar',
                    'Servicios escolares',
                    'Titulación',
                    'Intercambio académico',
                    'Certificación de estudios'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UACBI',
                'name' => 'Unidad Académica de Ciencias Básicas e Ingenierías',
                'short_name' => 'UACBI',
                'description' => 'Unidad académica del área de ciencias exactas e ingenierías',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8600',
                'contact_email' => 'direccion@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Licenciaturas en ingeniería',
                    'Programas de ciencias básicas',
                    'Laboratorios especializados',
                    'Investigación científica'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UACS',
                'name' => 'Unidad Académica de Ciencias Sociales',
                'short_name' => 'UACS',
                'description' => 'Unidad académica del área de ciencias sociales',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8610',
                'contact_email' => 'direccion.cs@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Licenciatura en Derecho',
                    'Licenciatura en Ciencias Políticas',
                    'Licenciatura en Comunicación',
                    'Programas de posgrado'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UAEH',
                'name' => 'Unidad Académica de Educación y Humanidades',
                'short_name' => 'UAEH',
                'description' => 'Unidad académica especializada en educación y humanidades',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8620',
                'contact_email' => 'direccion.eh@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Licenciaturas en educación',
                    'Programas de humanidades',
                    'Formación docente',
                    'Investigación educativa'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UAM',
                'name' => 'Unidad Académica de Medicina',
                'short_name' => 'Medicina',
                'description' => 'Unidad académica especializada en ciencias médicas',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8630',
                'contact_email' => 'direccion.medicina@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Licenciatura en Medicina',
                    'Programas de especialidad',
                    'Investigación médica',
                    'Clínicas universitarias'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UAEO',
                'name' => 'Unidad Académica de Enfermería y Odontología',
                'short_name' => 'Enfermería y Odontología',
                'description' => 'Unidad académica de ciencias de la salud',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8640',
                'contact_email' => 'direccion.eo@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Licenciatura en Enfermería',
                    'Licenciatura en Odontología',
                    'Clínicas universitarias',
                    'Programas de servicio social'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UACBAP',
                'name' => 'Unidad Académica de Ciencias Biológico Agropecuarias y Pesqueras',
                'short_name' => 'UACBAP',
                'description' => 'Unidad académica especializada en ciencias agropecuarias y pesqueras',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8650',
                'contact_email' => 'direccion.cbap@uan.edu.mx',
                'location' => 'Compostela, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Ingeniería en Acuacultura',
                    'Medicina Veterinaria y Zootecnia',
                    'Ingeniería en Biotecnología',
                    'Investigación agropecuaria'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'UACYA',
                'name' => 'Unidad Académica de Contaduría y Administración',
                'short_name' => 'UACYA',
                'description' => 'Unidad académica de ciencias económico-administrativas',
                'type' => 'unidad_academica',
                'contact_phone' => '311-211-8800 ext. 8660',
                'contact_email' => 'direccion.cya@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo"',
                'schedule' => 'Lunes a Viernes de 7:00 a 21:00 hrs',
                'services' => json_encode([
                    'Licenciatura en Contaduría',
                    'Licenciatura en Administración',
                    'Licenciatura en Economía',
                    'Programas de posgrado'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'GENERAL',
                'name' => 'Información General',
                'short_name' => 'General',
                'description' => 'Información general de la Universidad Autónoma de Nayarit',
                'type' => 'general',
                'contact_phone' => '311-211-8800',
                'contact_email' => 'info@uan.edu.mx',
                'location' => 'Ciudad de la Cultura "Amado Nervo", Tepic, Nayarit',
                'schedule' => '24/7 (Asistente Virtual)',
                'services' => json_encode([
                    'Información general',
                    'Oferta educativa',
                    'Eventos y actividades',
                    'Directorio institucional',
                    'Orientación estudiantil'
                ]),
                'head_name' => null,
                'head_title' => null,
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('departments')->insert($departments);
    }
};
