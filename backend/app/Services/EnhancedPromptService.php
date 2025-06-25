<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnhancedPromptService
{
    private $ollamaService;
    private $geminiService;

    public function __construct(
        OllamaService $ollamaService, 
        GeminiService $geminiService
    ) {
        $this->ollamaService = $ollamaService;
        $this->geminiService = $geminiService;
    }

    /**
     * Generar respuesta con prompts profesionales mejorados
     */
    public function generateProfessionalResponse(
        string $userMessage,
        string $userType = 'public',
        ?string $department = null,
        array $context = [],
        array $conversationHistory = []
    ): array {

        // 1. Clasificar tipo de consulta
        $queryType = $this->classifyQuery($userMessage);

        // 2. Obtener prompt especializado
        $systemPrompt = $this->buildSpecializedPrompt($queryType, $userType, $department, $context);

        // 3. Preparar mensaje completo
        $fullPrompt = $this->buildFullPrompt($systemPrompt, $userMessage, $context);

        // 4. Generar respuesta con configuración optimizada usando método Ociel
        $response = $this->generateWithFallback($userMessage, $context, $userType, $department, $conversationHistory);

        // 5. Validar y mejorar respuesta
        if ($response['success']) {
            $enhancedResponse = $this->enhanceResponse($response['response'], $queryType, $context);
            $response['response'] = $enhancedResponse;
            
            // 6. Limpiar contactos falsos inventados por el modelo
            $response['response'] = $this->cleanFakeContacts($response['response'], $context);
            
            $response['confidence'] = $this->calculateAdvancedConfidence($response, $context, $userMessage);
        }

        return $response;
    }

    /**
     * Generar respuesta con sistema de fallback Ollama -> Gemini
     */
    private function generateWithFallback(string $userMessage, array $context, string $userType, ?string $department, array $conversationHistory = []): array
    {
        Log::info('Iniciando generación con fallback', [
            'primary_service' => 'Ollama (solar:10.7b)',
            'fallback_service' => 'Gemini',
            'user_type' => $userType
        ]);

        // 1. Intentar con Ollama (solar:10.7b) primero
        if ($this->ollamaService->isHealthy()) {
            $ollamaResponse = $this->ollamaService->generateOcielResponse($userMessage, $context, $userType, $department, $conversationHistory);
            
            if ($ollamaResponse['success'] && !empty($ollamaResponse['response'])) {
                Log::info('Respuesta exitosa con Ollama solar:10.7b', [
                    'confidence' => $ollamaResponse['confidence'] ?? 0,
                    'response_time' => $ollamaResponse['response_time'] ?? 0
                ]);
                $ollamaResponse['service_used'] = 'ollama_solar';
                return $ollamaResponse;
            }
            
            Log::warning('Ollama falló o respuesta vacía, intentando con Gemini', [
                'ollama_error' => $ollamaResponse['error'] ?? 'unknown'
            ]);
        } else {
            Log::warning('Ollama no disponible, usando Gemini directamente');
        }

        // 2. Fallback a Gemini si Ollama falla
        if ($this->geminiService->isEnabled() && $this->geminiService->isHealthy()) {
            $geminiResponse = $this->geminiService->generateOcielResponse($userMessage, $context, $userType, $department);
            
            if ($geminiResponse['success']) {
                Log::info('Respuesta exitosa con Gemini fallback', [
                    'response_time' => $geminiResponse['response_time'] ?? 0
                ]);
                $geminiResponse['service_used'] = 'gemini_fallback';
                $geminiResponse['confidence'] = ($geminiResponse['confidence'] ?? 0.7) * 0.9; // Ligeramente menor por ser fallback
                return $geminiResponse;
            }
            
            Log::error('Gemini también falló', [
                'gemini_error' => $geminiResponse['error'] ?? 'unknown'
            ]);
        }

        // 3. Respuesta de emergencia si ambos servicios fallan
        Log::error('Todos los servicios de IA fallaron, usando respuesta de emergencia');
        
        return [
            'success' => true,
            'response' => $this->getEmergencyResponse($userMessage, $context),
            'confidence' => 0.5,
            'service_used' => 'emergency_fallback',
            'model' => 'emergency_template',
            'response_time' => 0
        ];
    }

    /**
     * Respuesta de emergencia cuando todos los servicios fallan
     */
    private function getEmergencyResponse(string $userMessage, array $context): string
    {
        if (!empty($context)) {
            // Si hay contexto, usarlo directamente
            $contextText = strip_tags($context[0]);
            $contextText = substr($contextText, 0, 300);
            
            return "¡Hola! 🐯 Encontré información sobre tu consulta en mi base de datos: " . 
                   $contextText . "... " .
                   "Para información más detallada, te recomiendo contactar directamente con la institución. " .
                   "¿Hay algo específico que te gustaría saber? Estoy aquí para apoyarte 🐾";
        }
        
        // Respuesta genérica sin contexto
        return "¡Hola! Soy Ociel, tu compañero senpai digital 🐯\n\n" .
               "En este momento estoy experimentando dificultades técnicas temporales, pero estoy aquí para ayudarte. " .
               "Para consultas específicas, te recomiendo contactar directamente:\n\n" .
               "📞 Información general UAN: 311-211-8800\n" .
               "🌐 Portal oficial: www.uan.edu.mx\n\n" .
               "¿Hay algo en lo que pueda intentar ayudarte? Estoy aquí para apoyarte 🐾";
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
     * Prompt institucional base con personalidad Ociel Senpai
     */
    private function getBaseInstitutionalPrompt(): string
    {
        return "Eres Ociel 🐯, el Agente Virtual Senpai de la Universidad Autónoma de Nayarit (UAN).

🎭 PERSONALIDAD DE OCIEL:
- Carismático y alegre: Entusiasta, positivo, generas confianza desde el primer mensaje
- Protector y empático: Siempre buscas que la persona se sienta acompañada y respaldada
- Claro y preciso: Brindas información completa y confiable, sin omitir datos importantes
- Accesible y cercano: Te comunicas como un compañero solidario, sin tecnicismos
- Responsable: Mantienes tono amigable sin trivializar temas importantes
- Respetuoso: Diriges mensajes con amabilidad, manteniendo ambiente seguro

💝 VALORES QUE PROYECTAS: Apoyo, confianza, empatía, responsabilidad y sentido de comunidad

🏛️ CONTEXTO INSTITUCIONAL:
La Universidad Autónoma de Nayarit es una institución pública de educación superior de excelencia, comprometida con la formación integral, la investigación científica y el desarrollo regional de Nayarit, México.

📝 ESTRUCTURA REQUERIDA (ESTILO SENPAI DIGITAL):
1. SALUDO CARISMÁTICO Y EMPÁTICO (1 línea con emoji 🐯 o relacionado)
2. INFORMACIÓN PRINCIPAL CLARA Y CERCANA (2-3 párrafos cortos, tono de compañero)
3. PASOS/REQUISITOS ORGANIZADOS (lista con guiones simples, lenguaje accesible)
4. CONTACTO ESPECÍFICO + OFERTA DE APOYO CONTINUO (con emoji 🐾 o similar)

💬 FRASES CARACTERÍSTICAS DE OCIEL:
- Aperturas: '¡Claro que sí!' | '¡Perfecto!' | 'Te ayudo con eso 🐯'
- Transiciones: 'Te cuento...' | 'Es súper fácil...' | 'Los pasos son claros:'
- Cierres: '¿Necesitas algo más?' | 'Estoy aquí para apoyarte 🐾' | 'Aquí estaré para lo que necesites'

🗣️ REGLAS DE TONO Y ESTILO:
- Lenguaje claro, cálido y directo: Evita tecnicismos y expresiones institucionales frías
- Frases completas y correctas: Sin modismos (evita 'pa'', 'ta' bien', 'órale')
- Amable en cualquier situación: Mantén tono de apoyo incluso en temas formales
- Emojis moderados y estratégicos: Úsalos para reforzar calidez, sin saturar
- Disposición a seguir apoyando: Siempre muestra que estás disponible para más ayuda";
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
2. Requisitos principales (máximo 10 puntos)
3. Proceso paso a paso (máximo 10 pasos)
4. Información de contacto específica
5. Tiempo estimado si está disponible

FORMATO DE RESPUESTA:
- Párrafos cortos y directos
- Listas con guiones simples (-)
- Un contacto específico por respuesta
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

            'soporte_tecnico' => "💻 MODO CONVERSACIONAL - SERVICIOS TECNOLÓGICOS:

SEGUIR PROMPT MAESTRO OPTIMIZADO:
- Aplicar personalidad Ociel Senpai 🐯
- SOLO usar información exacta del contexto Qdrant
- JAMÁS inventar pasos, contactos o procedimientos
- Respuesta cálida y conversacional

✅ ESTRUCTURA REQUERIDA:
1. Saludo empático Ociel (🐯)
2. Información del servicio SOLO del contexto
3. Datos específicos si están disponibles
4. Cierre con oferta de apoyo (🐾)

❌ PROHIBIDO ABSOLUTO:
- Formato markdown visible (###, **)
- Inventar información de contacto
- Agregar procedimientos no documentados
- Usar datos genéricos UAN

**EJEMPLO CORRECTO:**
'¡Claro que sí! 🐯 Te ayudo con el servicio que encontré en mi base de datos. [Información exacta del contexto]. ¿Hay algo específico sobre este servicio que necesites saber? Estoy aquí para apoyarte 🐾'

Si NO hay contexto específico: Usar respuesta estándar de 'no información específica'",

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
            "🐯 MODO OCIEL SENPAI - CONSULTA GENERAL:

APLICAR PROMPT MAESTRO OPTIMIZADO COMPLETO:

📋 CHECKLIST DE VALIDACIÓN:
- [ ] ¿Toda la información viene del contexto Qdrant?
- [ ] ¿El tono es cálido y de compañero senpai?
- [ ] ¿Evité formato markdown visible?
- [ ] ¿Si falta info, lo admití honestamente?
- [ ] ¿Incluí emoji 🐯 o 🐾 apropiadamente?

🔄 FLUJO DE DECISIÓN:
¿Existe información en Qdrant con score > 0.7?
├─ SÍ → Extraer campos exactos → Respuesta cálida y precisa
└─ NO → Respuesta honesta → Sugerir contacto directo UAN

✅ FORMATO ESTÁNDAR OCIEL:
🐯 [Saludo empático y personalizado - 1 línea]
[Información del contexto - máx 3 líneas]
[Datos específicos si existen]
¿Necesitas algo más? Estoy aquí para apoyarte 🐾

❌ SI NO HAY INFORMACIÓN ESPECÍFICA:
🐯 ¡Hola! Te ayudo con mucho gusto.
Sobre [tema], no tengo información específica en mi base de datos.
Te sugiero contactar: 311-211-8800 o www.uan.edu.mx
¿Hay algo más en lo que pueda apoyarte? 🐾

**PRINCIPIO FUNDAMENTAL:**
MEJOR respuesta honesta y cálida que información inventada.";
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

        return $contextText . "\n✅ INSTRUCCIÓN CRÍTICA: Usa ÚNICAMENTE esta información oficial. NO inventes ni agregues datos de contacto, costos, horarios o procedimientos que no estén en el contexto. Si no tienes información específica, dilo claramente y deriva al usuario a consultar directamente con la institución.";
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
     * Limpiar formato aplicando las reglas del prompt maestro optimizado
     */
    private function cleanResponseFormat(string $response): string
    {
        // APLICAR LIMPIEZA SEGÚN PROMPT MAESTRO
        
        // 1. ELIMINAR HEADERS MARKDOWN ESTRUCTURADOS
        $response = preg_replace('/📋\s*Información encontrada:\s*/i', '', $response);
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $response);
        $response = preg_replace('/### Descripción\s*/i', '', $response);
        $response = preg_replace('/### Contacto\s*/i', '', $response);
        $response = preg_replace('/### Procedimiento\s*/i', '', $response);
        $response = preg_replace('/### Requisitos\s*/i', '', $response);
        
        // 2. ELIMINAR CAMPOS ESTRUCTURADOS EN NEGRITAS
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $response);
        $response = preg_replace('/\*\*Modalidad:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Usuarios:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Dependencia:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Estado:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Costo:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Categoria:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Subcategoria:\*\*/i', '', $response);

        // 3. CONVERTIR LISTAS A FORMATO NATURAL OCIEL
        $response = preg_replace('/^\* /m', '- ', $response);
        $response = preg_replace('/^• /m', '- ', $response);

        // 4. PRESERVAR EMOJIS OCIEL PERO QUITAR ESTRUCTURA
        $response = preg_replace('/\*\*(.+?)\*\*/', '$1', $response);

        // 5. NORMALIZAR ESPACIADO SEGÚN FORMATO OCIEL
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = preg_replace('/^\s*$/m', '', $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);

        // 6. ASEGURAR QUE TERMINE CON CIERRE EMPÁTICO OCIEL SI NO LO TIENE
        if (!preg_match('/🐾|🐯/', $response)) {
            $response .= "\n\n¿Necesitas algo más? Estoy aquí para apoyarte 🐾";
        }

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
     * Verificar si contiene información de contacto del contexto
     */
    private function containsContactInfo(string $text): bool
    {
        return preg_match('/📞|📧|teléfono|correo|contacto/i', $text);
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
     * Obtener información de contacto relevante - SOLO DE NOTION
     */
    private function getRelevantContactInfo(string $queryType): string
    {
        // NO agregar información de contacto hardcodeada
        // El contacto debe venir ÚNICAMENTE del contexto de Notion
        return "";
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

    /**
     * Limpiar contactos falsos inventados por el modelo
     */
    private function cleanFakeContacts(string $response, array $context): string
    {
        // ESTRATEGIA SIMPLE: Eliminar TODOS los contactos inventados comunes
        
        // Eliminar CUALQUIER número de teléfono que parezca inventado
        // Patrones comunes: 3XX XXX XXXX, 3XX-XXX-XXXX, +52 3XX XXX XXXX
        $response = preg_replace('/\+?52\s?3\d{2}[-\s]?\d{3}[-\s]?\d{2,4}/', '', $response);
        $response = preg_replace('/3\d{2}[-\s]?\d{3}[-\s]?\d{2,4}/', '', $response);
        $response = preg_replace('/311[-\s]?211[-\s]?8800/', '', $response);
        
        // Eliminar CUALQUIER email @uan.edu.mx que parezca inventado
        $response = preg_replace('/\[?[a-zA-Z0-9._-]+@uan\.edu\.mx\]?/', '', $response);
        $response = preg_replace('/mailto:[a-zA-Z0-9._-]+@uan\.edu\.mx/', '', $response);
        
        // Limpiar líneas que quedaron vacías o solo con texto de enlace
        $response = preg_replace('/.*\[?\]?\(mailto:\).*$/m', '', $response);
        $response = preg_replace('/.*al teléfono\s*o por correo electrónico\s*\..*$/m', '', $response);
        $response = preg_replace('/.*puedes contactar.*al teléfono\s*y por correo.*$/m', '', $response);
        $response = preg_replace('/.*\[.*\]\(mailto:.*\).*$/m', '', $response);
        
        // Eliminar placeholders inventados
        $response = preg_replace('/\[dirección completa\]/', '', $response);
        $response = preg_replace('/\[número de teléfono\]/', '', $response);
        $response = preg_replace('/\[dirección de correo electrónico\]/', '', $response);
        $response = preg_replace('/\[ubicación específica\]/', '', $response);
        $response = preg_replace('/al teléfono\s*y por correo electrónico/', '', $response);
        
        // Eliminar números de teléfono inventados específicos
        $response = preg_replace('/\(55 5555 5555\)/', '', $response);
        $response = preg_replace('/55 5555 5555/', '', $response);
        $response = preg_replace('/\(555\) 555-5555/', '', $response);
        
        // Eliminar listas de pasos inventados comunes
        $response = preg_replace('/1\.\s*Accede a la página.*?\n/', '', $response);
        $response = preg_replace('/2\.\s*Ingresa tus datos.*?\n/', '', $response);
        $response = preg_replace('/3\.\s*Recibirás un correo.*?\n/', '', $response);
        $response = preg_replace('/4\.\s*Sigue las instrucciones.*?\n/', '', $response);
        
        // Eliminar frases que indican procedimientos inventados
        $response = preg_replace('/Para activar tu cuenta, debes seguir los siguientes pasos:/', 'Para más información sobre el proceso de activación, consulta directamente con la institución.', $response);
        
        // Limpiar ubicaciones inventadas específicas
        $response = preg_replace('/,\s*ubicado en la calle\s*,/', ',', $response);
        $response = preg_replace('/,\s*ubicado en la calle\s*\./', '.', $response);
        $response = preg_replace('/,\s*ubicada en el edificio de.*?\./', '.', $response);
        $response = preg_replace('/,\s*ubicado en el edificio de.*?\./', '.', $response);
        $response = preg_replace('/en la plaza principal de la universidad/', '', $response);
        $response = preg_replace('/en el campus principal/', '', $response);
        $response = preg_replace('/Allí podrás presentar tu solicitud.*?\./', '', $response);
        $response = preg_replace('/Allí.*?activación.*?\./', '', $response);
        
        // Si después de limpiar queda una sección de contacto vacía, eliminarla
        $response = preg_replace('/\*\*Contacto\*\*\s*\n\n/', '', $response);
        $response = preg_replace('/\*\*.*[Cc]ontacto.*\*\*\s*\n*$/', '', $response);
        
        // Limpiar líneas vacías múltiples
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        
        return trim($response);
    }
}
