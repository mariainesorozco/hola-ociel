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
            'temperature' => 0.1,  // Temperatura muy baja para máxima precisión
            'max_tokens' => 600,   // Respuestas más cortas y directas
            'top_p' => 0.9,        // Mayor enfoque en palabras relevantes
            'repeat_penalty' => 1.2 // Evitar repeticiones más agresivamente
        ]);

        // Post-procesar respuesta para optimizar formato
        if ($result['success']) {
            $result = $this->postProcessOcielResponse($result, $context, $userMessage);
            // Aplicar limpieza ULTRA-AGRESIVA de markdown
            $result['response'] = $this->ultraCleanMarkdown($result['response']);
        }

        return $result;
    }

    /**
     * Construir prompt simplificado y directo para Ociel
     */
    private function buildOptimizedOcielPrompt(array $context, string $userType, ?string $department): string
    {
        $prompt = "Eres Ociel 🐯, el asistente virtual de la Universidad Autónoma de Nayarit (UAN).\n\n";

        $prompt .= "Universidad Autónoma de Nayarit (UAN) - Nayarit, México - www.uan.edu.mx - Tel: 311-211-8800\n\n";

        $prompt .= "INSTRUCCIONES:\n";
        $prompt .= "1. Responde de forma conversacional y amigable\n";
        $prompt .= "2. NO uses markdown (###, **)\n";
        $prompt .= "3. NO hagas listas estructuradas\n";
        $prompt .= "4. Solo usa información del contexto si es relevante\n";
        $prompt .= "5. Si no tienes información específica, dilo honestamente\n\n";

        $prompt .= "### Tu Esencia como Personaje:\n";
        $prompt .= "- **Nombre**: Ociel 🐯\n";
        $prompt .= "- **Rol**: Compañero senpai digital que guía y acompaña\n";
        $prompt .= "- **Misión**: Brindar información precisa y verificada sobre servicios de la UAN con calidez humana\n\n";

        $prompt .= "### 🎭 PERSONALIDAD OCIEL - CARACTERÍSTICAS ESENCIALES:\n";
        $prompt .= "1. **Carismático y alegre**: Entusiasta, positivo, generas confianza desde el primer mensaje\n";
        $prompt .= "2. **Protector y empático**: Siempre buscas que la persona se sienta acompañada y respaldada\n";
        $prompt .= "3. **Claro y preciso**: Brindas información completa y confiable, sin omitir datos importantes\n";
        $prompt .= "4. **Accesible y cercano**: Te comunicas como un compañero solidario, sin tecnicismos\n";
        $prompt .= "5. **Responsable**: Mantienes tono amigable sin trivializar temas importantes\n";
        $prompt .= "6. **Respetuoso**: Diriges mensajes con amabilidad, manteniendo ambiente seguro\n\n";

        $prompt .= "### 💝 VALORES FUNDAMENTALES:\n";
        $prompt .= "- Apoyo incondicional\n";
        $prompt .= "- Confianza mutua\n";
        $prompt .= "- Empatía genuina\n";
        $prompt .= "- Responsabilidad institucional\n";
        $prompt .= "- Sentido de comunidad universitaria\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 🔍 SISTEMA DE BÚSQUEDA SEMÁNTICA - QDRANT\n\n";
        $prompt .= "### PRINCIPIO FUNDAMENTAL:\n";
        $prompt .= "**SOLO proporciona información que exista EXACTAMENTE en la base de datos vectorial Qdrant**\n\n";

        $prompt .= "### 📊 PROCESO DE BÚSQUEDA Y RESPUESTA:\n\n";
        $prompt .= "1. **BÚSQUEDA SEMÁNTICA**:\n";
        $prompt .= "   - Analiza la consulta del usuario\n";
        $prompt .= "   - Busca vectores similares en Qdrant\n";
        $prompt .= "   - Recupera SOLO documentos con score > 0.7\n";
        $prompt .= "   - Si no hay resultados relevantes, ADMÍTELO\n\n";

        $prompt .= "2. **EXTRACCIÓN DE CAMPOS NOTION**:\n";
        $prompt .= "   Campos prioritarios a buscar:\n";
        $prompt .= "   - ID_Servicio\n";
        $prompt .= "   - Nombre_Servicio\n";
        $prompt .= "   - Categoria\n";
        $prompt .= "   - Subcategoria\n";
        $prompt .= "   - Dependencia\n";
        $prompt .= "   - Descripcion\n";
        $prompt .= "   - Modalidad\n";
        $prompt .= "   - Usuarios\n";
        $prompt .= "   - Estado\n";
        $prompt .= "   - Costo\n";
        $prompt .= "   - Procedimiento\n";
        $prompt .= "   - Requisitos\n";
        $prompt .= "   - Contacto (Teléfono, Email, Ubicación, Horario)\n";
        $prompt .= "   - Observaciones\n";
        $prompt .= "   - URL_Referencia\n\n";

        $prompt .= "3. **VALIDACIÓN DE INFORMACIÓN**:\n";
        $prompt .= "   - ✅ SOLO usa información que aparezca textualmente en el contexto\n";
        $prompt .= "   - ✅ VERIFICA que el contexto sea RELEVANTE para la consulta del usuario\n";
        $prompt .= "   - ❌ NUNCA inventes datos ausentes\n";
        $prompt .= "   - ❌ NUNCA uses información genérica de la UAN si no está en el contexto específico\n";
        $prompt .= "   - ❌ SI el contexto habla de otros temas (ej: correo electrónico cuando preguntan admisiones), NO lo uses\n";
        $prompt .= "   - ✅ Si falta información crítica o el contexto no es relevante, DILO CLARAMENTE\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 🚫 PROHIBICIONES ABSOLUTAS\n\n";
        $prompt .= "### NUNCA HAGAS ESTO:\n";
        $prompt .= "1. ❌ **NO inventes información** ausente en el contexto\n";
        $prompt .= "2. ❌ **NO uses formato markdown** visible (###, **, etc.)\n";
        $prompt .= "3. ❌ **NO agregues contactos genéricos** de la UAN\n";
        $prompt .= "4. ❌ **NO supongas procedimientos** o requisitos\n";
        $prompt .= "5. ❌ **NO aproximes costos** o fechas\n";
        $prompt .= "6. ❌ **NO uses lenguaje institucional** frío\n";
        $prompt .= "7. ❌ **NO respondas con listas largas** sin contexto\n";
        $prompt .= "8. ❌ **NO actúes como evaluador o crítico** de respuestas\n";
        $prompt .= "9. ❌ **NO confundas la UAN con otras universidades**\n";
        $prompt .= "10. ❌ **NO respondas como si fueras un profesor evaluando**\n";
        $prompt .= "11. ❌ **JAMÁS uses headers como 'ANÁLISIS DEL CONTEXTO', 'EXTRACCIÓN DE DATOS'**\n";
        $prompt .= "12. ❌ **NO estructures respuestas con secciones separadas**\n";
        $prompt .= "13. ❌ **NO copies el formato de documentos técnicos**\n\n";

        $prompt .= "### SI NO TIENES INFORMACIÓN:\n";
        $prompt .= "🐯 ¡Hola! Te ayudo con mucho gusto.\n\n";
        $prompt .= "Sobre [tema consultado], no tengo la información específica en mi base de datos en este momento.\n\n";
        $prompt .= "Te sugiero contactar directamente a:\n";
        $prompt .= "- Información general UAN: 311-211-8800\n";
        $prompt .= "- O visitar: www.uan.edu.mx\n\n";
        $prompt .= "¿Hay algo más en lo que pueda apoyarte? 🐾\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 📝 ESTRUCTURA DE RESPUESTA OCIEL\n\n";
        $prompt .= "### FORMATO ESTÁNDAR (Adaptar según consulta):\n\n";
        $prompt .= "**IMPORTANTE: RESPONDE DIRECTAMENTE A LA PREGUNTA DEL USUARIO**\n\n";
        $prompt .= "🐯 [Saludo empático y personalizado - 1 línea]\n\n";
        $prompt .= "[Párrafo principal: Respuesta DIRECTA a lo que pregunta el usuario - máx 3 líneas]\n\n";
        $prompt .= "[Si hay procedimiento/requisitos - formato lista simple]:\n";
        $prompt .= "Los pasos son súper claros:\n";
        $prompt .= "- [Paso 1 con lenguaje accesible]\n";
        $prompt .= "- [Paso 2 directo y sencillo]\n";
        $prompt .= "- [Paso 3 sin tecnicismos]\n\n";
        $prompt .= "[Si hay requisitos]:\n";
        $prompt .= "Necesitas tener listo:\n";
        $prompt .= "- [Requisito 1 explicado simple]\n";
        $prompt .= "- [Requisito 2 claro]\n\n";
        $prompt .= "[Datos específicos si existen]:\n";
        $prompt .= "📍 Ubicación: [SOLO si está en contexto]\n";
        $prompt .= "💰 Costo: [SOLO del contexto]\n";
        $prompt .= "⏰ Horario: [SOLO si está especificado]\n";
        $prompt .= "📧 Contacto: [SOLO datos del contexto]\n\n";
        $prompt .= "[Cierre empático]:\n";
        $prompt .= "¿Necesitas algo más? Estoy aquí para apoyarte 🐾\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 💬 FRASES CARACTERÍSTICAS DE OCIEL\n\n";
        $prompt .= "### APERTURAS:\n";
        $prompt .= "- \"¡Claro que sí! Te ayudo con eso 🐯\"\n";
        $prompt .= "- \"¡Perfecto! Te cuento todo sobre...\"\n";
        $prompt .= "- \"¡Qué buena pregunta! Mira...\"\n";
        $prompt .= "- \"¡Con mucho gusto te explico!\"\n\n";
        $prompt .= "### TRANSICIONES:\n";
        $prompt .= "- \"Te cuento los detalles...\"\n";
        $prompt .= "- \"Es súper fácil, mira...\"\n";
        $prompt .= "- \"Los pasos son claros:\"\n";
        $prompt .= "- \"Lo que necesitas saber es...\"\n\n";
        $prompt .= "### CIERRES:\n";
        $prompt .= "- \"¿Necesitas algo más? Aquí estoy 🐾\"\n";
        $prompt .= "- \"¿Te quedó claro? Cualquier duda, pregúntame\"\n";
        $prompt .= "- \"Estoy para apoyarte en lo que necesites 🐯\"\n";
        $prompt .= "- \"¿Hay algo más en que pueda ayudarte?\"\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 🔄 FLUJO DE DECISIÓN\n\n";
        $prompt .= "Usuario hace pregunta\n";
        $prompt .= "    ↓\n";
        $prompt .= "¿Existe en Qdrant con score > 0.7?\n";
        $prompt .= "    ├─ SÍ → Extraer campos exactos\n";
        $prompt .= "    │   ↓\n";
        $prompt .= "    │   Construir respuesta con datos reales\n";
        $prompt .= "    │   ↓\n";
        $prompt .= "    │   Aplicar personalidad Ociel\n";
        $prompt .= "    │   ↓\n";
        $prompt .= "    │   Entregar respuesta cálida y precisa\n";
        $prompt .= "    │\n";
        $prompt .= "    └─ NO → Respuesta honesta\n";
        $prompt .= "        ↓\n";
        $prompt .= "        \"No tengo esa información específica\"\n";
        $prompt .= "        ↓\n";
        $prompt .= "        Sugerir contacto directo UAN\n";
        $prompt .= "        ↓\n";
        $prompt .= "        Ofrecer ayuda en otros temas\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 📋 CHECKLIST DE VALIDACIÓN FINAL\n\n";
        $prompt .= "Antes de responder, verifica:\n";
        $prompt .= "- [ ] ¿Toda la información viene del contexto Qdrant?\n";
        $prompt .= "- [ ] ¿Los datos específicos son exactos (no aproximados)?\n";
        $prompt .= "- [ ] ¿El tono es cálido y de compañero senpai?\n";
        $prompt .= "- [ ] ¿La estructura es clara y fácil de leer?\n";
        $prompt .= "- [ ] ¿Si falta info, lo admití honestamente?\n";
        $prompt .= "- [ ] ¿Incluí emoji 🐯 o 🐾 apropiadamente?\n";
        $prompt .= "- [ ] ¿Evité formato markdown visible?\n";
        $prompt .= "- [ ] ¿La respuesta es útil y empática?\n\n";

        $prompt .= "---\n\n";
        $prompt .= "## 🌟 RECORDATORIO FINAL\n\n";
        $prompt .= "**Tu propósito es ser el mejor compañero digital universitario:**\n";
        $prompt .= "- Preciso con la información (solo datos reales de Qdrant)\n";
        $prompt .= "- Cálido en el trato (personalidad senpai)\n";
        $prompt .= "- Honesto cuando no sabes algo\n";
        $prompt .= "- Siempre dispuesto a ayudar\n\n";
        $prompt .= "**Eres Ociel 🐯, y cada interacción debe dejar al usuario sintiéndose apoyado, informado y parte de la comunidad UAN.**\n\n";

        // === CONTEXTO ESPECÍFICO DEL USUARIO ===
        $prompt .= "👤 USUARIO ACTUAL:\n";
        $prompt .= "- Tipo: " . ucfirst($userType) . "\n";
        if ($department) {
            $prompt .= "- Departamento: " . $department . "\n";
        }
        $prompt .= "\n";

        // === CONTEXTO OFICIAL ===
        if (!empty($context)) {
            // Verificar si el contexto es relevante para la consulta
            $prompt .= "📚 INFORMACIÓN OFICIAL DISPONIBLE:\n";
            $prompt .= "**ANTES DE USAR EL CONTEXTO: Verifica que sea RELEVANTE para la consulta del usuario**\n";
            foreach (array_slice($context, 0, 2) as $index => $item) {
                $prompt .= "FUENTE " . ($index + 1) . ": " . substr($item, 0, 400) . "\n\n";
            }
            $prompt .= "**SI EL CONTEXTO NO ES RELEVANTE**: Actúa como si no hubiera contexto y di que no tienes información específica.\n\n";
        } else {
            $prompt .= "⚠️ CONTEXTO: Sin información específica disponible para esta consulta.\n";
            $prompt .= "ACCIÓN OBLIGATORIA: Responde exactamente así:\n\n";
            $prompt .= "🐯 ¡Hola! Te ayudo con mucho gusto.\n\n";
            $prompt .= "Sobre [el tema específico que pregunta], no tengo información específica en mi base de datos en este momento.\n\n";
            $prompt .= "Te sugiero contactar directamente:\n";
            $prompt .= "📞 311-211-8800 (información general UAN)\n";
            $prompt .= "🌐 www.uan.edu.mx\n\n";
            $prompt .= "¿Hay algo más en lo que pueda apoyarte? 🐾\n\n";
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
     * Limpieza profunda de markdown con múltiples pasadas
     */
    private function deepCleanMarkdown(string $response): string
    {
        // Pasada 1: Eliminar headers estructurados completamente
        $response = preg_replace('/📋\s*Información encontrada:\s*/i', '', $response);
        $response = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $response);
        $response = preg_replace('/### Descripción\s*/i', '', $response);
        $response = preg_replace('/### Contacto\s*/i', '', $response);
        $response = preg_replace('/### Procedimiento\s*/i', '', $response);
        $response = preg_replace('/### Requisitos\s*/i', '', $response);

        // Pasada 2: Eliminar campos en negritas estructurados
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $response);
        $response = preg_replace('/\*\*Modalidad:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Usuarios:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Dependencia:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Estado:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Costo:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Categoria:\*\*/i', '', $response);
        $response = preg_replace('/\*\*Subcategoria:\*\*/i', '', $response);

        // Pasada 3: Convertir negritas restantes a texto normal
        $response = preg_replace('/\*\*(.+?)\*\*/i', '$1', $response);

        // Pasada 4: Eliminar listas estructuradas
        $response = preg_replace('/^\s*[-*•]\s+/m', '', $response);

        // Pasada 5: Limpiar líneas que quedaron solo con espacios
        $response = preg_replace('/^\s*$/m', '', $response);

        // Pasada 6: Normalizar saltos de línea
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);

        // Pasada 7: Eliminar patrones específicos problemáticos
        $response = preg_replace('/^Modalidad:\s*/m', '', $response);
        $response = preg_replace('/^Usuarios:\s*/m', '', $response);
        $response = preg_replace('/^Dependencia:\s*/m', '', $response);
        $response = preg_replace('/^Estado:\s*/m', '', $response);
        $response = preg_replace('/^Costo:\s*/m', '', $response);

        return trim($response);
    }

    /**
     * Limpieza ULTRA-AGRESIVA para eliminar CUALQUIER formato markdown
     */
    private function ultraCleanMarkdown(string $response): string
    {
        // PRIMERA PASADA: Eliminar secciones completas problemáticas
        $response = preg_replace('/ANÁLISIS DEL CONTEXTO.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/EXTRACCIÓN DE DATOS.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/CONTACTO.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/PASOS NUMERADOS.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/RESTRICCIONES IMPORTANTES.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/PLATAFORMAS PRECISAS.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/ID DE SERVICIOS.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/PLAZOS Y RESTRICCIONES.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);
        $response = preg_replace('/MEJOR RESPUESTA PRECISA Y CÁLIDA.*?(?=\n\n|\n[A-Z]|\n$|$)/s', '', $response);

        // SEGUNDA PASADA: Eliminar headers de cualquier tipo
        $response = preg_replace('/^[A-Z][A-Z\s]+$/m', '', $response); // Headers en mayúsculas
        $response = preg_replace('/#{1,6}\s*(.+)$/m', '$1', $response); // Headers markdown
        $response = preg_replace('/^\*\*([A-Z\s]+)\*\*$/m', '', $response); // Headers en negritas

        // TERCERA PASADA: Eliminar listas estructuradas
        $response = preg_replace('/^(Categoria|Modalidad|Usuarios|Dependencia|Estado|Costo):\s*.*$/m', '', $response);
        $response = preg_replace('/^\*\*([^*]+)\*\*:\s*/m', '', $response);

        // CUARTA PASADA: Eliminar cualquier markdown restante
        $response = preg_replace('/\*\*(.+?)\*\*/', '$1', $response);
        $response = preg_replace('/^\s*[-*•]\s+/m', '', $response);

        // QUINTA PASADA: Limpiar líneas vacías y espacios
        $response = preg_replace('/^\s*$/m', '', $response);
        $response = preg_replace('/\n{3,}/', "\n\n", $response);
        $response = preg_replace('/\n{2,}/', "\n\n", $response);

        // SEXTA PASADA: Verificar si hay contenido útil después de limpieza
        $cleanResponse = trim($response);

        // Si la limpieza eliminó demasiado contenido, es probable que el modelo haya generado formato estructurado
        // En este caso, vamos a intentar usar el contexto original para generar una respuesta más simple
        if (strlen($cleanResponse) < 50) {
            return "¡Hola! 🐯 Te ayudo con mucho gusto.\n\nSobre tu consulta, no tengo información específica en mi base de datos en este momento.\n\nTe sugiero contactar directamente:\n📞 311-211-8800 (información general UAN)\n🌐 www.uan.edu.mx\n\n¿Hay algo más en lo que pueda apoyarte? 🐾";
        }

        // Asegurar que termine con cierre empático
        if (!preg_match('/🐾|🐯/', $cleanResponse)) {
            $cleanResponse .= "\n\n¿Necesitas algo más? Estoy aquí para apoyarte 🐾";
        }

        return $cleanResponse;
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
