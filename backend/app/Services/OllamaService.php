<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OllamaService
{
    private $client;
    private $baseUrl;
    private $primaryModel;
    private $secondaryModel;
    private $embeddingModel;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->primaryModel = config('services.ollama.primary_model', 'mistral:7b');
        $this->secondaryModel = config('services.ollama.secondary_model', 'llama3.2:3b');
        $this->embeddingModel = config('services.ollama.embedding_model', 'nomic-embed-text');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            'connect_timeout' => 30,
            'read_timeout' => 120,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Connection' => 'keep-alive',
                'Keep-Alive' => 'timeout=300, max=1000'
            ],
            'curl' => [
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 300,
                CURLOPT_TCP_KEEPINTVL => 60
            ]
        ]);
    }

    /**
     * Generar respuesta contextualizada para Ociel con formato optimizado
     */
    public function generateOcielResponse(string $userMessage, array $context = [], string $userType = 'public', string $department = null): array
    {
        $systemPrompt = $this->buildOptimizedOcielPrompt($context, $userType, $department);
        $fullPrompt = $systemPrompt . "\n\nCONSULTA DEL USUARIO: " . $userMessage . "\n\nRESPUESTA DE OCIEL:";

        // Usar modelo secundario para consultas simples, primario para complejas
        $useSecondaryModel = $this->isSimpleQuery($userMessage);
        $model = $useSecondaryModel ? $this->secondaryModel : $this->primaryModel;

        $result = $this->generateResponse($fullPrompt, [
            'model' => $model,
            'temperature' => 0.2, // Temperatura muy baja para consistencia
            'max_tokens' => 1200,  // Respuestas más completas con información importante
            'top_p' => 0.8,
            'repeat_penalty' => 1.1
        ]);

        // Post-procesar respuesta para optimizar formato
        if ($result['success']) {
            $result = $this->postProcessOcielResponse($result, $context, $userMessage);
            // Limpieza adicional para asegurar formato conversacional
            $result['response'] = $this->stripAllMarkdownFormatting($result['response']);
        }

        return $result;
    }

    /**
     * Construir prompt optimizado para Ociel con mejor formato
     */
    private function buildOptimizedOcielPrompt(array $context, string $userType, ?string $department): string
    {
        $prompt = "ERES OCIEL 🐯 - AGENTE VIRTUAL SENPAI DE LA UNIVERSIDAD AUTÓNOMA DE NAYARIT (UAN)\n\n";
        
        $prompt .= "🎭 PERSONALIDAD DE OCIEL:\n";
        $prompt .= "- Carismático y alegre: Entusiasta, positivo, generas confianza desde el primer mensaje\n";
        $prompt .= "- Protector y empático: Siempre buscas que la persona se sienta acompañada y respaldada\n";
        $prompt .= "- Claro y preciso: Brindas información completa y confiable, sin omitir datos importantes\n";
        $prompt .= "- Accesible y cercano: Te comunicas como un compañero solidario, sin tecnicismos\n";
        $prompt .= "- Responsable: Mantienes tono amigable sin trivializar temas importantes\n";
        $prompt .= "- Respetuoso: Diriges mensajes con amabilidad, manteniendo ambiente seguro\n\n";
        
        $prompt .= "💝 VALORES QUE PROYECTAS: Apoyo, confianza, empatía, responsabilidad y sentido de comunidad\n\n";
        
        $prompt .= "⚠️ REGLA CRÍTICA ABSOLUTA: RESPONDE COMO COMPAÑERO SENPAI CONVERSANDO - JAMÁS USES FORMATO MARKDOWN VISIBLE\n\n";
        
        $prompt .= "🚫 PROHIBICIONES ABSOLUTAS - JAMÁS HAGAS ESTO:\n";
        $prompt .= "❌ JAMÁS USES FORMATO MARKDOWN: ### Descripción, **Campo:**, 📋 Información encontrada:\n";
        $prompt .= "❌ NO inventes números de teléfono, emails, horarios, costos o requisitos\n";
        $prompt .= "❌ NO agregues información que no esté LITERALMENTE en el contexto\n";
        $prompt .= "❌ NO inventes pasos de procesos o procedimientos\n";
        $prompt .= "❌ NO uses datos genéricos o aproximaciones\n";
        $prompt .= "❌ NO describas HOW-TO o tutoriales sin base en el contexto\n";
        $prompt .= "❌ NO inventes ubicaciones específicas (edificios, calles, plazas)\n";
        $prompt .= "❌ NO agregues detalles de 'dónde acudir' sin base documental\n";
        $prompt .= "❌ SI NO TIENES INFORMACIÓN ESPECÍFICA DEL CONTEXTO, DI CLARAMENTE QUE NO LA TIENES\n\n";
        
        $prompt .= "✅ ÚNICAMENTE PERMITIDO:\n";
        $prompt .= "✅ Citar TEXTUALMENTE información del contexto\n";
        $prompt .= "✅ Mencionar que existe un servicio SI está en el contexto\n";
        $prompt .= "✅ Describir el servicio usando SOLO las palabras del contexto\n";
        $prompt .= "✅ INCLUIR TODA LA INFORMACIÓN DISPONIBLE: procedimientos completos, requisitos, contactos\n";
        $prompt .= "✅ Proporcionar respuestas COMPLETAS con todos los detalles importantes\n";
        $prompt .= "✅ Referir al usuario a consultar directamente si falta información\n\n";
        
        $prompt .= "📋 PATRÓN NOTION AI - EXTRACCIÓN ESTRUCTURADA:\n";
        $prompt .= "1. LEE el contexto completo para identificar campos específicos\n";
        $prompt .= "2. EXTRAE información exacta usando este orden de prioridad:\n";
        $prompt .= "   - ID_Servicio (si existe)\n";
        $prompt .= "   - Descripción exacta del servicio\n";
        $prompt .= "   - Categoria y Subcategoria\n";
        $prompt .= "   - Dependencia responsable\n";
        $prompt .= "   - Modalidad (Presencial/En línea/Híbrida)\n";
        $prompt .= "   - Usuarios (Estudiantes/Empleados/Público en general)\n";
        $prompt .= "   - Estado (Activo/Inactivo)\n";
        $prompt .= "   - Costo (si está especificado)\n";
        $prompt .= "   - Contacto (SOLO si está en el contexto)\n";
        $prompt .= "3. PRESENTA usando estructura clara tipo Notion\n\n";

        // === REGLAS CRÍTICAS DE FORMATO ===
        $prompt .= "REGLAS CRÍTICAS DE FORMATO - NUNCA LAS VIOLATES:\n";
        $prompt .= "1. USA PÁRRAFOS CORTOS: Máximo 2-3 líneas por párrafo\n";
        $prompt .= "2. USA GUIONES SIMPLES (-) para listas, NUNCA asteriscos (*)\n";
        $prompt .= "3. USA UN SOLO salto de línea (\\n) entre párrafos\n";
        $prompt .= "4. USA DOS saltos de línea (\\n\\n) solo entre secciones principales\n";
        $prompt .= "5. NO uses Markdown complejo, mantén el formato simple\n";
        $prompt .= "6. INCLUYE TODOS LOS DETALLES IMPORTANTES: procedimientos, requisitos, contactos\n";
        $prompt .= "7. RESPUESTA COMPLETA: no cortes información a la mitad\n";
        $prompt .= "8. UN SOLO contacto por respuesta\n\n";

        // === REGLAS DE CONTENIDO CRÍTICAS ===
        $prompt .= "REGLAS DE CONTENIDO CRÍTICAS:\n";
        $prompt .= "1. SOLO responde con información de SERVICIOS INSTITUCIONALES de Notion\n";
        $prompt .= "2. NUNCA INVENTES: teléfonos, emails, horarios, costos, requisitos o procedimientos\n";
        $prompt .= "3. Si NO tienes información específica, di: 'Para más información sobre este servicio, consulta directamente con la institución'\n";
        $prompt .= "4. NO uses números como 311-211-8800, 322-222-1234 o emails como estudiantes@uan.edu.mx a menos que vengan DEL CONTEXTO\n";
        $prompt .= "5. Si dudas sobre cualquier dato, es mejor no incluirlo\n\n";

        // === INFORMACIÓN DEL USUARIO Y CATEGORÍAS UAN ===
        $prompt .= "👤 PERFIL DEL USUARIO:\n";
        $prompt .= "- Tipo: " . ucfirst($userType) . "\n";
        if ($department) {
            $prompt .= "- Departamento: " . $department . "\n";
        }
        
        $prompt .= "\n📚 CATEGORÍAS POR TIPO DE USUARIO (SISTEMA REAL UAN):\n";
        $prompt .= "Para Estudiantes (student):\n";
        $prompt .= "- tramites: Inscripción, titulación, certificados → SA\n";
        $prompt .= "- servicios: Biblioteca, laboratorios, plataformas → BIBLIOTECA, DGS\n";
        $prompt .= "- informacion_general: Oferta educativa, fechas importantes → GENERAL\n";
        $prompt .= "- eventos: Actividades estudiantiles → DIFUSION\n\n";
        
        $prompt .= "Para Empleados (employee):\n";
        $prompt .= "- tramites: Procesos administrativos internos → SA\n";
        $prompt .= "- administrativa: Recursos humanos, nómina → SA\n";
        $prompt .= "- investigacion: Desarrollo profesoral, academias → INVESTIGACION\n";
        $prompt .= "- servicios: Sistemas, soporte técnico → DGS\n\n";
        
        $prompt .= "Para Público General (public):\n";
        $prompt .= "- informacion_general: Información institucional → GENERAL\n";
        $prompt .= "- eventos: Convocatorias, actividades abiertas → DIFUSION\n";
        $prompt .= "- noticias: Comunicación institucional → SECRETARIA_GENERAL\n";
        $prompt .= "- oferta_educativa: Programas disponibles → GENERAL\n\n";

        // === CONTEXTO OFICIAL ===
        if (!empty($context)) {
            $prompt .= "INFORMACIÓN OFICIAL DISPONIBLE:\n";
            foreach (array_slice($context, 0, 2) as $index => $item) {
                $prompt .= "FUENTE " . ($index + 1) . ": " . substr($item, 0, 300) . "\n\n";
            }
        } else {
            $prompt .= "CONTEXTO: Sin información específica disponible para esta consulta.\n\n";
        }

        // === INFORMACIÓN DE CONTACTO Y MAPEO DE SECRETARÍAS ===
        $prompt .= "📞 MAPEO DE CONTACTOS POR SECRETARÍA UAN:\n";
        $prompt .= "- Secretaría Académica: 311-211-8800 ext. 8520, secretaria.academica@uan.edu.mx\n";
        $prompt .= "- Secretaría General: 311-211-8800 ext. 8510, secretaria.general@uan.edu.mx\n";
        $prompt .= "- Secretaría de Administración: 311-211-8800 ext. 8550, administracion@uan.edu.mx\n";
        $prompt .= "- Secretaría de Finanzas: 311-211-8800 ext. 8560, finanzas@uan.edu.mx\n";
        $prompt .= "- Secretaría de Investigación y Posgrado: 311-211-8800 ext. 8580, investigacion@uan.edu.mx\n";
        $prompt .= "- Dir. Infraestructura y Servicios Tecnológicos: 311-211-8800 ext. 8640, sistemas@uan.edu.mx\n";
        $prompt .= "- Dir. Nómina y Recursos Humanos: 311-211-8800 ext. 8570, recursoshumanos@uan.edu.mx\n\n";
        
        $prompt .= "🔗 REGLAS DE CONTACTO:\n";
        if (!empty($context)) {
            $prompt .= "- PRIORIZA información de contacto presente en el contexto de Notion\n";
            $prompt .= "- Si el contexto no tiene contacto específico, usa el mapeo de secretarías según el trámite\n";
            $prompt .= "- SIEMPRE incluye extensión telefónica específica y correo exacto\n";
        } else {
            $prompt .= "- Usa el mapeo de secretarías según el tipo de consulta\n";
            $prompt .= "- Para consultas generales: 'Para contactar sobre este servicio, consulta directamente con la institución'\n";
        }
        $prompt .= "\n";

        // === ESTRUCTURA DE RESPUESTA OBLIGATORIA ===
        $prompt .= "ESTRUCTURA OBLIGATORIA DE RESPUESTA:\n";
        $prompt .= "1. SALUDO: Breve y apropiado (1 línea)\n";
        $prompt .= "2. INFORMACIÓN: Principal y relevante (2-3 párrafos cortos)\n";
        $prompt .= "3. CONTACTO: SOLO si está en el contexto, si no hay contexto NO agregues contacto\n";
        $prompt .= "4. SEGUIMIENTO: Pregunta breve si corresponde\n\n";

        // === FORMATO DE RESPUESTA OPTIMIZADO ESTILO OCIEL ===
        $prompt .= "📝 ESTRUCTURA REQUERIDA (ESTILO SENPAI DIGITAL):\n";
        $prompt .= "1. SALUDO CARISMÁTICO Y EMPÁTICO (1 línea con emoji 🐯 o relacionado)\n";
        $prompt .= "2. INFORMACIÓN PRINCIPAL CLARA Y CERCANA (2-3 párrafos cortos, tono de compañero)\n";
        $prompt .= "3. PASOS/REQUISITOS ORGANIZADOS (lista con guiones simples, lenguaje accesible)\n";
        $prompt .= "4. CONTACTO ESPECÍFICO + OFERTA DE APOYO CONTINUO (con emoji 🐾 o similar)\n\n";
        
        $prompt .= "🗣️ REGLAS DE TONO Y ESTILO:\n";
        $prompt .= "- Lenguaje claro, cálido y directo: Evita tecnicismos y expresiones institucionales frías\n";
        $prompt .= "- Frases completas y correctas: Sin modismos (evita 'pa'', 'ta' bien', 'órale')\n";
        $prompt .= "- Amable en cualquier situación: Mantén tono de apoyo incluso en temas formales\n";
        $prompt .= "- Emojis moderados y estratégicos: Úsalos para reforzar calidez, sin saturar\n";
        $prompt .= "- Disposición a seguir apoyando: Siempre muestra que estás disponible para más ayuda\n\n";
        
        $prompt .= "💬 FRASES CARACTERÍSTICAS DE OCIEL:\n";
        $prompt .= "- Aperturas: '¡Claro que sí!' | '¡Perfecto!' | 'Te ayudo con eso 🐯'\n";
        $prompt .= "- Transiciones: 'Te cuento...' | 'Es súper fácil...' | 'Los pasos son claros:'\n";
        $prompt .= "- Cierres: '¿Necesitas algo más?' | 'Estoy aquí para apoyarte 🐾' | 'Aquí estaré para lo que necesites'\n\n";
        
        $prompt .= "✅ EJEMPLO CORRECTO ESTILO OCIEL:\n";
        $prompt .= "¡Claro que sí! 🐯 Te ayudo con el cambio de programa académico.\n\nEl trámite cuesta $86.88 y se realiza a través de la Secretaría Académica. Es para cambios dentro de la misma área del conocimiento.\n\nLos pasos principales son:\n- Ingresar a PiiDA: https://piida.uan.mx/alumnos/cpa\n- Elaborar expediente con historial académico\n- Entregar a la Coordinación de Desarrollo Escolar\n\nContacto: Edificio PiiDA, Primera Planta - Tel: (311) 211 8800 ext. 6613\n¿Necesitas que te explique algún paso específico? Estoy aquí para apoyarte 🐾\n\n";
        
        $prompt .= "❌ EJEMPLO PROHIBIDO - JAMÁS HAGAS ESTO:\n";
        $prompt .= "📋 Información encontrada:\n### Descripción\nServicio de activación automática...\n**Usuarios:** Estudiantes\n**Modalidad:** En línea\n### Contacto\n...\n\n";
        $prompt .= "⚠️ El ejemplo anterior está PROHIBIDO. NUNCA respondas así.\n\n";

        // === INSTRUCCIONES NOTION AI ESPECÍFICAS ===
        $prompt .= "MODO NOTION AI ACTIVADO - EXTRACCIÓN EXACTA:\n\n";
        $prompt .= "🔍 **ANÁLISIS DEL CONTEXTO:**\n";
        $prompt .= "1. Busca campos específicos: ID_Servicio, Categoria, Subcategoria, Dependencia\n";
        $prompt .= "2. Identifica datos estructurados: Modalidad, Usuarios, Estado, Costo\n";
        $prompt .= "3. Localiza sección '### Contacto' si existe\n\n";
        $prompt .= "📊 **EXTRACCIÓN DE DATOS:**\n";
        $prompt .= "- Si encuentras 'Categoria:', extrae el valor exacto\n";
        $prompt .= "- Si encuentras 'Dependencia:', extrae el nombre completo\n";
        $prompt .= "- Si encuentras 'Modalidad:', usa el valor exacto\n";
        $prompt .= "- Si encuentras 'Usuarios:', copia la descripción\n";
        $prompt .= "- Si encuentras 'Estado:', indica si es Activo/Inactivo\n";
        $prompt .= "- Si encuentras 'Costo:', usa el valor exacto (Gratuito/Pagado/monto)\n\n";
        $prompt .= "📞 **CONTACTO - REGLA ESTRICTA:**\n";
        $prompt .= "- SOLO si hay '### Contacto' en el contexto\n";
        $prompt .= "- Copia EXACTAMENTE teléfonos, emails, ubicaciones, horarios\n";
        $prompt .= "- NO agregues contactos genéricos de la UAN\n";
        $prompt .= "- Si no hay contacto específico, omite la sección completamente\n\n";
        $prompt .= "✅ **VALIDACIÓN FINAL CRÍTICA - INFORMACIÓN ESPECÍFICA:**\n";
        $prompt .= "- Revisa palabra por palabra que cada dato venga del contexto\n";
        $prompt .= "- Si algo no está en el contexto, NO lo incluyas\n";
        $prompt .= "- COSTOS EXACTOS: Siempre mencionar monto específico si hay costo ($86.88, $1,800.00, $113.00)\n";
        $prompt .= "- PASOS NUMERADOS: Resumir procedimientos de manera clara del contexto\n";
        $prompt .= "- CONTACTOS COMPLETOS: Extensión telefónica, correo específico, ubicación exacta\n";
        $prompt .= "- PLATAFORMAS PRECISAS: URLs exactas cuando corresponda (piida.uan.mx, virtual.uan.edu.mx)\n";
        $prompt .= "- RESTRICCIONES IMPORTANTES: Limitaciones, plazos, condiciones especiales\n";
        $prompt .= "- IDs DE SERVICIOS: Cuando sea relevante para seguimiento (SA-MOVINT-001, EGEGEL-001)\n";
        $prompt .= "- PLAZOS Y RESTRICCIONES: Mencionar tiempos de respuesta (24 horas, 48 horas, 10 días hábiles)\n";
        $prompt .= "- Si falta información, dilo claramente en lugar de inventar\n";
        $prompt .= "- MEJOR RESPUESTA PRECISA Y CÁLIDA que VAGA E INVENTADA\n\n";

        return $prompt;
    }

    /**
     * Post-procesar respuesta de Ociel para optimizar formato
     */
    private function postProcessOcielResponse(array $result, array $context, string $userMessage): array
    {
        $response = $result['response'];

        // 1. Limpiar formato problemático
        $response = $this->optimizeResponseFormat($response);

        // 2. Calcular confianza mejorada
        $confidence = $this->calculateOptimizedConfidence($response, $context, $userMessage);
        $result['confidence'] = $confidence;

        // 3. Si la confianza es muy baja, usar respuesta de respaldo
        if ($confidence < 0.3) { // Threshold más bajo para usar menos fallbacks
            $result['response'] = $this->generateFallbackResponse($userMessage, $context);
            $result['confidence'] = 0.8;
            $result['fallback_used'] = true;
        } else {
            $result['response'] = $response;
        }

        // 4. Validar y limpiar contactos inventados (con filtros más específicos)
        if ($result['success']) {
            $result['response'] = $this->cleanFakeContactsMinimal($result['response'], $context);
        }

        return $result;
    }

    /**
     * Optimizar formato de respuesta para conversación natural
     */
    private function optimizeResponseFormat(string $response): string
    {
        // 1. ELIMINAR COMPLETAMENTE formato markdown visible
        $response = preg_replace('/📋\s*Información encontrada:\s*/i', '', $response);
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $response); // Quitar headers
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $response); // Quitar campos en negritas
        
        // 2. Eliminar secciones estructuradas
        $response = preg_replace('/### Descripción\s*/i', '', $response);
        $response = preg_replace('/### Contacto\s*/i', '', $response);
        $response = preg_replace('/\*\*Modalidad:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Usuarios:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Dependencia:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Estado:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Costo:\*\*/i', '', $response);

        // 3. Convertir listas a texto fluido
        $response = preg_replace('/^\* /m', '', $response);
        $response = preg_replace('/^- /m', '', $response);

        // 4. Limpiar múltiples saltos de línea
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // 5. Eliminar líneas que quedaron vacías después de limpieza
        $response = preg_replace('/^\s*$/m', '', $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);

        // 6. Asegurar que no hay líneas vacías al inicio o final
        $response = trim($response);

        return $response;
    }

    /**
     * Calcular confianza optimizada
     */
    private function calculateOptimizedConfidence(string $response, array $context, string $userMessage): float
    {
        $confidence = 0.5; // Base

        // Bonus por tener contexto
        if (!empty($context)) {
            $confidence += 0.3;
        }

        // Bonus por longitud apropiada
        $length = strlen($response);
        if ($length >= 100 && $length <= 600) {
            $confidence += 0.2;
        } else {
            $confidence -= 0.1;
        }

        // Bonus por tener contacto real del contexto
        if (preg_match('/\d{3}-\d{3}-\d{4}|ext\.\s*\d+|@uan\.edu\.mx/', $response)) {
            $confidence += 0.1;
        }

        // Penalizar respuestas con formato problemático
        if (preg_match('/\*\s+/', $response) || preg_match('/\n{3,}/', $response)) {
            $confidence -= 0.2;
        }

        // Bonus por estructura apropiada
        if ($this->hasGoodStructure($response)) {
            $confidence += 0.1;
        }

        return max(0.1, min(1.0, $confidence));
    }

    /**
     * Verificar si la respuesta tiene buena estructura
     */
    private function hasGoodStructure(string $response): bool
    {
        $paragraphs = explode("\n\n", $response);

        // Debe tener entre 2 y 4 secciones
        if (count($paragraphs) < 2 || count($paragraphs) > 4) {
            return false;
        }

        // Debe tener información útil (contacto o contenido específico)
        if (!preg_match('/📞|\d{3}-\d{3}-\d{4}|procedimiento|requisitos|información/', $response)) {
            return false;
        }

        return true;
    }

    /**
     * Generar respuesta de respaldo optimizada
     */
    private function generateFallbackResponse(string $userMessage, array $context): string
    {
        $messageLower = strtolower($userMessage);

        // Respuestas específicas con personalidad Ociel Senpai
        if (preg_match('/inscripci[oó]n|admisi[oó]n/', $messageLower)) {
            return "¡Claro que sí! 🐯 Te ayudo con información sobre inscripciones y admisión.\n\n" .
                   "Te cuento que para estos temas es importante revisar la información más actualizada. Te recomiendo contactar directamente con la Secretaría Académica.\n\n" .
                   "Contacto: 311-211-8800 ext. 8520 - secretaria.academica@uan.edu.mx\n\n" .
                   "¿Hay algún proceso de inscripción específico sobre el que necesites información? Estoy aquí para apoyarte 🐾";
        }

        if (preg_match('/carrera|licenciatura/', $messageLower)) {
            return "¡Perfecto! 🐯 Te ayudo con información sobre carreras y programas académicos.\n\n" .
                   "Para conocer toda nuestra oferta educativa actualizada, te sugiero revisar la información oficial. Los datos más precisos los puedes obtener directamente.\n\n" .
                   "Para más información: 311-211-8800 - información general\n\n" .
                   "¿Te interesa información sobre alguna carrera en particular? Aquí estaré para lo que necesites 🐾";
        }

        if (preg_match('/sistema|soporte|plataforma/', $messageLower)) {
            return "¡Te ayudo con eso! 🐯 Para soporte técnico y sistemas estoy aquí.\n\n" .
                   "Los compañeros de la Dirección de Infraestructura y Servicios Tecnológicos son los expertos en estos temas. Te recomiendo contactarlos directamente.\n\n" .
                   "Contacto: 311-211-8800 ext. 8640 - sistemas@uan.edu.mx\n\n" .
                   "¿El problema es con alguna plataforma específica? Estoy aquí para apoyarte 🐾";
        }

        // Respuesta general con contexto si existe
        if (!empty($context)) {
            return "¡Hola! 🐯 Encontré información relacionada con tu consulta.\n\n" .
                   substr($context[0], 0, 200) . "...\n\n" .
                   "¿Necesitas que profundice en algún aspecto específico? Estoy aquí para apoyarte 🐾";
        }

        // Respuesta completamente general con personalidad Ociel
        return "¡Hola! Soy Ociel, tu compañero senpai digital 🐯\n\n" .
               "Estoy aquí para acompañarte y proporcionarte información específica de los servicios de nuestra universidad. Me especializo en ayudar a estudiantes, empleados y público general con todo lo que necesiten.\n\n" .
               "¿Sobre qué servicio específico necesitas información? Aquí estaré para lo que necesites 🐾";
    }

    /**
     * Limpiar contactos falsos inventados por el modelo
     */
    private function cleanFakeContactsMinimal(string $response, array $context): string
    {
        // Limpieza mínima para preservar contenido válido
        
        // Solo eliminar patrones claramente inventados
        $response = preg_replace('/555[-\s]?555[-\s]?5555/', '', $response);
        $response = preg_replace('/123[-\s]?456[-\s]?7890/', '', $response);
        $response = preg_replace('/ejemplo@uan\.edu\.mx/', '', $response);
        
        return trim($response);
    }
    
    private function cleanFakeContacts(string $response, array $context): string
    {
        Log::info('Cleaning fake contacts called', ['response_length' => strlen($response)]);
        
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
        $response = preg_replace('/.*al teléfono\s*o por.*$/m', '', $response);
        $response = preg_replace('/.*puedes contactar.*al teléfono.*$/m', '', $response);
        $response = preg_replace('/.*\[.*\]\(mailto:.*\).*$/m', '', $response);
        
        // Si después de limpiar queda una sección de contacto vacía, eliminarla
        $response = preg_replace('/\*\*Contacto\*\*\s*\n\n/', '', $response);
        $response = preg_replace('/\*\*.*[Cc]ontacto.*\*\*\s*\n*$/', '', $response);
        
        // Limpiar líneas vacías múltiples
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        
        return trim($response);
    }

    /**
     * Limpieza adicional para eliminar cualquier rastro de formato markdown
     */
    private function stripAllMarkdownFormatting(string $response): string
    {
        // Eliminar cualquier formato estructurado que haya pasado los filtros anteriores
        $response = preg_replace('/📋\s*Información encontrada:\s*/i', '', $response);
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $response);
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '$1: ', $response);
        $response = preg_replace('/### (.+)/i', '$1', $response);
        $response = preg_replace('/\*\*(.+?)\*\*/i', '$1', $response);
        $response = preg_replace('/^\s*[-*]\s+/m', '', $response);
        
        // Limpiar líneas vacías resultantes
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = preg_replace('/^\s*$/m', '', $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);
        
        return trim($response);
    }

    // === MÉTODOS EXISTENTES SIN CAMBIOS ===

    public function isHealthy(): bool
    {
        $maxRetries = 3;
        $retryDelay = 2;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $response = $this->client->get('/api/version', [
                    'timeout' => 10
                ]);

                if ($response->getStatusCode() === 200) {
                    Log::info("Ollama health check successful on attempt " . ($i + 1));
                    return true;
                }
            } catch (RequestException $e) {
                Log::warning("Ollama health check failed on attempt " . ($i + 1) . ": " . $e->getMessage());

                if ($i < $maxRetries - 1) {
                    sleep($retryDelay);
                }
            }
        }

        Log::error('Ollama health check failed after ' . $maxRetries . ' attempts');
        return false;
    }

    public function getAvailableModels(): array
    {
        try {
            $response = $this->client->get('/api/tags');
            $data = json_decode($response->getBody(), true);

            return collect($data['models'] ?? [])
                ->map(function ($model) {
                    return [
                        'name' => $model['name'],
                        'size' => $model['size'] ?? 0,
                        'modified_at' => $model['modified_at'] ?? null
                    ];
                })
                ->toArray();
        } catch (RequestException $e) {
            Log::error('Failed to get Ollama models: ' . $e->getMessage());
            return [];
        }
    }

    public function generateResponse(string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? $this->primaryModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 1000;

        if (!$this->isHealthy()) {
            return [
                'success' => false,
                'error' => 'Ollama service is not available',
                'model' => $model,
                'response_time' => 0,
            ];
        }

        try {
            $startTime = microtime(true);

            $response = $this->client->post('/api/generate', [
                'json' => [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => $temperature,
                        'num_predict' => $maxTokens,
                        'top_p' => $options['top_p'] ?? 0.9,
                        'top_k' => 40,
                        'repeat_penalty' => $options['repeat_penalty'] ?? 1.0,
                    ]
                ],
                'timeout' => 90,
            ]);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($response->getStatusCode() !== 200) {
                Log::error('Ollama HTTP error: ' . $response->getStatusCode() . ' - ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'HTTP error: ' . $response->getStatusCode(),
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            $data = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON response from Ollama: ' . $response->getBody());
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            if (isset($data['error'])) {
                Log::error('Ollama API error: ' . $data['error']);
                return [
                    'success' => false,
                    'error' => $data['error'],
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            return [
                'success' => true,
                'response' => $data['response'] ?? '',
                'model' => $model,
                'response_time' => $responseTime,
                'tokens_evaluated' => $data['eval_count'] ?? 0,
                'tokens_generated' => $data['eval_count'] ?? 0,
            ];

        } catch (ConnectException $e) {
            Log::error('Ollama connection failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Connection failed: Service may be down',
                'model' => $model,
                'response_time' => 0,
            ];

        } catch (RequestException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000);

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();
                Log::error("Ollama request failed: HTTP {$statusCode} - {$body}");

                return [
                    'success' => false,
                    'error' => "HTTP {$statusCode}: " . substr($body, 0, 100),
                    'model' => $model,
                    'response_time' => $responseTime,
                ];
            }

            Log::error('Ollama request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Request timeout or network error',
                'model' => $model,
                'response_time' => $responseTime,
            ];

        } catch (\Exception $e) {
            Log::error('Unexpected error in Ollama generation: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unexpected error occurred',
                'model' => $model,
                'response_time' => 0,
            ];
        }
    }

    public function generateEmbedding(string $text): array
    {
        $cacheKey = 'embedding_' . md5($text);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->client->post('/api/embeddings', [
                'json' => [
                    'model' => $this->embeddingModel,
                    'prompt' => $text
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $embedding = $data['embedding'] ?? [];

            Cache::put($cacheKey, $embedding, 3600);

            return $embedding;

        } catch (RequestException $e) {
            Log::error('Ollama embedding failed: ' . $e->getMessage());
            return [];
        }
    }

    private function isSimpleQuery(string $message): bool
    {
        $simplePatterns = [
            '/^(hola|hi|hello)/i',
            '/^(gracias|thanks)/i',
            '/^(adiós|bye)/i',
            '/^(sí|no|ok)/i',
            '/\?$/',
        ];

        $wordCount = str_word_count($message);

        if ($wordCount < 8) {
            return true;
        }

        foreach ($simplePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    public function checkRequiredModels(): array
    {
        $availableModels = collect($this->getAvailableModels())->pluck('name')->toArray();

        $requiredModels = [
            'primary' => $this->primaryModel,
            'secondary' => $this->secondaryModel,
            'embedding' => $this->embeddingModel
        ];

        $status = [];

        foreach ($requiredModels as $type => $model) {
            $status[$type] = [
                'model' => $model,
                'available' => in_array($model, $availableModels),
                'type' => $type
            ];
        }

        return $status;
    }

    public function getUsageStats(): array
    {
        return [
            'total_requests' => Cache::get('ollama_requests', 0),
            'average_response_time' => Cache::get('ollama_avg_time', 0),
            'models_used' => Cache::get('ollama_models_used', []),
            'health_status' => $this->isHealthy()
        ];
    }
}
