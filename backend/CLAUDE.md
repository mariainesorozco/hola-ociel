# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

¡Hola Ociel! is a Laravel 8.x-based AI chatbot system for Universidad Autónoma de Nayarit (UAN) that provides conversational information services to students and staff. The system integrates with Ollama for local AI responses and uses Qdrant vector database exclusively with Notion-sourced content for semantic search.

## Architecture

### Core Service Layer
The system follows a service-oriented architecture with dependency injection:

- **OllamaService**: Primary AI model integration using local Ollama instance. Contains optimized prompts for conversational responses (not structured markdown). Method `generateOcielResponse()` should be used instead of generic `generateResponse()`.
- **EnhancedPromptService**: Advanced prompt engineering with query classification and specialized response generation. Uses OllamaService internally.
- **EnhancedQdrantVectorService**: Vector database operations for semantic similarity. Contains `searchNotionServices()` for Notion-specific queries and `indexNotionContent()` for data synchronization.
- **SimpleNotionService**: Direct Notion API integration for synchronizing content from specific databases to Qdrant vector store.
- **GeminiService**: Fallback AI service when Ollama is unavailable.

### Request Flow Architecture
1. Request hits `EnhancedChatController@chat`
2. Rate limiting and validation applied
3. Context retrieved via `EnhancedQdrantVectorService->searchNotionServices()`
4. Content processed from Notion-sourced data in Qdrant
5. `EnhancedPromptService->generateProfessionalResponse()` called
6. Multiple cleaning layers applied to ensure conversational output
7. Response returned with confidence metrics

### Data Flow Architecture
**Notion → Qdrant → Chat Responses**
1. **Synchronization**: `SimpleNotionService` reads from 4 Notion databases (Finanzas, Académica, RRHH, Servicios Tecnológicos)
2. **Indexing**: Content processed and stored as vectors in Qdrant `ociel_knowledge` collection with structured metadata
3. **Queries**: Widget searches semantically in Qdrant using `searchNotionServices()` method
4. **Responses**: Ociel provides conversational answers based exclusively on Notion data

### Critical Response Format Requirements
The system is designed to provide **conversational responses only**, never structured markdown. All prompts explicitly prohibit formats like:
- `📋 Información encontrada:`
- `### Descripción`
- `**Campo:** valor`

Content cleaning happens at multiple levels: Qdrant service, prompt service, and OllamaService.

### API Structure
- Primary endpoint: `/api/v1/chat` and `/api/v2/chat` (both use EnhancedChatController)
- Debug routes: `/api/debug/test`, `/api/debug/knowledge-test`, `/api/debug/stats`, `/api/debug/chat-test`
- Health monitoring: `/api/health` with advanced service status
- University info: `/api/university-info` for public institutional data
- **Widget Interface**: `/ociel` and `/widget` - Direct web interface for chat functionality

### Database Schema
- **Qdrant Vector Database** (`ociel_knowledge` collection): Primary content storage with embeddings and structured metadata from Notion
- `chat_interactions`: Full conversation logging with confidence metrics and metadata
- `departments`: University department configuration with specialized agent flags
- `uan_configurations`: System-wide configuration storage

### Notion Integration
- **4 Configured Databases**:
  - Secretaría de Finanzas: `NOTION_FINANZAS_DB_ID`
  - Secretaría Académica: `NOTION_ACADEMICA_DB_ID`
  - Dirección de Nómina y Recursos Humanos: `NOTION_RECURSOS_HUMANOS_DB_ID`
  - Dirección de Infraestructura y Servicios Tecnológicos: `NOTION_SERVICIOS_TECNOLOGICOS_DB_ID`

## Development Commands

### Laravel Artisan Commands
- `php artisan serve` - Start development server
- `php artisan migrate` - Run database migrations
- `php artisan test` - Run PHPUnit tests
- `php artisan tinker` - Interactive shell

### Current Active Commands
- `php artisan ociel:status` - Check system status and service health
- `php artisan ociel:sync-notion {database?} {--all}` - Sync content from Notion databases to Qdrant
- `php artisan ociel:diagnose-ollama` - Diagnose Ollama service issues
- `php artisan ociel:debug-qdrant` - Debug Qdrant vector database
- `php artisan ociel:test-semantic` - Test semantic search functionality
- `php artisan ociel:cleanup-vector-notion` - Clean vector database to maintain only Notion content

### ❌ Deprecated/Removed Commands (No longer needed)
- ~~`php artisan ociel:import-markdown`~~ - Replaced by direct Notion sync
- ~~`php artisan ociel:index-knowledge`~~ - No longer uses knowledge_base table
- ~~`php artisan ociel:manage-piida`~~ - PiiDA integration removed
- ~~`php artisan ociel:scrape-web`~~ - Web scraping removed

### Frontend Build Commands
- `npm run dev` - Development build
- `npm run watch` - Watch for changes
- `npm run prod` - Production build

### Testing
- `php artisan test` - Run all tests
- `./vendor/bin/phpunit` - Direct PHPUnit execution
- Tests located in `tests/Feature/` and `tests/Unit/`

## Key Configuration Files

### Service Configuration
- `config/services.php` - External service configurations including:
  - **Notion integration settings** with database IDs for 4 departmental databases
  - **Ollama configuration** (models, endpoints, timeouts)
  - **Qdrant vector database** settings
  - **Gemini AI fallback** configuration

