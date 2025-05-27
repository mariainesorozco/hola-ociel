<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnhancedPromptService
{
    private $ollamaService;
    private $knowledgeService;

    public function __construct(OllamaService $ollamaService, KnowledgeBaseService $knowledgeService)
    {
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
    }

    /**
     * Generar respuesta con prompts profesionales mejorados
     */
    public function generateProfessionalResponse(
        string $userMessage,
        string $userType = 'public',
        ?string $department = null,
        array $context = []
    ): array {

        // 1. Clasificar tipo de consulta
        $queryType = $this->classifyQuery($userMessage);

        // 2. Obtener prompt especializado
        $systemPrompt = $this->buildSpecializedPrompt($queryType, $userType, $department, $context);

        // 3. Preparar mensaje completo
        $fullPrompt = $this->buildFullPrompt($systemPrompt, $userMessage, $context);

        // 4. Generar respuesta con configuración optimizada
        $response = $this->ollamaService->generateResponse($fullPrompt, [
            'model' => $this->selectOptimalModel($queryType),
            'temperature' => $this->getOptimalTemperature($queryType),
            'max_tokens' => $this->getOptimalTokens($queryType)
        ]);

        // 5. Validar y mejorar respuesta
        if ($response['success']) {
            $enhancedResponse = $this->enhanceResponse($response['response'], $queryType, $context);
            $response['response'] = $enhancedResponse;
            $response['confidence'] = $this->calculateAdvancedConfidence($response, $context, $userMessage);
        }

        return $response;
    }

    /**
     * Clasificar tipo de consulta automáticamente
     */
    private function classifyQuery(string $message): string
    {
        $messageLower = strtolower($message);

        // Patrones de clasificación
        $patterns = [
            'tramite_especifico' => [
                'patterns' => ['inscripción', 'inscripcion', 'titulación', 'titulacion', 'revalidación', 'equivalencia', 'certificado', 'constancia'],
                'confidence' => 0.9
            ],
            'informacion_carrera' => [
                'patterns' => ['carrera', 'licenciatura', 'programa', 'plan de estudios', 'requisitos de admisión'],
                'confidence' => 0.8
            ],
            'servicio_universitario' => [
                'patterns' => ['biblioteca', 'laboratorio', 'cafetería', 'transporte', 'enfermería', 'deporte'],
                'confidence' => 0.8
            ],
            'soporte_tecnico' => [
                'patterns' => ['sistema', 'plataforma', 'correo', 'contraseña', 'usuario', 'acceso', 'login'],
                'confidence' => 0.9
            ],
            'informacion_general' => [
                'patterns' => ['horario', 'ubicación', 'dirección', 'teléfono', 'contacto'],
                'confidence' => 0.7
            ],
            'queja_problema' => [
                'patterns' => ['problema', 'queja', 'reclamo', 'error', 'falla', 'no funciona', 'molesto'],
                'confidence' => 0.9
            ],
            'consulta_academica' => [
                'patterns' => ['profesor', 'materia', 'calificación', 'examen', 'tarea', 'clase'],
                'confidence' => 0.8
            ]
        ];

        $scores = [];
        foreach ($patterns as $type => $config) {
            $score = 0;
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($messageLower, $pattern)) {
                    $score += $config['confidence'];
                }
            }
            if ($score > 0) {
                $scores[$type] = $score;
            }
        }

        return !empty($scores) ? array_key_first(arsort($scores) ? $scores : []) : 'consulta_general';
    }

    /**
     * Construir prompt especializado según tipo de consulta
     */
    private function buildSpecializedPrompt(string $queryType, string $userType, ?string $department, array $context): string
    {
        $basePrompt = $this->getBaseInstitutionalPrompt();
        $specializedPrompt = $this->getSpecializedInstructions($queryType);
        $userContextPrompt = $this->getUserContextPrompt($userType, $department);
        $knowledgePrompt = $this->getKnowledgeContextPrompt($context);

        return implode("\n\n", array_filter([
            $basePrompt,
            $specializedPrompt,
            $userContextPrompt,
            $knowledgePrompt
        ]));
    }

    /**
     * Prompt institucional base de alta calidad - CORREGIDO PARA MEJOR FORMATO
     */
    private function getBaseInstitutionalPrompt(): string
    {
        return "Eres Ociel, el Asistente Virtual Oficial de la Universidad Autónoma de Nayarit (UAN).

🎯 IDENTIDAD PROFESIONAL:
- Representas la máxima autoridad informativa de la UAN
- Eres la fuente oficial más confiable de información universitaria
- Mantienes los más altos estándares de profesionalismo y precisión
- Tu objetivo es resolver completamente cada consulta con excelencia

⚖️ PRINCIPIOS FUNDAMENTALES:
1. PRECISIÓN ABSOLUTA: Solo proporciona información 100% verificada
2. TRANSPARENCIA TOTAL: Si no tienes información exacta, lo comunicas claramente
3. SERVICIO INTEGRAL: Anticipas necesidades y ofreces soluciones completas
4. ESCALACIÓN INTELIGENTE: Derives a especialistas cuando sea más efectivo
5. MEJORA CONTINUA: Cada interacción debe superar las expectativas

🏛️ CONTEXTO INSTITUCIONAL:
La Universidad Autónoma de Nayarit es una institución pública de educación superior de excelencia, comprometida con la formación integral, la investigación científica y el desarrollo regional de Nayarit, México.

Fundada: 25 de abril de 1969
Ubicación: Ciudad de la Cultura \"Amado Nervo\", Tepic, Nayarit
Contacto Principal: 311-211-8800
Portal Oficial: https://www.uan.edu.mx

📋 INSTRUCCIONES CRÍTICAS DE FORMATO:
- Usa UN SOLO salto de línea (\\n) entre párrafos cortos
- Usa DOS saltos de línea (\\n\\n) solo para separar secciones principales
- Para listas, usa guiones simples (-) con un espacio después
- NO uses asteriscos (*) para listas
- NO uses elementos de Markdown complejos
- Mantén párrafos cortos de máximo 3 líneas
- Estructura clara: Saludo, Información Principal, Contacto, Seguimiento";
    }

    /**
     * Instrucciones especializadas por tipo de consulta - FORMATO MEJORADO
     */
    private function getSpecializedInstructions(string $queryType): string
    {
        $instructions = [
            'tramite_especifico' => "🎓 ESPECIALIZACIÓN EN TRÁMITES ACADÉMICOS:

Como experto en procedimientos universitarios, proporciona:

ESTRUCTURA OBLIGATORIA:
1. Confirmación del trámite solicitado
2. Requisitos principales (máximo 5 puntos)
3. Proceso paso a paso (máximo 4 pasos)
4. Información de contacto específica
5. Tiempo estimado si está disponible

FORMATO DE RESPUESTA:
- Párrafos cortos y directos
- Listas con guiones simples (-)
- UN contacto específico por respuesta
- Lenguaje claro sin tecnicismos innecesarios",

            'informacion_carrera' => "🎓 ESPECIALIZACIÓN EN OFERTA ACADÉMICA:

Como consejero académico experto, incluye:

ESTRUCTURA PARA CARRERAS:
1. Saludo y confirmación de la carrera
2. Información básica (duración, modalidad)
3. Perfil de ingreso principal
4. Campo laboral general
5. Contacto para más información

FORMATO ESPECÍFICO:
- Máximo 4 párrafos
- Información esencial solamente
- Evitar listas largas
- Contacto directo al final",

            'servicio_universitario' => "🏛️ ESPECIALIZACIÓN EN SERVICIOS:

Como guía de servicios universitarios:

ESTRUCTURA DE RESPUESTA:
1. Descripción breve del servicio
2. Ubicación y horarios básicos
3. Cómo acceder al servicio
4. Contacto específico

FORMATO:
- Información práctica y directa
- Horarios en formato simple
- Un solo contacto relevante",

            'soporte_tecnico' => "💻 ESPECIALIZACIÓN EN SOPORTE TÉCNICO:

Como especialista en sistemas universitarios:

RESPUESTA ESTRUCTURADA:
1. Confirmación del problema técnico
2. Solución básica si es simple
3. Contacto de soporte especializado
4. Horarios de atención técnica

IMPORTANTE:
- Respuestas concisas para problemas técnicos
- Derivar rápidamente a especialistas
- Incluir extensión específica de sistemas",

            'queja_problema' => "🛡️ ESPECIALIZACIÓN EN ATENCIÓN DE PROBLEMAS:

Modo de atención prioritaria:

ESTRUCTURA EMPÁTICA:
1. Reconocimiento de la situación
2. Disculpa institucional si corresponde
3. Escalación inmediata a autoridades
4. Seguimiento garantizado

TONO:
- Empático pero profesional
- Escalación rápida
- Contacto directo de supervisión",

            'consulta_academica' => "🎓 ESPECIALIZACIÓN ACADÉMICA:

Como asesor académico:

ESTRUCTURA:
1. Confirmación de la consulta académica
2. Información general disponible
3. Contacto de coordinación académica
4. Recursos adicionales si aplican

FORMATO:
- Información académica específica
- Contacto directo con coordinación"
        ];

        return $instructions[$queryType] ??
            "📋 CONSULTA GENERAL:

Proporciona información completa pero concisa.
Estructura: Saludo, Información, Contacto, Seguimiento.
Máximo 3 párrafos cortos.";
    }

    /**
     * Prompt de contexto de usuario - SIMPLIFICADO
     */
    private function getUserContextPrompt(string $userType, ?string $department): string
    {
        $userProfiles = [
            'student' => "👨‍🎓 USUARIO: ESTUDIANTE
Enfócate en: trámites estudiantiles, servicios académicos, fechas importantes.
Lenguaje: claro y directo, sin exceso de formalidad.",

            'employee' => "👩‍💼 USUARIO: EMPLEADO UNIVERSITARIO
Enfócate en: procedimientos internos, normativas, canales administrativos.
Lenguaje: técnico apropiado, información específica.",

            'public' => "🌟 USUARIO: PÚBLICO GENERAL
Enfócate en: información general, oferta educativa, servicios públicos.
Lenguaje: accesible y explicativo, contexto institucional."
        ];

        $departmentContext = $department ? "\n🏛️ DEPARTAMENTO DE INTERÉS: {$department}" : "";

        return ($userProfiles[$userType] ?? $userProfiles['public']) . $departmentContext;
    }

    /**
     * Prompt de contexto de knowledge base - SIMPLIFICADO
     */
    private function getKnowledgeContextPrompt(array $context): string
    {
        if (empty($context)) {
            return "⚠️ CONTEXTO: Sin información específica en base de conocimientos.
ACCIÓN: Proporciona información general confiable y deriva a contactos apropiados.";
        }

        $contextText = "📚 INFORMACIÓN OFICIAL DISPONIBLE:\n";
        foreach (array_slice($context, 0, 2) as $i => $item) {
            $contextText .= "FUENTE " . ($i + 1) . ": " . substr($item, 0, 200) . "...\n";
        }

        return $contextText . "\n✅ INSTRUCCIÓN: Usa ÚNICAMENTE esta información oficial. No agregues datos no verificados.";
    }

    /**
     * Construir prompt completo - FORMATO MEJORADO
     */
    private function buildFullPrompt(string $systemPrompt, string $userMessage, array $context): string
    {
        return "{$systemPrompt}

📩 CONSULTA DEL USUARIO:
\"{$userMessage}\"

🎯 FORMATO DE TU RESPUESTA:
- Saludo apropiado y breve
- Información principal (máximo 3 párrafos cortos)
- Contacto específico con teléfono/email
- Pregunta de seguimiento si corresponde

🚫 NO HAGAS:
- Listas largas con muchos elementos
- Párrafos extensos
- Información no verificada
- Múltiples contactos en una respuesta

¡Responde como Ociel, el asistente más confiable de la UAN!";
    }

    /**
     * Seleccionar modelo óptimo según tipo de consulta
     */
    private function selectOptimalModel(string $queryType): string
    {
        $modelMapping = [
            'tramite_especifico' => config('services.ollama.primary_model'),
            'informacion_carrera' => config('services.ollama.primary_model'),
            'queja_problema' => config('services.ollama.primary_model'),
            'consulta_academica' => config('services.ollama.primary_model'),
            'soporte_tecnico' => config('services.ollama.secondary_model'),
            'servicio_universitario' => config('services.ollama.secondary_model'),
            'informacion_general' => config('services.ollama.secondary_model')
        ];

        return $modelMapping[$queryType] ?? config('services.ollama.primary_model');
    }

    /**
     * Obtener temperatura óptima
     */
    private function getOptimalTemperature(string $queryType): float
    {
        $temperatureMapping = [
            'tramite_especifico' => 0.1, // Máxima precisión
            'soporte_tecnico' => 0.2,
            'informacion_carrera' => 0.2,
            'queja_problema' => 0.2,
            'consulta_academica' => 0.3,
            'servicio_universitario' => 0.3,
            'informacion_general' => 0.4
        ];

        return $temperatureMapping[$queryType] ?? 0.2;
    }

    /**
     * Obtener cantidad óptima de tokens
     */
    private function getOptimalTokens(string $queryType): int
    {
        $tokenMapping = [
            'tramite_especifico' => 600, // Respuestas concisas pero completas
            'informacion_carrera' => 500,
            'queja_problema' => 400,
            'consulta_academica' => 500,
            'soporte_tecnico' => 350,
            'servicio_universitario' => 450,
            'informacion_general' => 400
        ];

        return $tokenMapping[$queryType] ?? 450;
    }

    /**
     * Mejorar respuesta post-generación - FORMATO CORREGIDO
     */
    private function enhanceResponse(string $response, string $queryType, array $context): string
    {
        // 1. Limpiar formato problemático
        $response = $this->cleanResponseFormat($response);

        // 2. Agregar información de contacto si no está presente
        if (!$this->containsContactInfo($response)) {
            $contactInfo = $this->getRelevantContactInfo($queryType);
            $response .= "\n\n" . $contactInfo;
        }

        // 3. Agregar llamada a la acción apropiada
        $response .= "\n\n" . $this->getCallToAction($queryType);

        return $response;
    }

    /**
     * Limpiar formato de respuesta para evitar desfase
     */
    private function cleanResponseFormat(string $response): string
    {
        // Convertir asteriscos a guiones para listas
        $response = preg_replace('/^\* /m', '- ', $response);

        // Eliminar múltiples saltos de línea consecutivos
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // Asegurar formato consistente para títulos
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '**$1**', $response);

        // Limpiar espacios al final de líneas
        $response = preg_replace('/[ \t]+$/m', '', $response);

        return trim($response);
    }

    /**
     * Calcular confianza avanzada
     */
    private function calculateAdvancedConfidence(array $response, array $context, string $originalQuery): float
    {
        $confidence = 0.0;

        // Base por contexto disponible
        if (!empty($context)) {
            $confidence += 0.4;
        }

        // Por éxito de generación
        if ($response['success']) {
            $confidence += 0.3;
        }

        // Por longitud apropiada de respuesta
        $responseLength = strlen($response['response'] ?? '');
        if ($responseLength > 50 && $responseLength < 800) {
            $confidence += 0.2;
        }

        // Por presencia de información estructurada
        if ($this->hasStructuredInfo($response['response'] ?? '')) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Verificar si contiene información de contacto
     */
    private function containsContactInfo(string $text): bool
    {
        return preg_match('/\b311-211-8800\b|\b\w+@\w+\.edu\.mx\b|ext\.\s*\d+/i', $text);
    }

    /**
     * Verificar si tiene información estructurada
     */
    private function hasStructuredInfo(string $text): bool
    {
        $patterns = [
            '/requisitos?/i',
            '/pasos?/i',
            '/procedimiento/i',
            '/contacto/i'
        ];

        $matches = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches++;
            }
        }

        return $matches >= 2;
    }

    /**
     * Obtener información de contacto relevante
     */
    private function getRelevantContactInfo(string $queryType): string
    {
        $contactMapping = [
            'tramite_especifico' => "📞 DGSA: 311-211-8800 ext. 8530",
            'soporte_tecnico' => "💻 DGS: 311-211-8800 ext. 8540",
            'informacion_carrera' => "📞 Información general: 311-211-8800",
            'servicio_universitario' => "📞 UAN: 311-211-8800"
        ];

        return $contactMapping[$queryType] ?? "📞 UAN: 311-211-8800";
    }

    /**
     * Obtener llamada a la acción apropiada
     */
    private function getCallToAction(string $queryType): string
    {
        $ctaMapping = [
            'tramite_especifico' => "¿Necesitas información sobre algún requisito específico?",
            'informacion_carrera' => "¿Te interesa conocer más sobre alguna carrera en particular?",
            'soporte_tecnico' => "¿El problema persiste o necesitas ayuda con algo más?",
            'queja_problema' => "¿Hay algo más en lo que pueda asistirte?",
            'servicio_universitario' => "¿Quieres información sobre algún otro servicio?"
        ];

        return $ctaMapping[$queryType] ?? "¿En qué más puedo ayudarte?";
    }
}
