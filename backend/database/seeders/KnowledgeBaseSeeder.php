<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $knowledgeEntries = [
            [
                'title' => 'Información General UAN',
                'content' => 'La Universidad Autónoma de Nayarit (UAN) es una institución pública de educación superior fundada el 25 de abril de 1969. Está ubicada en la Ciudad de la Cultura "Amado Nervo" en Tepic, Nayarit, México.',
                'category' => 'informacion_general',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['UAN', 'universidad', 'historia', 'fundación', 'Nayarit']),
                'contact_info' => 'Tel: 311-211-8800',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Inscripción a Licenciatura',
                'content' => 'Para inscribirte a una licenciatura en la UAN necesitas: 1) Certificado de bachillerato, 2) Aprobar el examen de admisión, 3) Realizar el proceso de inscripción en línea, 4) Presentar documentación completa.',
                'category' => 'tramites',
                'department' => 'DGSA',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['inscripción', 'licenciatura', 'admisión', 'examen', 'bachillerato']),
                'contact_info' => 'DGSA: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Oferta Educativa',
                'content' => 'La UAN ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados en diversas áreas del conocimiento: Ciencias Básicas e Ingenierías, Ciencias Sociales y Humanidades, Ciencias de la Salud, Ciencias Biológico Agropecuarias y Pesqueras.',
                'category' => 'oferta_educativa',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['carreras', 'licenciaturas', 'maestrías', 'doctorados', 'programas']),
                'contact_info' => 'Tel: 311-211-8800',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Servicios de Biblioteca',
                'content' => 'La UAN cuenta con un sistema bibliotecario que incluye biblioteca central y bibliotecas especializadas. Servicios: préstamo de libros, consulta en línea, bases de datos, cubículos de estudio, wifi gratuito.',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['biblioteca', 'libros', 'consulta', 'estudio', 'wifi']),
                'contact_info' => 'Biblioteca Central: 311-211-8800 ext. 8600',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Soporte Técnico de Sistemas',
                'content' => 'La Dirección General de Sistemas brinda soporte técnico a la comunidad universitaria: correo institucional, acceso a plataformas educativas, soporte de equipos, conectividad de red.',
                'category' => 'servicios',
                'department' => 'DGS',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['soporte', 'técnico', 'sistemas', 'correo', 'plataformas']),
                'contact_info' => 'DGS: 311-211-8800 ext. 8540, sistemas@uan.edu.mx',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Proceso de Titulación',
                'content' => 'Modalidades de titulación: tesis, tesina, examen general de conocimientos, estudios de posgrado, experiencia profesional. Requisitos: 100% de créditos, servicio social liberado, sin adeudos.',
                'category' => 'tramites',
                'department' => 'DGSA',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['titulación', 'tesis', 'tesina', 'examen', 'egreso']),
                'contact_info' => 'DGSA: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('knowledge_base')->insert($knowledgeEntries);
    }
}
