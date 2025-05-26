#!/bin/bash
echo "🔍 Diagnóstico de Ollama - ¡Hola Ociel!"
echo "=================================="

echo "1. Verificando servicio Ollama..."
if curl -s http://localhost:11434/api/version > /dev/null; then
    echo "✅ Ollama está respondiendo"
    curl -s http://localhost:11434/api/version | jq .
else
    echo "❌ Ollama no responde"
    echo "Intentando reiniciar..."
    ollama serve &
    sleep 5
fi

echo ""
echo "2. Verificando modelos..."
curl -s http://localhost:11434/api/tags | jq '.models[].name'

echo ""
echo "3. Verificando recursos del sistema..."
echo "CPU:"
top -l 1 | grep "CPU usage"
echo "Memoria:"
vm_stat | grep "Pages free"

echo ""
echo "4. Probando generación simple..."
curl -X POST http://localhost:11434/api/generate \
  -H "Content-Type: application/json" \
  -d '{
    "model": "mistral:7b",
    "prompt": "Responde solo: La UAN está en",
    "stream": false,
    "options": {
      "num_predict": 20,
      "temperature": 0.1
    }
  }' | jq .

echo ""
echo "5. Verificando logs recientes..."
if [ -f "/var/log/ollama.log" ]; then
    echo "Últimas 5 líneas del log:"
    tail -5 /var/log/ollama.log
else
    echo "No se encontró archivo de log específico"
fi
