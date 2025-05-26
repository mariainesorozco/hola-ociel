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
            // === INFORMACIÓN GENERAL VERIFICADA ===
            [
                'title' => 'Historia y Fundación de la UAN',
                'content' => 'La Universidad Autónoma de Nayarit fue fundada el 25 de abril de 1969. Está ubicada en la Ciudad de la Cultura "Amado Nervo" en Tepic, Nayarit, México. Es una institución pública de educación superior que ha crecido significativamente desde su fundación, convirtiéndose en la principal universidad del estado de Nayarit.',
                'category' => 'informacion_general',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['UAN', 'universidad', 'historia', 'fundación', 'Nayarit', '1969', 'Amado Nervo']),
                'contact_info' => 'Tel: 311-211-8800',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === OFERTA EDUCATIVA ESPECÍFICA ===
            [
                'title' => 'Oferta Educativa Completa UAN',
                'content' => 'La UAN ofrece más de 40 programas de licenciatura, 25 maestrías y 8 doctorados distribuidos en las siguientes áreas académicas:

• Área de Ciencias Básicas e Ingenierías: Incluye carreras como Ingeniería Civil, Sistemas Computacionales, Electrónica, Mecánica, Química
• Área de Ciencias Sociales y Humanidades: Derecho, Psicología, Trabajo Social, Comunicación, Turismo
• Área de Ciencias de la Salud: Medicina, Enfermería, Odontología, Nutrición
• Área de Ciencias Biológico Agropecuarias y Pesqueras: Biología, Agronomía, Medicina Veterinaria, Acuacultura
• Área de Ciencias Económico Administrativas: Administración, Contaduría, Economía, Comercio Internacional

Cada programa cuenta con planes de estudio actualizados y reconocimiento oficial de la SEP.',
                'category' => 'oferta_educativa',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['carreras', 'licenciaturas', 'maestrías', 'doctorados', 'ingeniería', 'medicina', 'derecho', 'psicología']),
                'contact_info' => 'DGSA: 311-211-8800 ext. 8530, Para información específica de cada carrera contactar la unidad académica correspondiente',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === PROCESO DE ADMISIÓN DETALLADO ===
            [
                'title' => 'Proceso de Admisión e Inscripción',
                'content' => 'Para ingresar a la UAN como estudiante de licenciatura, debes seguir estos pasos:

REQUISITOS BÁSICOS:
• Certificado de bachillerato o equivalente
• Acta de nacimiento
• CURP
• Fotografías tamaño infantil
• Comprobante de domicilio

PROCESO:
1. Registro en línea en la convocatoria correspondiente
2. Pago de derechos de examen
3. Presentar examen de admisión (EXANI-II)
4. Esperar resultados
5. Si eres aceptado, realizar inscripción en fechas establecidas

FECHAS IMPORTANTES:
Las convocatorias se publican generalmente:
• Primer semestre: Febrero-Marzo
• Segundo semestre: Julio-Agosto

NOTA: Las fechas exactas varían cada año, siempre verificar en el sitio oficial.',
                'category' => 'tramites',
                'department' => 'DGSA',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['inscripción', 'admisión', 'examen', 'EXANI', 'convocatoria', 'requisitos']),
                'contact_info' => 'DGSA: 311-211-8800 ext. 8530, Sitio web: https://www.uan.edu.mx',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === SERVICIOS ESTUDIANTILES ===
            [
                'title' => 'Servicios de Biblioteca UAN',
                'content' => 'El Sistema Bibliotecario de la UAN ofrece servicios integrales para apoyar las actividades académicas:

BIBLIOTECA CENTRAL "MAGNA":
• Acervo de más de 100,000 volúmenes
• Salas de lectura con capacidad para 800 usuarios
• Cubículos individuales y grupales
• Acceso a internet wifi gratuito
• Computadoras para consulta

SERVICIOS DISPONIBLES:
• Préstamo interno y externo de libros
• Consulta de bases de datos especializadas
• Servicio de fotocopiado
• Orientación bibliográfica
• Talleres de alfabetización informacional

BIBLIOTECAS ESPECIALIZADAS:
Cada área académica cuenta con biblioteca especializada en su campus correspondiente.

HORARIOS:
Lunes a viernes: 7:00 a 21:00 horas
Sábados: 8:00 a 14:00 horas',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['biblioteca', 'libros', 'consulta', 'estudio', 'wifi', 'Magna', 'préstamo']),
                'contact_info' => 'Biblioteca Central: 311-211-8800 ext. 8600',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === SERVICIOS DE SISTEMAS ===
            [
                'title' => 'Servicios de la Dirección General de Sistemas',
                'content' => 'La DGS proporciona soporte tecnológico integral a la comunidad universitaria:

SERVICIOS PARA ESTUDIANTES:
• Activación y soporte del correo institucional (@uan.edu.mx)
• Acceso a plataformas educativas (Moodle, Microsoft Teams)
• Soporte técnico para equipos en laboratorios
• Acceso a software especializado
• Conectividad wifi en todo el campus

SERVICIOS PARA EMPLEADOS:
• Cuentas de correo institucional
• Acceso a sistemas administrativos
• Soporte técnico especializado
• Mantenimiento de equipos institucionales

PLATAFORMAS PRINCIPALES:
• PiiDA: Sistema integral de administración
• Moodle: Plataforma educativa
• Portal UAN: Servicios en línea
• Biblioteca Digital

HORARIO DE ATENCIÓN:
Lunes a viernes: 8:00 a 15:00 horas

Para soporte urgente, también disponible vía correo electrónico.',
                'category' => 'servicios',
                'department' => 'DGS',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['sistemas', 'correo', 'plataformas', 'soporte', 'wifi', 'Moodle', 'PiiDA']),
                'contact_info' => 'DGS: 311-211-8800 ext. 8540, sistemas@uan.edu.mx',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === TITULACIÓN Y EGRESO ===
            [
                'title' => 'Proceso de Titulación UAN',
                'content' => 'La UAN ofrece múltiples modalidades de titulación para egresados de licenciatura:

MODALIDADES DISPONIBLES:
1. Tesis profesional
2. Tesina
3. Examen general de conocimientos
4. Estudios de posgrado
5. Experiencia profesional
6. Actividad de apoyo a la docencia
7. Trabajo profesional
8. Actividades de extensión

REQUISITOS GENERALES:
• Haber cubierto el 100% de los créditos del plan de estudios
• Servicio social liberado
• No tener adeudos con la institución
• Presentar documentación completa

DOCUMENTACIÓN REQUERIDA:
• Solicitud de titulación
• Certificado de estudios
• Constancia de servicio social
• Acta de nacimiento
• CURP
• Fotografías

El proceso puede tardar entre 2 a 6 meses dependiendo de la modalidad elegida.',
                'category' => 'tramites',
                'department' => 'DGSA',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['titulación', 'tesis', 'tesina', 'examen', 'egreso', 'modalidades', 'servicio social']),
                'contact_info' => 'DGSA: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === SERVICIOS ESTUDIANTILES ADICIONALES ===
            [
                'title' => 'Becas y Apoyos Estudiantiles',
                'content' => 'La UAN ofrece diversos programas de becas y apoyos para estudiantes:

TIPOS DE BECAS:
• Beca de Excelencia Académica
• Beca de Apoyo Económico
• Beca de Manutención
• Becas Federales (Benito Juárez, etc.)
• Becas de Movilidad Estudiantil
• Becas Deportivas y Culturales

REQUISITOS GENERALES:
• Estar inscrito en la UAN
• Mantener promedio mínimo (varía según beca)
• Situación socioeconómica documentada
• No tener adeudos académicos o administrativos

PROCESO:
1. Consultar convocatorias (generalmente en febrero y agosto)
2. Reunir documentación requerida
3. Registrarse en línea
4. Entregar documentos en fechas establecidas
5. Esperar resultados

DOCUMENTACIÓN COMÚN:
• Estudio socioeconómico
• Kardex actualizado
• Comprobantes de ingresos familiares
• CURP y acta de nacimiento

Para información específica sobre convocatorias vigentes, contactar directamente.',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['becas', 'apoyo', 'económico', 'excelencia', 'Benito Juárez', 'convocatoria']),
                'contact_info' => 'Coordinación de Becas: 311-211-8800, consultar sitio web para convocatorias',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === SERVICIOS MÉDICOS ===
            [
                'title' => 'Servicios Médicos Universitarios',
                'content' => 'La UAN cuenta con servicios médicos para atender a la comunidad universitaria:

SERVICIOS DISPONIBLES:
• Consulta médica general
• Primeros auxilios
• Atención de emergencias menores
• Campañas de prevención y salud
• Referencias a especialistas cuando se requiere

UBICACIÓN:
Centro de Salud Universitario, ubicado en Ciudad de la Cultura

HORARIOS:
Lunes a viernes: 8:00 a 15:00 horas

REQUISITOS:
• Ser estudiante, docente o empleado activo de la UAN
• Presentar credencial universitaria vigente
• En caso de emergencia, no se requiere documentación previa

SERVICIOS ESPECIALES:
• Exámenes médicos para estudiantes de nuevo ingreso
• Certificados médicos para actividades deportivas
• Campañas de vacunación
• Talleres de educación para la salud

El servicio es gratuito para toda la comunidad universitaria.',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['médicos', 'salud', 'consulta', 'emergencias', 'primeros auxilios', 'centro salud']),
                'contact_info' => 'Centro de Salud Universitario: 311-211-8800',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === ACTIVIDADES CULTURALES Y DEPORTIVAS ===
            [
                'title' => 'Actividades Culturales y Deportivas',
                'content' => 'La UAN promueve el desarrollo integral de los estudiantes a través de actividades culturales y deportivas:

ACTIVIDADES CULTURALES:
• Talleres de danza folklórica y contemporánea
• Grupos musicales (coro, banda, estudiantina)
• Teatro universitario
• Artes plásticas y visuales
• Literatura y creación literaria
• Fotografía y medios audiovisuales

ACTIVIDADES DEPORTIVAS:
• Fútbol soccer y americano
• Básquetbol y voleibol
• Atletismo y natación
• Artes marciales (karate, taekwondo)
• Deportes de raqueta (tenis, ping pong)
• Ajedrez

INSTALACIONES:
• Auditorio Amado Nervo
• Gimnasio universitario
• Canchas deportivas múltiples
• Piscina olímpica
• Pista de atletismo
• Dojo para artes marciales

PARTICIPACIÓN:
• Talleres abiertos para toda la comunidad
• Equipos representativos universitarios
• Competencias inter-universitarias
• Festivales culturales anuales

La participación es gratuita para estudiantes regulares.',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee', 'public']),
                'keywords' => json_encode(['cultura', 'deportes', 'talleres', 'danza', 'música', 'teatro', 'fútbol', 'básquetbol']),
                'contact_info' => 'Dirección de Cultura: 311-211-8800, Dirección de Deportes: 311-211-8800',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === LABORATORIOS Y SERVICIOS ESPECIALIZADOS ===
            [
                'title' => 'Laboratorios y Servicios Especializados',
                'content' => 'La UAN cuenta con laboratorios e instalaciones especializadas para apoyo académico:

LABORATORIOS DE CÓMPUTO:
• Más de 20 laboratorios distribuidos en el campus
• Software especializado por área de estudio
• Acceso a internet de alta velocidad
• Soporte técnico especializado

LABORATORIOS CIENTÍFICOS:
• Laboratorios de Química, Física y Biología
• Laboratorios de Investigación especializados
• Equipos de alta tecnología para prácticas
• Protocolos de seguridad estrictos

LABORATORIOS ESPECIALIZADOS POR ÁREA:
• Medicina: Laboratorios de anatomía, fisiología, patología
• Ingenierías: Laboratorios de mecánica, electrónica, hidráulica
• Ciencias Agropecuarias: Laboratorios de suelos, plantas, veterinaria
• Odontología: Clínicas y laboratorios dentales

ACCESO:
• Horarios programados según materias
• Uso libre en horarios establecidos
• Reservación para proyectos especiales
• Supervisión de personal técnico especializado

SERVICIOS ADICIONALES:
• Mantenimiento preventivo y correctivo
• Capacitación en uso de equipos
• Asesoría técnica para proyectos de investigación',
                'category' => 'servicios',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'employee']),
                'keywords' => json_encode(['laboratorios', 'cómputo', 'científicos', 'equipos', 'software', 'investigación']),
                'contact_info' => 'Coordinación de Laboratorios: 311-211-8800, DGS para lab. cómputo: ext. 8540',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === POSGRADOS ===
            [
                'title' => 'Oferta de Posgrados UAN',
                'content' => 'La UAN ofrece programas de posgrado de alta calidad académica:

MAESTRÍAS (25 programas):
• Maestría en Ciencias en Ingeniería
• Maestría en Administración
• Maestría en Derecho
• Maestría en Educación
• Maestría en Ciencias Biológicas
• Maestría en Desarrollo Económico Local
• Maestría en Ciencias en Recursos Naturales y Medio Ambiente
• Entre otras especializaciones

DOCTORADOS (8 programas):
• Doctorado en Ciencias Biológico Agropecuarias
• Doctorado en Ciencias en Ingeniería
• Doctorado en Ciencias Sociales
• Doctorado en Educación
• Doctorado en Derecho
• Entre otros

ESPECIALIDADES MÉDICAS:
• Medicina Familiar
• Ginecología y Obstetricia
• Pediatría
• Anestesiología

REQUISITOS GENERALES:
• Título de licenciatura (para maestría)
• Título de maestría (para doctorado)
• Promedio mínimo establecido por cada programa
• Examen de admisión específico
• Entrevista académica

BECAS DISPONIBLES:
• CONACYT para programas en el Padrón Nacional de Posgrados
• Becas institucionales
• Apoyos para tesis y estancias de investigación',
                'category' => 'oferta_educativa',
                'department' => 'GENERAL',
                'user_types' => json_encode(['student', 'public']),
                'keywords' => json_encode(['posgrado', 'maestría', 'doctorado', 'especialidad', 'CONACYT', 'investigación']),
                'contact_info' => 'Coordinación de Posgrado: 311-211-8800, DGSA: ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === INTERCAMBIO ACADÉMICO ===
            [
                'title' => 'Programas de Intercambio Académico',
                'content' => 'La UAN participa en programas de movilidad e intercambio estudiantil:

PROGRAMAS NACIONALES:
• ECOES (Espacio Común de Educación Superior)
• ANUIES (Asociación Nacional de Universidades)
• Convenios específicos con universidades mexicanas

PROGRAMAS INTERNACIONALES:
• Intercambios con universidades de Estados Unidos
• Programas con instituciones europeas
• Convenios con universidades latinoamericanas
• Estancias de investigación internacionales

TIPOS DE MOVILIDAD:
• Intercambio semestral
• Estancias cortas (verano, invierno)
• Prácticas profesionales internacionales
• Estancias de investigación

REQUISITOS:
• Promedio mínimo de 8.0
• Haber cursado al menos 4 semestres
• Dominio del idioma del país destino
• Solvencia académica y administrativa
• Carta de motivos y proyecto académico

APOYOS DISPONIBLES:
• Becas parciales de transporte
• Exención de colegiaturas
• Asesoría para trámites migratorios
• Seguimiento académico durante el intercambio

PROCESO:
Las convocatorias se publican generalmente en febrero y agosto.',
                'category' => 'servicios',
                'department' => 'DGSA',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['intercambio', 'movilidad', 'internacional', 'ECOES', 'ANUIES', 'estancias']),
                'contact_info' => 'Coordinación de Intercambio Académico: 311-211-8800 ext. 8530',
                'priority' => 'medium',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

            // === SERVICIO SOCIAL ===
            [
                'title' => 'Servicio Social Universitario',
                'content' => 'El servicio social es un requisito obligatorio para la titulación en la UAN:

REQUISITOS PARA REALIZAR SERVICIO SOCIAL:
• Haber cubierto el 70% de los créditos del plan de estudios
• Promedio mínimo de 7.0
• Estar al corriente en pagos y sin adeudos
• No tener materias reprobadas pendientes

DURACIÓN:
• 480 horas mínimo (equivalente a 6 meses)
• Puede realizarse de manera intensiva o distribuida

MODALIDADES:
• Programas institucionales de la UAN
• Dependencias gubernamentales
• Organizaciones de la sociedad civil
• Empresas con programas sociales autorizados
• Proyectos de investigación universitarios

ÁREAS DE PARTICIPACIÓN:
• Educación y alfabetización
• Salud comunitaria
• Desarrollo rural y urbano
• Medio ambiente y sustentabilidad
• Tecnología y sistemas
• Desarrollo social y comunitario

PROCESO:
1. Solicitar autorización con 70% de créditos
2. Seleccionar programa o dependencia
3. Presentar plan de trabajo
4. Realizar las 480 horas
5. Entregar informes mensuales y final
6. Obtener carta de liberación

La liberación del servicio social es requisito indispensable para titulación.',
                'category' => 'tramites',
                'department' => 'DGSA',
                'user_types' => json_encode(['student']),
                'keywords' => json_encode(['servicio social', 'liberación', '480 horas', 'titulación', 'requisito', 'comunidad']),
                'contact_info' => 'Coordinación de Servicio Social: 311-211-8800 ext. 8530',
                'priority' => 'high',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('knowledge_base')->insert($knowledgeEntries);
    }
}