### Environment Requirements
The system requires:
- **Ollama service**: Local instance (default: localhost:11434) with models `solar:10.7b`, `llama3.2:3b`, `nomic-embed-text`
- **Qdrant vector database**: **REQUIRED** - Primary content storage for semantic search
- **MySQL database**: Conversation logging and system configuration
- **PHP 8.0+** with Laravel 8.x framework
- **Notion API**: Content source - requires valid API key and database access

### External Service Dependencies
- **Notion API**: **PRIMARY** - All content sourced from 4 departmental databases
- **Gemini AI**: **FALLBACK** - Secondary AI service when Ollama unavailable

### ❌ Removed Dependencies (No longer used)
- ~~**PIIDA System**~~ - External scraping removed
- ~~**Ghost CMS**~~ - Content management integration removed
- ~~**MySQL knowledge_base table**~~ - Replaced by Qdrant vector storage

## Content Management

### Notion-Based Knowledge Organization
Content structure follows UAN institutional departments:
- **Departments**: 
  - Secretaría de Finanzas
  - Secretaría Académica  
  - Dirección de Nómina y Recursos Humanos
  - Dirección de Infraestructura y Servicios Tecnológicos
- **User Types**: `student`, `employee`, `public` with differentiated content access
- **Content Types**: Notion pages with structured properties (título, descripción, categoría, costo, modalidad, usuarios, etc.)

### Content Synchronization Workflows
- **Primary**: `php artisan ociel:sync-notion {database}` - Sync specific Notion database to Qdrant
- **Bulk**: `php artisan ociel:sync-notion --all` - Sync all 4 departmental databases
- **Automated**: Scheduled sync via `app/Console/Kernel.php` (every 4-6 hours)
- **Storage**: Direct Qdrant vector storage with structured metadata - no intermediate files

## Service Health Monitoring

Use `php artisan ociel:status` to check:
- Database connectivity
- Ollama service status
- Qdrant vector database health
- Notion API connectivity
- Gemini AI fallback status
- API endpoint availability

## Debugging and Troubleshooting

### Debug API Endpoints
- `GET /api/debug/test` - Basic API connectivity and environment check
- `GET /api/debug/knowledge-test?q=query` - Test knowledge base search with detailed results
- `GET /api/debug/stats` - Comprehensive system statistics including content counts and service health
- `POST /api/debug/chat-test` - End-to-end chat functionality test with request body: `{"message": "test query", "user_type": "student"}`

### Specialized Diagnostic Commands
- `php artisan ociel:diagnose-ollama` - Comprehensive Ollama service diagnostics
- `php artisan ociel:debug-qdrant` - Vector database connection and indexing status
- `php artisan ociel:test-semantic` - Semantic search functionality validation

### Common Issues and Solutions

**Structured Markdown Responses**: If system returns `📋 Información encontrada:` format instead of conversational text:
1. Check that `EnhancedPromptService` calls `generateOcielResponse()` not `generateResponse()`
2. Verify content cleaning in `EnhancedQdrantVectorService->searchNotionServices()`
3. Clear cache: `php artisan cache:clear`
4. Check vector database content for embedded markdown formatting

**AI Service Issues**:
- Ollama connection failed: Verify service at `localhost:11434`, check models with `ollama list`
- Low confidence responses: Check Notion content sync status with `php artisan ociel:sync-notion --all`
- Empty responses: Verify Qdrant contains Notion data with `php artisan ociel:debug-qdrant`

**Content Synchronization Issues**:
- Sync failures: Check Notion API key validity and database permissions
- Missing search results: Verify Notion databases contain active content
- Outdated responses: Run manual sync with `php artisan ociel:sync-notion --all`

### ❌ Deprecated Troubleshooting (No longer applicable)
- ~~PIIDA sync errors~~ - PIIDA integration removed
- ~~Markdown import failures~~ - Direct file import removed
- ~~knowledge_base table issues~~ - MySQL table no longer used

### Performance Monitoring
- Response times logged in `chat_interactions` table
- Service health via `/api/health` endpoint with detailed component status
- Qdrant vector search performance metrics
- Notion sync frequency and success rates in scheduled tasks

## Simplified Architecture Summary

**Current Active Flow**:
```
Notion (4 DBs) → SimpleNotionService → Qdrant (ociel_knowledge) → EnhancedChatController → Widget
```

**Widget Configuration**:
- **Location**: `/public/ociel/index.php` - Complete HTML/CSS/JS implementation
- **API Endpoint**: Uses `/api/v1/chat` for all queries with session management
- **Access URLs**: `http://localhost:8000/ociel` or `http://localhost:8000/widget`
- **Features**: 
  - Real-time chat interface with conversation history
  - Session-based follow-up questions and context tracking
  - Notion-sourced responses with confidence scoring
  - UAN-specific query suggestions
  - Message timestamps and conversation continuity
  - "Nueva conversación" functionality to reset chat

**Removed Components**:
- ❌ MySQL knowledge_base table
- ❌ PIIDA scraping and integration
- ❌ Ghost CMS integration  
- ❌ Markdown file imports
- ❌ Web scraping services
- ❌ Multiple knowledge services (now unified in Qdrant)
- ❌ File upload functionality in widget (now focused on chat only)