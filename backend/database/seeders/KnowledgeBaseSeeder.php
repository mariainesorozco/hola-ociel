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
            // ===== INFORMACIÓN GENERAL UAN =====
            [
                'title' => 'Historia de la Universidad Autónoma de Nayarit',
                'content' => 'La Universidad Autónoma de Nayarit (UAN) fue fundada el 25 de abril de 1969. Su campus principal se denomina Ciudad de la Cultura "Amado Nervo" y está ubicado en Tepic, Nayarit. El proyecto universitario inició en 1966 con la creación del Patronato de la Ciudad de la Cultura. Su lema institucional es "Por lo nuestro a lo universal".',
                'category' => 'informacion_general',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['historia', 'fundación', '1969', 'Amado Nervo', 'lema', 'patronato']),
                'contact_info' => 'Tel: 311-211-8800',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Misión, Visión y Valores de la UAN',
                'content' => 'La UAN tiene como misión formar integralmente profesionales competentes, emprendedores y humanistas, realizar investigación que contribuya al desarrollo sustentable y extender la cultura y los servicios universitarios para coadyuvar al desarrollo de la sociedad. Su visión es ser una universidad pública de excelencia que contribuya significativamente al desarrollo sustentable de Nayarit, México y el mundo.',
                'category' => 'informacion_general',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['misión', 'visión', 'valores', 'excelencia', 'desarrollo sustentable']),
                'contact_info' => 'Tel: 311-211-8800',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Ubicación y Contacto General UAN',
                'content' => 'La Universidad Autónoma de Nayarit está ubicada en Ciudad de la Cultura "Amado Nervo", Bulevar Tepic-Xalisco s/n, Colonia Centro, C.P. 63000, Tepic, Nayarit, México. Teléfono general: 311-211-8800. Sitio web: https://www.uan.edu.mx. Correo institucional: info@uan.edu.mx',
                'category' => 'informacion_general',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['ubicación', 'dirección', 'contacto', 'teléfono', 'Ciudad de la Cultura']),
                'contact_info' => 'Tel: 311-211-8800, info@uan.edu.mx',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== OFERTA EDUCATIVA =====
            [
                'title' => 'Oferta Educativa Nivel Superior UAN',
                'content' => 'La UAN ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados organizados en cuatro áreas del conocimiento: Ciencias Básicas e Ingenierías, Ciencias Sociales y Humanidades, Ciencias de la Salud, y Ciencias Biológico Agropecuarias y Pesqueras. Los programas incluyen carreras como Medicina, Derecho, Ingeniería, Contaduría, Administración, Educación, entre otras.',
                'category' => 'oferta_educativa',
                'department' => 'SECRETARIA_ACADEMICA',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['licenciaturas', 'maestrías', 'doctorados', 'carreras', 'programas académicos']),
                'contact_info' => 'Secretaría Académica: 311-211-8800 ext. 8520',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Área de Ciencias de la Salud UAN',
                'content' => 'El área de Ciencias de la Salud incluye la Unidad Académica de Medicina y la Unidad Académica de Enfermería y Odontología. Ofrece las licenciaturas en Medicina, Enfermería y Odontología, así como programas de especialidades médicas y posgrados. Cuenta con clínicas universitarias para prácticas profesionales y servicio social.',
                'category' => 'oferta_educativa',
                'department' => 'UAM',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['medicina', 'enfermería', 'odontología', 'ciencias de la salud', 'clínicas']),
                'contact_info' => 'UA Medicina: 311-211-8800 ext. 8630',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Educación Media Superior UAN',
                'content' => 'La UAN cuenta con 15 planteles de preparatoria distribuidos en todo el estado de Nayarit: Preparatorias 1 y 13 en Tepic, Preparatoria 2 en Santiago Ixcuintla, Preparatoria 3 en Acaponeta, Preparatoria 4 en Tecuala, Preparatoria 5 en Tuxpan, Preparatoria 6 en Ixtlán del Río, Preparatoria 7 en Compostela, Preparatoria 8 en Ahuacatlán, Preparatoria 9 en Villa Hidalgo, Preparatoria 10 en Valle de Banderas, Preparatoria 11 en Ruiz, Preparatoria 12 en San Blas, Preparatoria 14 en modalidad abierta, y Preparatoria 15 en Puente de Camotlán.',
                'category' => 'oferta_educativa',
                'department' => 'SEMS',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['preparatoria', 'bachillerato', 'media superior', 'planteles', 'SEMS']),
                'contact_info' => 'SEMS: 311-211-8800 ext. 8540',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== TRÁMITES Y SERVICIOS ACADÉMICOS =====
            [
                'title' => 'Proceso de Admisión Licenciatura UAN',
                'content' => 'Para ingresar a una licenciatura en la UAN debes: 1) Tener certificado de bachillerato, 2) Registrarte en el proceso de admisión, 3) Presentar y aprobar el examen de selección, 4) Completar el proceso de inscripción con la documentación requerida. Las convocatorias se publican en el sitio web oficial. El proceso incluye examen de conocimientos generales y por área específica.',
                'category' => 'tramites',
                'department' => 'DGAE',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['admisión', 'inscripción', 'examen', 'selección', 'bachillerato', 'convocatoria']),
                'contact_info' => 'DGAE: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Proceso de Titulación UAN',
                'content' => 'La UAN ofrece las siguientes modalidades de titulación: 1) Tesis de licenciatura, 2) Tesina, 3) Examen general de conocimientos, 4) Estudios de posgrado, 5) Experiencia profesional. Requisitos generales: 100% de créditos cubiertos, servicio social liberado, no tener adeudos académicos o administrativos. El proceso se realiza a través de la DGAE.',
                'category' => 'tramites',
                'department' => 'DGAE',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['titulación', 'tesis', 'tesina', 'examen general', 'egreso', 'título']),
                'contact_info' => 'DGAE: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Servicio Social UAN',
                'content' => 'El servicio social es obligatorio para obtener el título de licenciatura. Duración mínima: 480 horas (6 meses). Se puede realizar en dependencias de gobierno, organizaciones civiles, o proyectos universitarios. Los estudiantes deben tener al menos 70% de créditos para iniciarlo. El registro y seguimiento se realiza a través de la DGAE.',
                'category' => 'tramites',
                'department' => 'DGAE',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['servicio social', '480 horas', 'obligatorio', '70% créditos', 'DGAE']),
                'contact_info' => 'DGAE: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== SERVICIOS UNIVERSITARIOS =====
            [
                'title' => 'Sistema Bibliotecario UAN',
                'content' => 'La UAN cuenta con un sistema bibliotecario que incluye biblioteca central y bibliotecas especializadas en cada unidad académica. Servicios: préstamo de libros, consulta de bases de datos digitales, cubículos de estudio individual y grupal, acceso a internet y wifi gratuito, servicios de impresión y digitalización.',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['biblioteca', 'libros', 'bases de datos', 'wifi', 'estudio', 'consulta']),
                'contact_info' => 'Biblioteca Central: 311-211-8800 ext. 8600',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Servicios de Sistemas y Tecnología UAN',
                'content' => 'La Dirección General de Sistemas proporciona: acceso a plataformas educativas (PiiDA, SAi), desarrollo y mantenimiento de sistemas administrativos.',
                'category' => 'servicios',
                'department' => 'DGS',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['sistemas', 'SAI', 'PIIDA', 'plataformas', 'tecnología']),
                'contact_info' => 'DGS: 311-211-8800 ext. 8540, dgs@uan.edu.mx',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Acceso a Plataforma PIIDA',
                'content' => 'PIIDA es la plataforma educativa institucional de la UAN. Para acceder: 1) Usa tu número de matrícula como usuario, 2) Tu contraseña inicial es tu CURP, 3) Si olvidaste tu contraseña, solicita restablecimiento en la DGS, 4) La plataforma está disponible 24/7 en alumnos.piida.uan.mx.',
                'category' => 'sistemas',
                'department' => 'DGS',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['PIIDA', 'plataforma', 'matrícula', 'CURP', 'calificaciones']),
                'contact_info' => 'DGS: 311-211-8800 ext. 8540, dgs@uan.edu.mx',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Actividades Deportivas y Culturales UAN',
                'content' => 'La UAN promueve el desarrollo integral a través de actividades deportivas (fútbol, basquetbol, voleibol, atletismo, natación) y culturales (danza, teatro, música, artes plásticas). Cuenta con instalaciones deportivas, centro cultural, grupos artísticos representativos y programas de formación artística.',
                'category' => 'servicios',
                'department' => 'SECRETARIA_EXTENSION',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['deportes', 'cultura', 'danza', 'teatro', 'música', 'actividades']),
                'contact_info' => 'Extensión y Vinculación: 311-211-8800 ext. 8580',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== INVESTIGACIÓN Y POSGRADO =====
            [
                'title' => 'Programas de Posgrado UAN',
                'content' => 'La UAN ofrece programas de posgrado en las cuatro áreas del conocimiento: maestrías en Educación, Estudios de Género, Justicia Alternativa, Terapia Sistémica, entre otras; y doctorados en Ciencias Sociales, Derecho Interinstitucional, y Psicología Interinstitucional. Los programas están diseñados para formar investigadores y profesionales especializados.',
                'category' => 'oferta_educativa',
                'department' => 'SIP',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['posgrado', 'maestría', 'doctorado', 'investigación', 'especialización']),
                'contact_info' => 'SIP: 311-211-8800 ext. 8550',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Centros de Investigación UAN',
                'content' => 'La UAN cuenta con diversos centros y grupos de investigación en áreas como biotecnología, ciencias sociales, educación, salud, y desarrollo sustentable. Participación en convocatorias CONACYT, proyectos internacionales, y vinculación con sectores productivos. Los estudiantes pueden participar en proyectos como tesistas o asistentes de investigación.',
                'category' => 'investigacion',
                'department' => 'SIP',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['investigación', 'CONACYT', 'biotecnología', 'desarrollo sustentable', 'tesistas']),
                'contact_info' => 'SIP: 311-211-8800 ext. 8550',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== ESTRUCTURA ORGANIZACIONAL =====
            [
                'title' => 'Autoridades Universitarias UAN',
                'content' => 'La máxima autoridad de la UAN es la Rectora, Dra. Norma Liliana Galván Meza. La estructura organizacional incluye: Secretaría General, Secretaría Académica, Secretaría de Planeación Programación e Infraestructura, Secretaría de Educación Media Superior, Secretaría de Investigación y Posgrado, Secretaría de Administración, Secretaría de Finanzas, y Secretaría de Extensión y Vinculación.',
                'category' => 'informacion_general',
                'department' => 'RECTORIA',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['rectora', 'autoridades', 'secretarías', 'estructura', 'organización']),
                'contact_info' => 'Rectoría: 311-211-8800 ext. 8500',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== INFORMACIÓN FINANCIERA Y BECAS =====
            [
                'title' => 'Becas y Apoyos Económicos UAN',
                'content' => 'La UAN ofrece diversos programas de becas: Beca de Excelencia Académica, Beca de Apoyo Económico, Becas Santander, Becas CONACYT para posgrado, y apoyos para estudiantes de escasos recursos. Los requisitos y convocatorias se publican semestralmente. También existen programas de trabajo estudiantil y becas deportivas y culturales.',
                'category' => 'servicios',
                'department' => 'SECRETARIA_ACADEMICA',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['becas', 'apoyo económico', 'Santander', 'CONACYT', 'excelencia académica']),
                'contact_info' => 'Desarrollo Estudiantil: 311-211-8800 ext. 8525',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Costos y Cuotas UAN 2024-2025',
                'content' => 'La educación en la UAN es prácticamente gratuita. Los estudiantes solo cubren: cuota de inscripción semestral ($200-$500 pesos dependiendo de la carrera), cuota de examen ($150 pesos), y algunos materiales específicos de laboratorio. La universidad es pública y subsidiada por el gobierno estatal y federal.',
                'category' => 'tramites',
                'department' => 'SECRETARIA_FINANZAS',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['costos', 'cuotas', 'inscripción', 'gratuita', 'pública', 'semestral']),
                'contact_info' => 'Secretaría de Finanzas: 311-211-8800 ext. 8570',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== MOVILIDAD E INTERCAMBIO =====
            [
                'title' => 'Programas de Movilidad e Intercambio UAN',
                'content' => 'La UAN participa en programas de movilidad nacional e internacional. Convenios con universidades de México, Estados Unidos, Europa y Latinoamérica. Los estudiantes pueden realizar estancias académicas de un semestre. Requisitos: promedio mínimo 8.0, nivel de idioma requerido, y estar al corriente académicamente.',
                'category' => 'servicios',
                'department' => 'SECRETARIA_ACADEMICA',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['movilidad', 'intercambio', 'internacional', 'estancias', 'convenios']),
                'contact_info' => 'Relaciones Interinstitucionales: 311-211-8800 ext. 8520',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== SERVICIOS DE SALUD =====
            [
                'title' => 'Servicios Médicos Universitarios UAN',
                'content' => 'La UAN cuenta con servicios médicos para la comunidad universitaria: consulta general, urgencias menores, primeros auxilios, campañas de vacunación, y programas de prevención. Ubicados en el campus principal y algunas unidades académicas. Atención gratuita para estudiantes y empleados con credencial vigente.',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['servicios médicos', 'salud', 'consulta', 'urgencias', 'primeros auxilios']),
                'contact_info' => 'Servicios Médicos: 311-211-8800 ext. 8590',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== CALENDARIO ACADÉMICO =====
            [
                'title' => 'Calendario Escolar UAN 2024-2025',
                'content' => 'El calendario académico UAN funciona por semestres: Semestre Agosto-Enero y Semestre Febrero-Julio. Incluye períodos de inscripción, clases regulares, exámenes parciales, semana de exámenes finales, período de vacaciones, y actividades especiales. Las fechas específicas se publican oficialmente cada año académico.',
                'category' => 'informacion_general',
                'department' => 'SECRETARIA_ACADEMICA',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['calendario', 'semestres', 'agosto', 'febrero', 'exámenes', 'vacaciones']),
                'contact_info' => 'Secretaría Académica: 311-211-8800 ext. 8520',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== EXTENSIÓN Y VINCULACIÓN =====
            [
                'title' => 'Programas de Extensión Universitaria UAN',
                'content' => 'La UAN desarrolla programas de extensión hacia la comunidad: educación continua, cursos de actualización profesional, talleres culturales abiertos al público, conferencias magistrales, y programas de responsabilidad social. La universidad mantiene vínculos estrechos con los sectores productivo y social de Nayarit.',
                'category' => 'servicios',
                'department' => 'SECRETARIA_EXTENSION',
                'user_types' => json_encode(['public', 'employee']),
                'keywords' => json_encode(['extensión', 'educación continua', 'responsabilidad social', 'comunidad']),
                'contact_info' => 'Extensión y Vinculación: 311-211-8800 ext. 8580',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // ===== PREGUNTAS FRECUENTES ESPECÍFICAS =====
            [
                'title' => 'Revalidación de Estudios UAN',
                'content' => 'Para revalidar estudios de otras instituciones en la UAN: 1) Presentar solicitud en DGAE, 2) Entregar documentos académicos oficiales, 3) Realizar equivalencia de materias, 4) Presentar exámenes extraordinarios si es necesario, 5) Cubrir cuotas correspondientes. El proceso puede tomar 4-6 semanas dependiendo de la complejidad.',
                'category' => 'tramites',
                'department' => 'DGAE',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['revalidación', 'equivalencia', 'materias', 'exámenes extraordinarios', 'DGAE']),
                'contact_info' => 'DGAE: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Cambio de Carrera UAN',
                'content' => 'Para solicitar cambio de carrera dentro de la UAN: 1) Tener al menos un semestre cursado, 2) Promedio mínimo de 8.0, 3) Presentar solicitud justificada, 4) Pasar por entrevista con coordinación académica, 5) Realizar equivalencias de materias cursadas. Se evalúa disponibilidad de cupo en la carrera destino.',
                'category' => 'tramites',
                'department' => 'DGAE',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['cambio de carrera', 'promedio', 'equivalencias', 'cupo', 'entrevista']),
                'contact_info' => 'DGAE: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            [
                'title' => 'Credencial Universitaria UAN',
                'content' => 'La credencial universitaria es obligatoria para todos los miembros de la comunidad UAN. Para obtenerla: 1) Presentar fotografía tamaño infantil, 2) Comprobante de inscripción vigente, 3) Identificación oficial, 4) Cubrir cuota correspondiente. Se debe renovar cada semestre para estudiantes y anualmente para empleados.',
                'category' => 'tramites',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['credencial', 'fotografía', 'inscripción', 'renovación', 'semestral']),
                'contact_info' => 'Servicios Generales: 311-211-8800 ext. 8560',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Insertar todas las entradas
        DB::table('knowledge_base')->insert($knowledgeEntries);

        // Mostrar mensaje de confirmación en consola
        echo "✅ Se insertaron " . count($knowledgeEntries) . " entradas en la base de conocimientos exitosamente.\n";
    }
}
