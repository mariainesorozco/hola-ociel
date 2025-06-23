# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

¡Hola Ociel! is a Laravel 8.x-based AI chatbot system for Universidad Autónoma de Nayarit (UAN) that provides conversational information services to students and staff. The system integrates with Ollama for local AI responses, optional Qdrant for vector search, and includes comprehensive knowledge base management for university-specific content.

## Architecture

### Core Service Layer
The system follows a service-oriented architecture with dependency injection:

- **OllamaService**: Primary AI model integration using local Ollama instance. Contains optimized prompts for conversational responses (not structured markdown). Method `generateOcielResponse()` should be used instead of generic `generateResponse()`.
- **EnhancedPromptService**: Advanced prompt engineering with query classification and specialized response generation. Uses OllamaService internally.
- **KnowledgeBaseService**: Base knowledge retrieval from MySQL database with semantic search capabilities
- **EnhancedKnowledgeBaseService**: Extended version with vector search via Qdrant integration
- **EnhancedQdrantVectorService**: Vector database operations for semantic similarity
- **MarkdownProcessingService**: Content import and processing from markdown files
- **PiidaScrapingService**: External content scraping from PIIDA system (piida.uan.mx)
- **NotionIntegrationService**: Notion workspace integration for institutional content
- **GhostIntegrationService**: Ghost CMS integration for content management

### Request Flow Architecture
1. Request hits `EnhancedChatController@chat`
2. Rate limiting and validation applied
3. Context retrieved via `KnowledgeBaseService->searchRelevantContent()`
4. Content cleaned to remove markdown formatting before AI processing
5. `EnhancedPromptService->generateProfessionalResponse()` called
6. Multiple cleaning layers applied to ensure conversational output
7. Response returned with confidence metrics

### Critical Response Format Requirements
The system is designed to provide **conversational responses only**, never structured markdown. All prompts explicitly prohibit formats like:
- `📋 Información encontrada:`
- `### Descripción`
- `**Campo:** valor`

Content cleaning happens at multiple levels: knowledge service, prompt service, and OllamaService.

### API Structure
- Primary endpoint: `/api/v1/chat` and `/api/v2/chat` (both use EnhancedChatController)
- Debug routes: `/api/debug/test`, `/api/debug/knowledge-test`, `/api/debug/stats`, `/api/debug/chat-test`
- Health monitoring: `/api/health` with advanced service status
- University info: `/api/university-info` for public institutional data

### Database Schema
- `knowledge_base`: Content storage with categories, departments, user_types, and is_active flags
- `chat_interactions`: Full conversation logging with confidence metrics and metadata
- `departments`: University department configuration with specialized agent flags
- `uan_configurations`: System-wide configuration storage

## Development Commands

### Laravel Artisan Commands
- `php artisan serve` - Start development server
- `php artisan migrate` - Run database migrations
- `php artisan test` - Run PHPUnit tests
- `php artisan tinker` - Interactive shell

### Custom Artisan Commands
- `php artisan ociel:status` - Check system status and service health
- `php artisan ociel:import-markdown` - Import markdown content to knowledge base
- `php artisan ociel:index-knowledge` - Index content for search
- `php artisan ociel:diagnose-ollama` - Diagnose Ollama service issues
- `php artisan ociel:debug-qdrant` - Debug Qdrant vector database
- `php artisan ociel:manage-piida` - Manage PIIDA content integration
- `php artisan ociel:sync-notion` - Sync content from Notion
- `php artisan ociel:scrape-web` - Scrape web content
- `php artisan ociel:test-semantic` - Test semantic search functionality

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
- `config/ollama.php` - Ollama AI service settings (models, endpoints, timeouts)
- `config/services.php` - External service configurations including:
  - PIIDA categories and URL mappings
  - Notion integration settings  
  - Web scraping allowed domains and rate limits
- `config/knowledge_weights.php` - Knowledge base search weighting algorithms
- `config/search_optimization.php` - Search optimization and ranking parameters
- `config/keyword_mappings.php` - Keyword synonyms and mappings for better search

### Environment Requirements
The system requires:
- **Ollama service**: Local instance (default: localhost:11434) with models `mistral:7b`, `llama3.2:3b`, `nomic-embed-text`
- **Qdrant vector database**: Optional but recommended for semantic search (enhances context retrieval)
- **MySQL database**: Primary data storage for knowledge base and interactions
- **PHP 8.0+** with Laravel 8.x framework

### External Service Dependencies
- **PIIDA System**: piida.uan.mx for institutional procedure data
- **Notion API**: Optional workspace integration for content management
- **Ghost CMS**: Optional content management integration

## Content Management

### Knowledge Base Organization
Content hierarchy follows UAN institutional structure:
- **Categories**: `tramites_estudiantes`, `servicios_academicos`, `oferta_educativa`, `directorio`, `informacion_general`
- **Departments**: Maps to actual UAN departments (DGS, DGSA, etc.)
- **User Types**: `student`, `employee`, `public` with differentiated content access
- **Content Types**: Markdown-based with metadata for categorization

### Content Import Workflows
- **Markdown import**: `php artisan ociel:import-markdown` for bulk content loading
- **PIIDA scraping**: `php artisan ociel:manage-piida` for external system synchronization
- **Notion sync**: `php artisan ociel:sync-notion` for workspace content integration
- **Storage locations**: 
  - Source: `app/knowledge/markdown/` and `storage/app/knowledge/markdown/`
  - Processed: Database with vector embeddings if Qdrant available

## Service Health Monitoring

Use `php artisan ociel:status` to check:
- Database connectivity
- Ollama service status
- Qdrant vector database health
- Knowledge base content statistics
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
2. Verify content cleaning in `KnowledgeBaseService->searchRelevantContent()`
3. Clear cache: `php artisan cache:clear`
4. Check vector database content for embedded markdown formatting

**AI Service Issues**:
- Ollama connection failed: Verify service at `localhost:11434`, check models with `ollama list`
- Low confidence responses: Check knowledge base content quality with `/api/debug/stats`
- Empty responses: Verify active content in knowledge_base table

**Content Management Issues**:
- Import failures: Check markdown file format and permissions in knowledge directories
- Missing search results: Verify categories and departments match user query context
- PIIDA sync errors: Check network connectivity to piida.uan.mx and rate limiting

### Performance Monitoring
- Response times logged in `chat_interactions` table
- Service health via `/api/health` endpoint with detailed component status
- Cache performance metrics available through Laravel telescope (if installed)