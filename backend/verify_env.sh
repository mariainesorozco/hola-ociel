#!/bin/bash

# 🔍 Script de Verificación Específica para tu .env
# Verificaciones paso a paso basadas en tu configuración actual

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() {
    echo -e "\n${BLUE}==== $1 ====${NC}\n"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_header "VERIFICACIÓN ESPECÍFICA PARA TU CONFIGURACIÓN"

# =================
# 1. VERIFICAR BASE DE DATOS MYSQL
# =================
print_header "1. VERIFICANDO BASE DE DATOS MYSQL"

print_info "Verificando conexión a MySQL con tu configuración..."

# Verificar que MySQL está corriendo
if pgrep mysql > /dev/null || pgrep mysqld > /dev/null; then
    print_success "MySQL está corriendo"
else
    print_error "MySQL no está corriendo. Inicia MySQL primero."
    echo "macOS: brew services start mysql"
    echo "Ubuntu/Debian: sudo systemctl start mysql"
    echo "Windows: net start mysql"
fi

# Verificar conexión con credenciales específicas
print_info "Probando conexión con base de datos 'hola_ociel'..."
if mysql -h 127.0.0.1 -P 3306 -u ociel_user -pociel_password_2025 -e "SELECT 1;" hola_ociel 2>/dev/null; then
    print_success "Conexión a base de datos exitosa"
else
    print_error "No se puede conectar a la base de datos"
    print_info "Necesitas crear la base de datos y usuario"
    echo ""
    echo "Ejecuta estos comandos en MySQL como root:"
    echo "mysql -u root -p"
    echo "CREATE DATABASE IF NOT EXISTS hola_ociel;"
    echo "CREATE USER IF NOT EXISTS 'ociel_user'@'localhost' IDENTIFIED BY 'ociel_password_2025';"
    echo "GRANT ALL PRIVILEGES ON hola_ociel.* TO 'ociel_user'@'localhost';"
    echo "FLUSH PRIVILEGES;"
fi

# =================
# 2. VERIFICAR OLLAMA
# =================
print_header "2. VERIFICANDO OLLAMA"

print_info "Verificando Ollama en http://localhost:11434..."
if curl -s http://localhost:11434/ > /dev/null; then
    print_success "Ollama está disponible"
    
    # Verificar modelos específicos de tu configuración
    print_info "Verificando modelos configurados..."
    
    if curl -s http://localhost:11434/api/tags | grep -q "mistral:7b"; then
        print_success "Modelo primary disponible: mistral:7b"
    else
        print_warning "Modelo primary no encontrado: mistral:7b"
        echo "Ejecuta: ollama pull mistral:7b"
    fi
    
    if curl -s http://localhost:11434/api/tags | grep -q "llama3.2:3b"; then
        print_success "Modelo secondary disponible: llama3.2:3b"
    else
        print_warning "Modelo secondary no encontrado: llama3.2:3b"
        echo "Ejecuta: ollama pull llama3.2:3b"
    fi
    
    if curl -s http://localhost:11434/api/tags | grep -q "nomic-embed-text"; then
        print_success "Modelo embedding disponible: nomic-embed-text"
    else
        print_error "Modelo embedding no encontrado: nomic-embed-text"
        echo "CRÍTICO: Ejecuta: ollama pull nomic-embed-text"
    fi
    
else
    print_error "Ollama no está disponible"
    echo "Descarga e instala desde: https://ollama.com/"
    echo "Después ejecuta: ollama serve"
fi

# =================
# 3. VERIFICAR QDRANT
# =================
print_header "3. VERIFICANDO QDRANT"

print_info "Verificando Qdrant en http://localhost:6333..."
if curl -s http://localhost:6333/ > /dev/null; then
    print_success "Qdrant está disponible"
    
    # Verificar colección específica
    if curl -s http://localhost:6333/collections/uan_piida_knowledge 2>/dev/null | grep -q '"status"'; then
        print_success "Colección 'uan_piida_knowledge' existe"
    else
        print_warning "Colección 'uan_piida_knowledge' no existe (se creará automáticamente)"
    fi
    
else
    print_error "Qdrant no está disponible"
    echo "Inicia con Docker: docker run -p 6333:6333 qdrant/qdrant"
    echo "O descarga desde: https://github.com/qdrant/qdrant/releases"
fi

# =================
# 4. VERIFICAR PIIDA
# =================
print_header "4. VERIFICANDO CONECTIVIDAD CON PIIDA"

print_info "Verificando acceso a https://piida.uan.mx..."
if curl -s -I https://piida.uan.mx/servicios | head -1 | grep -q "200"; then
    print_success "PIIDA accesible"
else
    print_warning "PIIDA no accesible (puede estar temporalmente no disponible)"
fi

# =================
# 5. VERIFICAR LARAVEL
# =================
print_header "5. VERIFICANDO CONFIGURACIÓN DE LARAVEL"

print_info "Limpiando y recargando configuración..."
php artisan config:clear > /dev/null 2>&1
php artisan cache:clear > /dev/null 2>&1

print_info "Verificando comandos de Ociel..."
if php artisan list | grep -q "ociel:piida-diagnose"; then
    print_success "Comandos Ociel disponibles"
else
    print_error "Comandos Ociel no encontrados"
    echo "Asegúrate de que el código está actualizado y ejecuta:"
    echo "composer dump-autoload"
fi

# =================
# 6. VERIFICAR MIGRACIONES
# =================
print_header "6. VERIFICANDO MIGRACIONES"

print_info "Verificando estado de migraciones..."
if php artisan migrate:status > /dev/null 2>&1; then
    print_success "Conexión de migración exitosa"
    
    # Verificar migraciones específicas
    if php artisan migrate:status | grep -q "knowledge_base"; then
        print_success "Tabla knowledge_base existe"
    else
        print_warning "Tabla knowledge_base no existe"
        echo "Ejecuta: php artisan migrate"
    fi
    
    if php artisan migrate:status | grep -q "chat_interactions"; then
        print_success "Tabla chat_interactions existe"
    else
        print_warning "Tabla chat_interactions no existe"
        echo "Ejecuta: php artisan migrate"
    fi
    
else
    print_error "Error en migraciones - revisa conexión a base de datos"
fi

# =================
# 7. RESUMEN Y PRÓXIMOS PASOS
# =================
print_header "RESUMEN Y PRÓXIMOS PASOS"

echo "Basado en tu configuración actual:"
echo ""

echo "✅ CONFIGURACIÓN VERIFICADA:"
echo "   - Base de datos: hola_ociel"
echo "   - Usuario: ociel_user"
echo "   - Ollama: localhost:11434"
echo "   - Qdrant: localhost:6333"
echo "   - PIIDA: piida.uan.mx"
echo ""

echo "🚀 COMANDOS PARA CONTINUAR:"
echo ""

echo "1. Si las migraciones no están ejecutadas:"
echo "   php artisan migrate"
echo ""

echo "2. Si Ollama necesita modelos:"
echo "   ollama pull mistral:7b"
echo "   ollama pull llama3.2:3b"
echo "   ollama pull nomic-embed-text"
echo ""

echo "3. Si Qdrant no está corriendo:"
echo "   docker run -d -p 6333:6333 qdrant/qdrant"
echo ""

echo "4. Poblar base de conocimientos:"
echo "   php artisan ociel:piida-manage scrape --force"
echo ""

echo "5. Crear índice vectorial:"
echo "   php artisan ociel:piida-manage index --force"
echo ""

echo "6. Probar el sistema:"
echo "   php artisan serve"
echo "   # Abrir: http://localhost:8000/ociel"

print_header "🎉 VERIFICACIÓN COMPLETADA"
echo "Revisa los mensajes anteriores y ejecuta los comandos sugeridos."
