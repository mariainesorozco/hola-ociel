<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class GetNotionDatabaseIds extends Command
{
    protected $signature = 'notion:get-ids';
    protected $description = 'Obtener los IDs de todas las bases de datos en tu workspace de Notion';

    public function handle()
    {
        $apiKey = config('services.notion.api_key');

        if (!$apiKey) {
            $this->error('❌ No se encontró NOTION_API_KEY en el archivo .env');
            return 1;
        }

        $this->info('🔍 Buscando bases de datos en Notion...');
        $this->newLine();

        $client = new Client([
            'base_uri' => 'https://api.notion.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Notion-Version' => '2022-06-28',
                'Content-Type' => 'application/json'
            ]
        ]);

        try {
            // Buscar todas las bases de datos
            $response = $client->post('search', [
                'json' => [
                    'filter' => [
                        'property' => 'object',
                        'value' => 'database'
                    ],
                    'page_size' => 100
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $databases = $data['results'] ?? [];

            if (empty($databases)) {
                $this->warn('⚠️  No se encontraron bases de datos.');
                $this->info('Asegúrate de que la integración tenga acceso a las bases de datos en Notion.');
                return 0;
            }

            $this->info('📊 Bases de datos encontradas:');
            $this->newLine();

            $envSuggestions = [];

            foreach ($databases as $database) {
                $title = $database['title'][0]['plain_text'] ?? 'Sin título';
                $id = $database['id'];
                $url = $database['url'] ?? '';

                $this->line("📁 <info>{$title}</info>");
                $this->line("   ID: <comment>{$id}</comment>");
                if ($url) {
                    $this->line("   URL: {$url}");
                }

                // Sugerir variable de entorno basada en el título
                $envName = $this->suggestEnvName($title);
                if ($envName) {
                    $envSuggestions[] = "{$envName}={$id}";
                }

                $this->newLine();
            }

            // Mostrar sugerencias para .env
            if (!empty($envSuggestions)) {
                $this->info('💡 Sugerencias para tu archivo .env:');
                $this->newLine();

                foreach ($envSuggestions as $suggestion) {
                    $this->line($suggestion);
                }

                $this->newLine();
                $this->info('Copia estas líneas a tu archivo .env para configurar las bases de datos.');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error al conectar con Notion: ' . $e->getMessage());

            if (strpos($e->getMessage(), '401') !== false) {
                $this->warn('La API key parece ser inválida. Verifica tu NOTION_API_KEY.');
            }

            return 1;
        }

        return 0;
    }

    private function suggestEnvName(string $title): ?string
    {
        $title = strtolower($title);

        $mappings = [
            'finanza' => 'NOTION_FINANZAS_DB_ID',
            'academ' => 'NOTION_ACADEMICA_DB_ID',
            'academica' => 'NOTION_ACADEMICA_DB_ID',
            'recurso' => 'NOTION_RECURSOS_HUMANOS_DB_ID',
            'humano' => 'NOTION_RECURSOS_HUMANOS_DB_ID',
            'rrhh' => 'NOTION_RECURSOS_HUMANOS_DB_ID',
            'tecnolog' => 'NOTION_SERVICIOS_TECNOLOGICOS_DB_ID',
            'sistema' => 'NOTION_SERVICIOS_TECNOLOGICOS_DB_ID',
            'servicio' => 'NOTION_SERVICIOS_DB_ID'
        ];

        foreach ($mappings as $keyword => $envName) {
            if (strpos($title, $keyword) !== false) {
                return $envName;
            }
        }

        return null;
    }
}

// Script independiente para obtener IDs (puedes ejecutarlo con php artisan tinker)
function getNotionDatabaseIds() {
    $apiKey = config('services.notion.api_key');

    if (!$apiKey) {
        echo "❌ No API key found\n";
        return;
    }

    $client = new \GuzzleHttp\Client([
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Notion-Version' => '2022-06-28',
            'Content-Type' => 'application/json'
        ]
    ]);

    try {
        $response = $client->post('https://api.notion.com/v1/search', [
            'json' => [
                'filter' => [
                    'property' => 'object',
                    'value' => 'database'
                ]
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        echo "\n🔍 BASES DE DATOS ENCONTRADAS:\n\n";

        foreach ($data['results'] as $db) {
            $title = $db['title'][0]['plain_text'] ?? 'Sin título';
            echo "📁 {$title}\n";
            echo "   ID: {$db['id']}\n\n";
        }

    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Instrucciones para obtener el ID de una base de datos específica desde la URL de Notion:
/*
 * CÓMO OBTENER EL ID DE UNA BASE DE DATOS:
 *
 * 1. Abre la base de datos en Notion
 * 2. La URL se verá algo así:
 *    https://www.notion.so/workspace/Base-de-Datos-de-Finanzas-1234567890abcdef1234567890abcdef?v=...
 *
 * 3. El ID es la parte después del nombre y antes de "?v=":
 *    1234567890abcdef1234567890abcdef
 *
 * 4. Si copias la URL completa, puedes extraer el ID con este regex:
 *    /([a-f0-9]{32})/
 *
 * IMPORTANTE: Asegúrate de compartir cada base de datos con tu integración:
 * - Abre la base de datos
 * - Click en "..." (tres puntos) arriba a la derecha
 * - "Add connections"
 * - Busca y selecciona tu integración "Hola Ociel Integration"
 */
