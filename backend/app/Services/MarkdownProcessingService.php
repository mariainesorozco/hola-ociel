<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class MarkdownProcessingService
{
    private $ollamaService;
    private $knowledgeService;

    public function __construct(OllamaService $ollamaService, EnhancedKnowledgeBaseService $knowledgeService)
    {
        $this->ollamaService = $ollamaService;
        $this->knowledgeService = $knowledgeService;
    }

    /**
     * Procesar archivo Markdown con flexibilidad de formato
     */
    public function processMarkdownContent(string $content, array $options = []): array
    {
        $options = array_merge([
            'source_name' => 'markdown_import',
            'auto_categorize' => true,
            'extract_metadata' => true,
            'split_by_sections' => true,
            'min_section_length' => 50,
            'max_section_length' => 2000,
            'preserve_formatting' => true,
            'generate_keywords' => true
        ], $options);

        $results = [
            'total_processed' => 0,
            'entries_created' => 0,
            'entries_updated' => 0,
            'errors' => 0,
            'sections_found' => 0,
            'categories_detected' => []
        ];

        try {
            Log::info('Iniciando procesamiento de contenido Markdown', [
                'content_length' => strlen($content),
                'options' => $options
            ]);

            // 1. Limpiar y normalizar el contenido
            $normalizedContent = $this->normalizeMarkdownContent($content);

            // 2. Extraer metadatos del documento
            $metadata = $options['extract_metadata'] ?
                $this->extractDocumentMetadata($normalizedContent) : [];

            // 3. Dividir en secciones de manera inteligente
            $sections = $options['split_by_sections'] ?
                $this->intelligentSectionSplit($normalizedContent, $options) :
                [$this->createSingleSection($normalizedContent, $metadata)];

            $results['sections_found'] = count($sections);

            // 4. Procesar cada sección
            foreach ($sections as $section) {
                $results['total_processed']++;

                try {
                    $processedSection = $this->processSection($section, $metadata, $options);

                    if ($processedSection && $this->validateSection($processedSection, $options)) {
                        $saved = $this->saveToKnowledgeBase($processedSection, $options['source_name']);

                        if ($saved['created']) {
                            $results['entries_created']++;
                        } elseif ($saved['updated']) {
                            $results['entries_updated']++;
                        }

                        // Agregar categoría detectada
                        if (!in_array($processedSection['category'], $results['categories_detected'])) {
                            $results['categories_detected'][] = $processedSection['category'];
                        }
                    }

                } catch (\Exception $e) {
                    Log::warning('Error procesando sección de Markdown', [
                        'section_title' => $section['title'] ?? 'Sin título',
                        'error' => $e->getMessage()
                    ]);
                    $results['errors']++;
                }
            }

            Log::info('Procesamiento de Markdown completado', $results);

        } catch (\Exception $e) {
            Log::error('Error en procesamiento de Markdown: ' . $e->getMessage());
            $results['errors']++;
        }

        return $results;
    }

    /**
     * Normalizar contenido Markdown para mejor procesamiento
     */
    private function normalizeMarkdownContent(string $content): string
    {
        // Convertir diferentes tipos de salto de línea
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Normalizar títulos con diferentes formatos
        $content = preg_replace_callback('/^#+\s*(.+)$/m', function($matches) {
            $level = strlen(preg_replace('/[^#].*/', '', $matches[0]));
            return str_repeat('#', min($level, 6)) . ' ' . trim($matches[1]);
        }, $content);

        // Limpiar espacios excesivos pero preservar estructura
        $content = preg_replace('/[ \t]+$/m', '', $content); // Espacios al final de línea
        $content = preg_replace('/\n{4,}/', "\n\n\n", $content); // Máximo 3 saltos seguidos

        // Normalizar enlaces e imágenes
        $content = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '![Imagen: $1]($2)', $content);
        $content = preg_replace('/\[([^\]]+)\]\(javascript:[^)]*\)/', '$1', $content); // Remover enlaces JS

        // Limpiar elementos HTML pero preservar contenido
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $content);

        return trim($content);
    }

    /**
     * Extraer metadatos del documento
     */
    private function extractDocumentMetadata(string $content): array
    {
        $metadata = [
            'document_title' => null,
            'main_category' => null,
            'source_url' => null,
            'institution' => null,
            'document_type' => 'informational',
            'target_audience' => [],
            'has_procedures' => false,
            'has_contact_info' => false,
            'language' => 'es'
        ];

        // Extraer título principal (primer H1 o H2)
        if (preg_match('/^#{1,2}\s+(.+)$/m', $content, $matches)) {
            $metadata['document_title'] = trim($matches[1]);
        }

        // Detectar URLs en el contenido
        if (preg_match('/https?:\/\/[^\s\)]+/', $content, $matches)) {
            $metadata['source_url'] = $matches[0];
        }

        // Detectar institución (UAN, Universidad Autónoma de Nayarit)
        if (preg_match('/Universidad Autónoma de Nayarit|UAN|piida\.uan\.mx/i', $content)) {
            $metadata['institution'] = 'UAN';
        }

        // Detectar tipo de documento
        if (preg_match('/trámite|proceso|procedimiento|solicitud|requisitos/i', $content)) {
            $metadata['document_type'] = 'procedural';
            $metadata['has_procedures'] = true;
        } elseif (preg_match('/servicio|información|descripción/i', $content)) {
            $metadata['document_type'] = 'informational';
        }

        // Detectar audiencia objetivo
        if (preg_match('/estudiante|alumno|aspirante/i', $content)) {
            $metadata['target_audience'][] = 'student';
        }
        if (preg_match('/docente|profesor|empleado/i', $content)) {
            $metadata['target_audience'][] = 'employee';
        }
        if (preg_match('/público|general|visitante/i', $content)) {
            $metadata['target_audience'][] = 'public';
        }

        // Default si no se detectó audiencia
        if (empty($metadata['target_audience'])) {
            $metadata['target_audience'] = ['student', 'public'];
        }

        // Detectar información de contacto
        $metadata['has_contact_info'] = preg_match('/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b|@\w+\.\w+/', $content);

        return $metadata;
    }

    /**
     * División inteligente en secciones
     */
    private function intelligentSectionSplit(string $content, array $options): array
    {
        $sections = [];
        $lines = explode("\n", $content);
        $currentSection = null;
        $currentContent = [];

        foreach ($lines as $lineNumber => $line) {
            $trimmedLine = trim($line);

            // Detectar inicio de nueva sección (headers o patrones específicos)
            if ($this->isNewSectionStart($trimmedLine, $lineNumber, $lines)) {
                // Guardar sección anterior si existe
                if ($currentSection && !empty($currentContent)) {
                    $sections[] = $this->buildSection($currentSection, $currentContent, $options);
                }

                // Iniciar nueva sección
                $currentSection = $this->extractSectionTitle($trimmedLine);
                $currentContent = [$line];
            } else {
                // Agregar línea a la sección actual
                $currentContent[] = $line;
            }
        }

        // Agregar última sección
        if ($currentSection && !empty($currentContent)) {
            $sections[] = $this->buildSection($currentSection, $currentContent, $options);
        }

        // Si no se encontraron secciones, crear una sección única
        if (empty($sections)) {
            $sections[] = $this->createSingleSection($content, []);
        }

        return array_filter($sections, function($section) use ($options) {
            return strlen($section['content']) >= $options['min_section_length'];
        });
    }

    /**
     * Detectar si una línea inicia una nueva sección
     */
    private function isNewSectionStart(string $line, int $lineNumber, array $allLines): bool
    {
        // Headers markdown
        if (preg_match('/^#{1,6}\s+.+/', $line)) {
            return true;
        }

        // Líneas que parecen títulos (seguidas de línea vacía o con contenido)
        if (preg_match('/^[A-ZÁÉÍÓÚÑ][^.]{10,80}$/', $line) &&
            isset($allLines[$lineNumber + 1]) &&
            (trim($allLines[$lineNumber + 1]) === '' || !preg_match('/^[a-z]/', trim($allLines[$lineNumber + 1])))) {
            return true;
        }

        // Patrones específicos de servicios/trámites
        if (preg_match('/^(Perfil:|Trámite:|Servicio:|Proceso:|## |### )/', $line)) {
            return true;
        }

        // Líneas con formato de lista que parecen títulos de servicios
        if (preg_match('/^[-*+]\s+[A-ZÁÉÍÓÚÑ][^.]{15,}$/', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Extraer título de sección
     */
    private function extractSectionTitle(string $line): string
    {
        // Remover marcadores markdown
        $title = preg_replace('/^#{1,6}\s*/', '', $line);
        $title = preg_replace('/^[-*+]\s*/', '', $title);
        $title = preg_replace('/^(Perfil:|Trámite:|Servicio:|Proceso:)\s*/i', '', $title);

        return trim($title);
    }

    /**
     * Construir sección estructurada
     */
    private function buildSection(string $title, array $contentLines, array $options): array
    {
        $content = implode("\n", $contentLines);

        // Limpiar contenido de la sección
        $cleanContent = $this->cleanSectionContent($content);

        // Limitar tamaño si es necesario
        if (strlen($cleanContent) > $options['max_section_length']) {
            $cleanContent = $this->intelligentTruncate($cleanContent, $options['max_section_length']);
        }

        return [
            'title' => $title,
            'content' => $cleanContent,
            'raw_content' => $content,
            'word_count' => str_word_count($cleanContent),
            'has_links' => preg_match('/\[.+\]\(.+\)/', $content),
            'has_images' => preg_match('/!\[.*\]\(.+\)/', $content),
            'section_type' => $this->detectSectionType($content)
        ];
    }

    /**
     * Limpiar contenido de sección
     */
    private function cleanSectionContent(string $content): string
    {
        // Preservar estructura básica pero limpiar elementos problemáticos
        $content = preg_replace('/!\[.*?\]\([^)]*\)/', '[Imagen]', $content);
        $content = preg_replace('/\[([^\]]+)\]\(javascript:[^)]*\)/', '$1', $content);
        $content = preg_replace('/\\\\\\\\/', '', $content); // Remover backslashes múltiples

        // Limpiar espacios pero preservar párrafos
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);

        return trim($content);
    }

    /**
     * Detectar tipo de sección
     */
    private function detectSectionType(string $content): string
    {
        $contentLower = strtolower($content);

        if (preg_match('/requisitos?|documentos?|procedimiento|pasos?/i', $content)) {
            return 'procedural';
        } elseif (preg_match('/contacto|teléfono|email|dirección/i', $content)) {
            return 'contact';
        } elseif (preg_match('/perfil:|trámite:|servicio:/i', $content)) {
            return 'service_description';
        } elseif (preg_match('/^[-*+]\s+/m', $content)) {
            return 'list';
        } else {
            return 'informational';
        }
    }

    /**
     * Truncar contenido de manera inteligente
     */
    private function intelligentTruncate(string $content, int $maxLength): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        // Buscar punto de corte en párrafo o oración
        $truncated = substr($content, 0, $maxLength);
        $lastParagraph = strrpos($truncated, "\n\n");
        $lastSentence = strrpos($truncated, '. ');

        if ($lastParagraph !== false && $lastParagraph > ($maxLength * 0.7)) {
            return substr($content, 0, $lastParagraph) . "\n\n[Contenido truncado...]";
        } elseif ($lastSentence !== false && $lastSentence > ($maxLength * 0.8)) {
            return substr($content, 0, $lastSentence + 1) . " [Contenido truncado...]";
        } else {
            return $truncated . "... [Contenido truncado]";
        }
    }

    /**
     * Crear sección única para documentos sin divisiones
     */
    private function createSingleSection(string $content, array $metadata): array
    {
        return [
            'title' => $metadata['document_title'] ?? 'Documento Markdown',
            'content' => $this->cleanSectionContent($content),
            'raw_content' => $content,
            'word_count' => str_word_count($content),
            'has_links' => preg_match('/\[.+\]\(.+\)/', $content),
            'has_images' => preg_match('/!\[.*\]\(.+\)/', $content),
            'section_type' => 'document'
        ];
    }

    /**
     * Procesar sección individual
     */
    private function processSection(array $section, array $metadata, array $options): ?array
    {
        try {
            // Generar categoría automáticamente
            $category = $options['auto_categorize'] ?
                $this->intelligentCategorization($section, $metadata) :
                'informacion_general';

            // Generar keywords
            $keywords = $options['generate_keywords'] ?
                $this->generateIntelligentKeywords($section, $metadata) :
                $this->extractBasicKeywords($section['title'] . ' ' . $section['content']);

            // Determinar departamento
            $department = $this->determineDepartment($section, $metadata);

            // Extraer información de contacto
            $contactInfo = $this->extractContactInformation($section['content']);

            // Determinar prioridad
            $priority = $this->calculatePriority($section, $metadata, $category);

            return [
                'title' => $section['title'],
                'content' => $section['content'],
                'category' => $category,
                'department' => $department,
                'user_types' => json_encode($metadata['target_audience']),
                'keywords' => json_encode($keywords),
                'source_url' => $metadata['source_url'],
                'contact_info' => $contactInfo,
                'priority' => $priority,
                'section_type' => $section['section_type'],
                'word_count' => $section['word_count'],
                'metadata' => json_encode([
                    'document_type' => $metadata['document_type'] ?? 'informational',
                    'has_procedures' => $metadata['has_procedures'] ?? false,
                    'institution' => $metadata['institution'] ?? null,
                    'processing_method' => 'markdown_flexible'
                ])
            ];

        } catch (\Exception $e) {
            Log::error('Error procesando sección: ' . $e->getMessage(), [
                'section_title' => $section['title']
            ]);
            return null;
        }
    }

    /**
     * Categorización inteligente usando IA
     */
    private function intelligentCategorization(array $section, array $metadata): string
    {
        try {
            $prompt = "Analiza el siguiente contenido universitario y determina su categoría más apropiada.

Título: {$section['title']}
Contenido: " . substr($section['content'], 0, 500) . "
Tipo de sección: {$section['section_type']}

Categorías disponibles:
- tramites_estudiantes: Trámites y procesos para estudiantes
- tramites_docentes: Trámites para personal académico
- servicios_academicos: Servicios universitarios generales
- oferta_educativa: Programas académicos y educativos
- directorio: Información de contacto y ubicaciones
- normatividad: Reglamentos y normas institucionales
- eventos: Actividades y eventos universitarios
- informacion_general: Información general de la universidad

Responde solo con el nombre de la categoría más apropiada.";

            $response = $this->ollamaService->generateResponse($prompt, [
                'temperature' => 0.1,
                'max_tokens' => 50
            ]);

            if ($response['success']) {
                $detectedCategory = trim(strtolower($response['response']));

                $validCategories = [
                    'tramites_estudiantes', 'tramites_docentes', 'servicios_academicos',
                    'oferta_educativa', 'directorio', 'normatividad', 'eventos', 'informacion_general'
                ];

                if (in_array($detectedCategory, $validCategories)) {
                    return $detectedCategory;
                }
            }

        } catch (\Exception $e) {
            Log::warning('Error en categorización con IA: ' . $e->getMessage());
        }

        // Fallback a categorización por patrones
        return $this->basicCategorization($section, $metadata);
    }

    /**
     * Categorización básica por patrones
     */
    private function basicCategorization(array $section, array $metadata): string
    {
        $content = strtolower($section['title'] . ' ' . $section['content']);

        $patterns = [
            'tramites_estudiantes' => [
                'inscripción', 'matrícula', 'egreso', 'titulación', 'certificado',
                'carta de pasante', 'examen', 'beca', 'movilidad', 'cambio de programa'
            ],
            'tramites_docentes' => [
                'docente', 'profesor', 'academia', 'registro de publicaciones',
                'comité curricular', 'actividades académicas', 'congreso'
            ],
            'servicios_academicos' => [
                'biblioteca', 'laboratorio', 'servicio', 'simulador', 'plataforma',
                'validación', 'optativas', 'rubro'
            ],
            'directorio' => [
                'contacto', 'teléfono', 'dirección', 'ubicación', 'ext.', 'email'
            ]
        ];

        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($content, $keyword)) {
                    return $category;
                }
            }
        }

        return 'informacion_general';
    }

    /**
     * Generar keywords inteligentes
     */
    private function generateIntelligentKeywords(array $section, array $metadata): array
    {
        try {
            $prompt = "Extrae 8-12 palabras clave relevantes del siguiente contenido universitario para facilitar búsquedas:

Título: {$section['title']}
Contenido: " . substr($section['content'], 0, 800) . "

Incluye:
- Términos específicos del trámite/servicio
- Sinónimos que los usuarios podrían buscar
- Nombres de dependencias mencionadas
- Palabras relacionadas con la UAN

Responde solo con las palabras separadas por comas.";

            $response = $this->ollamaService->generateResponse($prompt, [
                'temperature' => 0.2,
                'max_tokens' => 150
            ]);

            if ($response['success']) {
                $keywords = array_map('trim', explode(',', $response['response']));
                return array_filter($keywords, fn($k) => strlen($k) > 2 && strlen($k) < 50);
            }

        } catch (\Exception $e) {
            Log::warning('Error generando keywords con IA: ' . $e->getMessage());
        }

        // Fallback a extracción básica
        return $this->extractBasicKeywords($section['title'] . ' ' . $section['content']);
    }

    /**
     * Extracción básica de keywords
     */
    private function extractBasicKeywords(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\s+/', $text);
        $keywords = [];

        $stopWords = [
            'de', 'la', 'el', 'en', 'a', 'y', 'que', 'es', 'se', 'con', 'por', 'para',
            'del', 'los', 'las', 'un', 'una', 'su', 'sus', 'al', 'le', 'da', 'muy'
        ];

        foreach ($words as $word) {
            $word = trim($word, '.,;:()[]{}¿?¡!#*-');
            if (strlen($word) > 3 && !in_array($word, $stopWords) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }

        // Agregar keywords específicos relevantes
        $specificKeywords = ['uan', 'universidad', 'trámite', 'servicio', 'estudiante', 'docente'];
        $keywords = array_merge($keywords, $specificKeywords);

        return array_values(array_unique(array_slice($keywords, 0, 12)));
    }

    /**
     * Resto de métodos auxiliares
     */
    private function determineDepartment(array $section, array $metadata): string
    {
        $content = strtolower($section['title'] . ' ' . $section['content']);

        $departmentPatterns = [
            'SA' => ['secretaría académica', 'administración escolar', 'servicios académicos'],
            'DGS' => ['sistemas', 'dirección general de sistemas'],
            'BIBLIOTECA' => ['biblioteca'],
            'VINCULACION' => ['vinculación', 'egresados'],
            'INVESTIGACION' => ['investigación', 'posgrado']
        ];

        foreach ($departmentPatterns as $dept => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    return $dept;
                }
            }
        }

        return 'GENERAL';
    }

    private function extractContactInformation(string $content): string
    {
        $contactInfo = '';

        // Extraer teléfonos
        if (preg_match_all('/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/', $content, $phones)) {
            foreach ($phones[0] as $phone) {
                $contactInfo .= "Tel: " . trim($phone) . "\n";
            }
        }

        // Extraer emails
        if (preg_match_all('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content, $emails)) {
            foreach ($emails[0] as $email) {
                $contactInfo .= "Email: " . trim($email) . "\n";
            }
        }

        return trim($contactInfo);
    }

    private function calculatePriority(array $section, array $metadata, string $category): string
    {
        // Alta prioridad para trámites y servicios críticos
        if (in_array($category, ['tramites_estudiantes', 'tramites_docentes', 'servicios_academicos'])) {
            return 'high';
        }

        // Prioridad media para directorio y eventos
        if (in_array($category, ['directorio', 'eventos'])) {
            return 'medium';
        }

        return 'low';
    }

    private function validateSection(array $section, array $options): bool
    {
        // Validar longitud mínima
        if (strlen($section['content']) < $options['min_section_length']) {
            return false;
        }

        // Validar que tiene título
        if (empty($section['title']) || strlen($section['title']) < 3) {
            return false;
        }

        return true;
    }

    private function saveToKnowledgeBase(array $data, string $sourceName): array
    {
        try {
            $existing = DB::table('knowledge_base')
                ->where('title', $data['title'])
                ->where('created_by', $sourceName)
                ->first();

            $baseData = array_merge($data, [
                'is_active' => true,
                'created_by' => $sourceName,
                'updated_at' => now()
            ]);

            if ($existing) {
                DB::table('knowledge_base')
                    ->where('id', $existing->id)
                    ->update($baseData);

                return ['created' => false, 'updated' => true];
            } else {
                $baseData['created_at'] = now();
                DB::table('knowledge_base')->insert($baseData);

                return ['created' => true, 'updated' => false];
            }

        } catch (\Exception $e) {
            Log::error('Error guardando sección en base de conocimientos: ' . $e->getMessage());
            return ['created' => false, 'updated' => false];
        }
    }

    /**
     * Procesar archivo Markdown desde ruta
     */
    public function processMarkdownFile(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Archivo no encontrado: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $options['source_name'] = 'markdown_file_' . basename($filePath, '.md');

        return $this->processMarkdownContent($content, $options);
    }

    /**
     * Obtener estadísticas de procesamiento
     */
    public function getProcessingStats(): array
    {
        return [
            'total_markdown_entries' => DB::table('knowledge_base')
                ->where('created_by', 'LIKE', 'markdown_%')
                ->count(),
            'by_category' => DB::table('knowledge_base')
                ->where('created_by', 'LIKE', 'markdown_%')
                ->groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->pluck('count', 'category')
                ->toArray(),
            'recent_imports' => DB::table('knowledge_base')
                ->where('created_by', 'LIKE', 'markdown_%')
                ->where('created_at', '>', now()->subDays(7))
                ->count()
        ];
    }
}
