<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PreparatoriasUANSeeder extends Seeder
{
    /**
     * Agregar todas las preparatorias UAN al catálogo de departamentos
     */
    public function run()
    {
        $preparatorias = [
            // ===== PREPARATORIA NO. 1 - TEPIC =====
            [
                'code' => 'PREP01',
                'name' => 'Unidad Académica Preparatoria No. 1',
                'short_name' => 'Preparatoria No. 1',
                'description' => 'Plantel de educación media superior en Tepic',
                'type' => 'preparatoria',
                'contact_phone' => '311-211-8800 ext. 8701',
                'contact_email' => 'prep01@uan.edu.mx',
                'location' => 'Tepic, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 2 - SANTIAGO IXCUINTLA =====
            [
                'code' => 'PREP02',
                'name' => 'Unidad Académica Preparatoria No. 2',
                'short_name' => 'Preparatoria No. 2',
                'description' => 'Plantel de educación media superior en Santiago Ixcuintla',
                'type' => 'preparatoria',
                'contact_phone' => '323-235-0127',
                'contact_email' => 'prep02@uan.edu.mx',
                'location' => 'Santiago Ixcuintla, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 3 - ACAPONETA =====
            [
                'code' => 'PREP03',
                'name' => 'Unidad Académica Preparatoria No. 3',
                'short_name' => 'Preparatoria No. 3',
                'description' => 'Plantel de educación media superior en Acaponeta',
                'type' => 'preparatoria',
                'contact_phone' => '325-253-2078',
                'contact_email' => 'prep03@uan.edu.mx',
                'location' => 'Acaponeta, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 4 - TECUALA =====
            [
                'code' => 'PREP04',
                'name' => 'Unidad Académica Preparatoria No. 4',
                'short_name' => 'Preparatoria No. 4',
                'description' => 'Plantel de educación media superior en Tecuala',
                'type' => 'preparatoria',
                'contact_phone' => '325-256-0089',
                'contact_email' => 'prep04@uan.edu.mx',
                'location' => 'Tecuala, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 5 - TUXPAN =====
            [
                'code' => 'PREP05',
                'name' => 'Unidad Académica Preparatoria No. 5',
                'short_name' => 'Preparatoria No. 5',
                'description' => 'Plantel de educación media superior en Tuxpan',
                'type' => 'preparatoria',
                'contact_phone' => '323-230-0156',
                'contact_email' => 'prep05@uan.edu.mx',
                'location' => 'Tuxpan, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 6 - IXTLÁN DEL RÍO =====
            [
                'code' => 'PREP06',
                'name' => 'Unidad Académica Preparatoria No. 6',
                'short_name' => 'Preparatoria No. 6',
                'description' => 'Plantel de educación media superior en Ixtlán del Río',
                'type' => 'preparatoria',
                'contact_phone' => '324-242-0087',
                'contact_email' => 'prep06@uan.edu.mx',
                'location' => 'Ixtlán del Río, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 7 - COMPOSTELA =====
            [
                'code' => 'PREP07',
                'name' => 'Unidad Académica Preparatoria No. 7',
                'short_name' => 'Preparatoria No. 7',
                'description' => 'Plantel de educación media superior en Compostela',
                'type' => 'preparatoria',
                'contact_phone' => '311-258-4567',
                'contact_email' => 'prep07@uan.edu.mx',
                'location' => 'Compostela, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 8 - AHUACATLÁN =====
            [
                'code' => 'PREP08',
                'name' => 'Unidad Académica Preparatoria No. 8',
                'short_name' => 'Preparatoria No. 8',
                'description' => 'Plantel de educación media superior en Ahuacatlán',
                'type' => 'preparatoria',
                'contact_phone' => '324-747-0123',
                'contact_email' => 'prep08@uan.edu.mx',
                'location' => 'Ahuacatlán, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 9 - VILLA HIDALGO =====
            [
                'code' => 'PREP09',
                'name' => 'Unidad Académica Preparatoria No. 9',
                'short_name' => 'Preparatoria No. 9',
                'description' => 'Plantel de educación media superior en Villa Hidalgo',
                'type' => 'preparatoria',
                'contact_phone' => '324-247-0098',
                'contact_email' => 'prep09@uan.edu.mx',
                'location' => 'Villa Hidalgo, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 10 - VALLE DE BANDERAS =====
            [
                'code' => 'PREP10',
                'name' => 'Unidad Académica Preparatoria No. 10',
                'short_name' => 'Preparatoria No. 10',
                'description' => 'Plantel de educación media superior en Valle de Banderas',
                'type' => 'preparatoria',
                'contact_phone' => '329-291-0167',
                'contact_email' => 'prep10@uan.edu.mx',
                'location' => 'Valle de Banderas, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 11 - RUIZ =====
            [
                'code' => 'PREP11',
                'name' => 'Unidad Académica Preparatoria No. 11',
                'short_name' => 'Preparatoria No. 11',
                'description' => 'Plantel de educación media superior en Ruiz',
                'type' => 'preparatoria',
                'contact_phone' => '323-232-0145',
                'contact_email' => 'prep11@uan.edu.mx',
                'location' => 'Ruiz, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 12 - SAN BLAS =====
            [
                'code' => 'PREP12',
                'name' => 'Unidad Académica Preparatoria No. 12',
                'short_name' => 'Preparatoria No. 12',
                'description' => 'Plantel de educación media superior en San Blas',
                'type' => 'preparatoria',
                'contact_phone' => '323-285-0134',
                'contact_email' => 'prep12@uan.edu.mx',
                'location' => 'San Blas, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 13 - TEPIC =====
            [
                'code' => 'PREP13',
                'name' => 'Unidad Académica Preparatoria No. 13',
                'short_name' => 'Preparatoria No. 13',
                'description' => 'Plantel de educación media superior en Tepic',
                'type' => 'preparatoria',
                'contact_phone' => '311-211-8800 ext. 8713',
                'contact_email' => 'prep13@uan.edu.mx',
                'location' => 'Tepic, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 14 - MODALIDAD ABIERTA =====
            [
                'code' => 'PREP14',
                'name' => 'Unidad Académica Preparatoria No. 14 - Modalidad Abierta',
                'short_name' => 'Preparatoria No. 14',
                'description' => 'Plantel de educación media superior modalidad abierta en Tepic',
                'type' => 'preparatoria',
                'contact_phone' => '311-211-8800 ext. 8714',
                'contact_email' => 'prep14@uan.edu.mx',
                'location' => 'Tepic, Nayarit',
                'schedule' => 'Horarios flexibles',
                'services' => json_encode([
                    'Bachillerato modalidad abierta',
                    'Asesorías académicas',
                    'Educación a distancia',
                    'Evaluaciones flexibles'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREPARATORIA NO. 15 - PUENTE DE CAMOTLÁN =====
            [
                'code' => 'PREP15',
                'name' => 'Unidad Académica Preparatoria No. 15',
                'short_name' => 'Preparatoria No. 15',
                'description' => 'Plantel de educación media superior en Puente de Camotlán',
                'type' => 'preparatoria',
                'contact_phone' => '324-244-0089',
                'contact_email' => 'prep15@uan.edu.mx',
                'location' => 'Puente de Camotlán, Nayarit',
                'schedule' => 'Lunes a Viernes de 7:00 a 15:00 hrs',
                'services' => json_encode([
                    'Bachillerato general',
                    'Actividades deportivas',
                    'Actividades culturales',
                    'Orientación educativa'
                ]),
                'area_knowledge' => 'Educación Media Superior',
                'has_specialized_agent' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Insertar todas las preparatorias
        DB::table('departments')->insert($preparatorias);

        // Mostrar mensaje de confirmación en consola
        echo "✅ Se insertaron " . count($preparatorias) . " preparatorias UAN exitosamente.\n";
    }
}
