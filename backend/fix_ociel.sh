#!/bin/bash

echo "🔧 Diagnóstico y corrección de ¡Hola Ociel!"
echo "================================================"

# 1. Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo "❌ Error: No se encuentra el archivo artisan. Ejecuta desde el directorio backend/"
    exit 1
fi

echo "✅ Directorio correcto detectado"

# 2. Verificar configuración de base de datos
echo ""
echo "📊 Verificando configuración de base de datos..."

if [ ! -f ".env" ]; then
    echo "❌ Archivo .env no encontrado. Copiando desde .env.example..."
    cp .env.example .env
    echo "📝 Archivo .env creado. Configura tu base de datos."
fi

# 3. Instalar dependencias si no existen
if [ ! -d "vendor" ]; then
    echo "📦 Instalando dependencias de Composer..."
    composer install --no-dev --optimize-autoloader
fi

# 4. Generar clave de aplicación
echo ""
echo "🔑 Generando clave de aplicación..."
php artisan key:generate

# 5. Ejecutar migraciones
echo ""
echo "🗄️ Ejecutando migraciones de base de datos..."
php artisan migrate --force

# 6. Ejecutar seeders
echo ""
echo "🌱 Ejecutando seeders..."
php artisan db:seed --force

# 7. Limpiar caché
echo ""
echo "🧹 Limpiando caché..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 8. Crear directorio para el widget si no existe
echo ""
echo "📁 Configurando directorio del widget..."
mkdir -p public/ociel

# 9. Verificar permisos
echo ""
echo "🔐 Configurando permisos..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# 10. Mostrar rutas disponibles
echo ""
echo "🛣️ Rutas de API disponibles:"
php artisan route:list --path=api

echo ""
echo "✅ Configuración completada!"
echo ""
echo "🚀 Para iniciar el servidor:"
echo "   php artisan serve"
echo ""
echo "🧪 Para probar la API:"
echo "   curl -X POST http://localhost:8000/api/v1/chat \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -H 'Accept: application/json' \\"
echo "     -d '{\"message\": \"Hola Ociel\", \"user_type\": \"student\"}'"
