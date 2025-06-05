<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Hola Ociel! - Asistente Virtual UAN v2.0</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .chat-container {
            width: 100%;
            max-width: 480px;
            height: 650px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.12);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .chat-header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .chat-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            opacity: 0.1;
        }

        .ociel-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 22px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .header-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 4px;
        }

        .header-subtitle {
            font-size: 13px;
            opacity: 0.9;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: auto;
            animation: pulse 2s infinite;
            position: relative;
            z-index: 1;
        }

        .status-indicator.online { background: #10b981; }
        .status-indicator.connecting { background: #f59e0b; }
        .status-indicator.offline { background: #ef4444; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: #f8fafc;
            position: relative;
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .message {
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .message-content {
            max-width: 85%;
            padding: 16px 20px;
            border-radius: 20px;
            word-wrap: break-word;
            line-height: 1.6;
            font-size: 14px;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 8px;
        }

        .message.ociel .message-content {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* === CONTENIDO DE OCIEL === */
        .message.ociel .message-content h3 {
            color: #1e40af;
            font-size: 16px;
            margin: 0 0 12px 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            line-height: 1.4;
        }

        .message.ociel .message-content h4 {
            color: #1d4ed8;
            font-size: 14px;
            margin: 16px 0 8px 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dbeafe;
            padding-bottom: 4px;
        }

        .message.ociel .message-content p {
            margin: 0 0 12px 0;
            color: #374151;
            line-height: 1.6;
            font-size: 14px;
        }

        .message.ociel .message-content p:last-child {
            margin-bottom: 0;
        }

        .message.ociel .message-content strong {
            font-weight: 700;
            color: #1f2937;
        }

        /* === LISTAS === */
        .message.ociel .message-content ul {
            margin: 12px 0;
            padding: 0;
            list-style: none;
        }

        .message.ociel .message-content li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
            line-height: 1.5;
            color: #4b5563;
            font-size: 14px;
        }

        .message.ociel .message-content li::before {
            content: "•";
            color: #3b82f6;
            font-size: 14px;
            font-weight: bold;
            position: absolute;
            left: 6px;
            top: 0;
        }

        /* === INFORMACIÓN DE CONTACTO === */
        .contact-info {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 16px;
            margin: 12px 0;
            font-size: 13px;
            color: #166534;
            font-weight: 500;
        }

        .contact-title {
            font-weight: 700;
            color: #14532d;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 6px 0;
            color: #166534;
            font-size: 13px;
        }

        /* === AVATAR DE MENSAJE === */
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 13px;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message.user .message-avatar {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        /* === INDICADOR DE ESCRITURA === */
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 16px 0;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 16px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9ca3af;
            animation: typing 1.4s ease-in-out infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }

        /* === ÁREA DE INPUT === */
        .chat-input-container {
            padding: 24px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }

        .quick-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            padding: 8px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            font-size: 12px;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .quick-action-btn:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .chat-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 28px;
            outline: none;
            font-size: 14px;
            line-height: 1.4;
            resize: none;
            max-height: 120px;
            transition: border-color 0.2s ease;
            font-family: inherit;
        }

        .chat-input:focus {
            border-color: #3b82f6;
        }

        .send-button {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
        }

        .send-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }

        .send-button:active {
            transform: translateY(0);
        }

        .send-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* === MENSAJE DE BIENVENIDA === */
        .welcome-message {
            text-align: center;
            color: #6b7280;
            padding: 48px 24px;
            line-height: 1.6;
        }

        .welcome-logo {
            width: 88px;
            height: 88px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            margin: 0 auto 24px;
            box-shadow: 0 12px 28px rgba(251, 191, 36, 0.3);
        }

        /* === INDICADOR DE CONFIANZA === */
        .confidence-indicator {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 12px;
            text-align: right;
            font-weight: 500;
        }

        .high-confidence { color: #10b981; }
        .medium-confidence { color: #f59e0b; }
        .low-confidence { color: #ef4444; }

        /* === FOOTER === */
        .footer-info {
            text-align: center;
            padding: 12px 24px;
            background: #f9fafb;
            font-size: 11px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            font-weight: 500;
        }

        /* === MENSAJES DE ERROR === */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            font-size: 14px;
        }

        .retry-button {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
        }

        .system-message {
            text-align: center;
            color: #6b7280;
            font-size: 12px;
            margin: 16px 0;
            font-style: italic;
        }

        /* === RESPONSIVE === */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .chat-container {
                height: 100vh;
                border-radius: 0;
                max-width: 100%;
            }

            .chat-header {
                padding: 20px;
            }

            .chat-messages {
                padding: 20px;
            }

            .chat-input-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="ociel-avatar">O</div>
            <div class="header-info">
                <div class="header-title">¡Hola Ociel!</div>
                <div class="header-subtitle">Asistente Virtual UAN v2.0</div>
            </div>
            <div class="status-indicator online" id="statusIndicator"></div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="welcome-message">
                <div class="welcome-logo">O</div>
                <h3>¡Bienvenido!</h3>
                <p>Soy Ociel, tu asistente virtual de la Universidad Autónoma de Nayarit.</p>
                <p>Estoy aquí para ayudarte con información sobre trámites, carreras, servicios y más.</p>
                <p><strong>¿En qué puedo ayudarte hoy?</strong></p>
            </div>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            <div class="message-avatar">O</div>
            <div class="typing-dots">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
        </div>

        <div class="chat-input-container">
            <div class="quick-actions">
                <button class="quick-action-btn" onclick="sendQuickMessage('¿Qué carreras ofrecen?')">🎓 Carreras</button>
                <button class="quick-action-btn" onclick="sendQuickMessage('¿Cómo me inscribo?')">📝 Inscripción</button>
                <button class="quick-action-btn" onclick="sendQuickMessage('¿Cómo activar mi correo?')">📧 Correo</button>
                <button class="quick-action-btn" onclick="sendQuickMessage('Soporte técnico')">💻 Soporte</button>
            </div>

            <div class="chat-input-wrapper">
                <textarea
                    id="messageInput"
                    class="chat-input"
                    placeholder="Escribe tu mensaje aquí..."
                    rows="1"
                ></textarea>
                <button id="sendButton" class="send-button" onclick="sendMessage()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="footer-info">
            Universidad Autónoma de Nayarit • Tel: 311-211-8800 • Sistema v2.0
        </div>
    </div>

    <script>
        // === CONFIGURACIÓN DEL SISTEMA ===
        let isLoading = false;
        let sessionId = null;
        let retryCount = 0;
        const maxRetries = 3;

        // URLs de la API - ACTUALIZADO para EnhancedChatController
        const API_CONFIG = {
            CHAT_URL: '/api/v1/chat',          // Usa EnhancedChatController
            HEALTH_URL: '/api/v1/health',      // Usa healthAdvanced
            DEBUG_URL: '/api/v1/debug/stats',  // Nueva ruta de debug
            FALLBACK_CHAT_URL: '/api/v2/chat'  // Fallback v2
        };

        // Estado del sistema
        let systemStatus = {
            apiOnline: false,
            lastHealthCheck: null,
            errorCount: 0,
            debugMode: false
        };

        // === INICIALIZACIÓN ===
        document.addEventListener('DOMContentLoaded', function() {
            initializeChat();
        });

        async function initializeChat() {
            setupEventListeners();
            autoResizeTextarea();

            // Verificar salud del sistema al iniciar
            await checkSystemHealth();

            // Health check periódico cada 30 segundos
            setInterval(checkSystemHealth, 30000);

            // Activar modo debug si estamos en desarrollo
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                systemStatus.debugMode = true;
                console.log('🔧 Modo debug activado');
            }
        }

        function setupEventListeners() {
            const messageInput = document.getElementById('messageInput');
            const sendButton = document.getElementById('sendButton');

            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            messageInput.addEventListener('input', function() {
                autoResizeTextarea();
                toggleSendButton();
            });

            // Debug: Doble click en el avatar para mostrar debug info
            if (systemStatus.debugMode) {
                document.querySelector('.ociel-avatar').addEventListener('dblclick', showDebugInfo);
            }

            messageInput.focus();
        }

        function autoResizeTextarea() {
            const textarea = document.getElementById('messageInput');
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        function toggleSendButton() {
            const input = document.getElementById('messageInput');
            const button = document.getElementById('sendButton');
            button.disabled = !input.value.trim() || isLoading;
        }

        // === VERIFICACIÓN DE SALUD DEL SISTEMA ===
        async function checkSystemHealth() {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000);

                const response = await fetch(API_CONFIG.HEALTH_URL, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (response.ok) {
                    const healthData = await response.json();
                    systemStatus.apiOnline = true;
                    systemStatus.lastHealthCheck = new Date();
                    systemStatus.errorCount = 0;
                    updateStatusIndicator('online');

                    if (systemStatus.debugMode) {
                        console.log('✅ Health check OK:', healthData);
                    }
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                console.warn('⚠️ Health check failed:', error.message);
                systemStatus.apiOnline = false;
                systemStatus.errorCount++;
                updateStatusIndicator('offline');

                // Si hay muchos errores consecutivos, avisar al usuario
                if (systemStatus.errorCount >= 3) {
                    showSystemMessage('⚠️ Detectamos problemas de conectividad. El servicio puede estar limitado.');
                }
            }
        }

        function updateStatusIndicator(status) {
            const indicator = document.getElementById('statusIndicator');
            indicator.className = `status-indicator ${status}`;
        }

        function showSystemMessage(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const systemDiv = document.createElement('div');
            systemDiv.className = 'system-message';
            systemDiv.textContent = message;
            messagesContainer.appendChild(systemDiv);
            scrollToBottom();
        }

        // === ENVÍO DE MENSAJES ===
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message || isLoading) return;

            input.value = '';
            autoResizeTextarea();
            toggleSendButton();

            addMessage(message, 'user');
            showTypingIndicator();

            try {
                const response = await sendChatRequest(message);

                if (response.success) {
                    handleSuccessfulResponse(response);
                } else {
                    handleErrorResponse(response);
                }

                retryCount = 0; // Reset retry count on success

            } catch (error) {
                console.error('❌ Chat error:', error);
                await handleChatError(error, message);
            } finally {
                hideTypingIndicator();
            }
        }

        async function sendChatRequest(message) {
            updateStatusIndicator('connecting');

            const requestData = {
                message: message,
                user_type: 'student',
                session_id: sessionId,
                context_preference: 'standard'
            };

            if (systemStatus.debugMode) {
                console.log('📤 Enviando request:', requestData);
            }

            // Intentar primero con la API principal (v1 con EnhancedChatController)
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000);

                const response = await fetch(API_CONFIG.CHAT_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestData),
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                updateStatusIndicator('online');

                if (systemStatus.debugMode) {
                    console.log('📥 Respuesta recibida:', data);
                }

                return data;

            } catch (primaryError) {
                console.warn('⚠️ API principal falló, intentando fallback:', primaryError.message);

                // Intentar con API fallback (v2)
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000);

                    const fallbackResponse = await fetch(API_CONFIG.FALLBACK_CHAT_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(requestData),
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    if (!fallbackResponse.ok) {
                        throw new Error(`Fallback HTTP ${fallbackResponse.status}`);
                    }

                    const fallbackData = await fallbackResponse.json();
                    updateStatusIndicator('online');

                    if (systemStatus.debugMode) {
                        console.log('📥 Respuesta fallback recibida:', fallbackData);
                    }

                    return fallbackData;

                } catch (fallbackError) {
                    updateStatusIndicator('offline');
                    throw fallbackError;
                }
            }
        }

        function handleSuccessfulResponse(response) {
            if (response.data) {
                sessionId = response.data.session_id;
                const confidence = response.data.confidence || 0.8;

                addMessage(response.data.response || 'Respuesta no disponible', 'ociel', confidence);

                // Mostrar información adicional del Enhanced response
                if (response.data.requires_human_follow_up) {
                    addSystemMessage('🔄 Esta consulta ha sido marcada para seguimiento especializado.');
                }

                // Mostrar información de contacto si está disponible
                if (response.data.contact_info && response.data.contact_info.primary) {
                    addContactInfo(response.data.contact_info);
                }

                if (systemStatus.debugMode) {
                    console.log('📊 Quality indicators:', response.data.quality_indicators);
                    console.log('🔧 Model used:', response.data.model_used);
                    console.log('⏱️ Response time:', response.data.response_time + 'ms');
                }
            } else {
                addMessage('Recibí tu mensaje pero no pude procesarlo correctamente. ¿Puedes intentar reformular tu consulta?', 'ociel', 0.3);
            }
        }

        function handleErrorResponse(response) {
            const errorMessage = response.error || 'Error desconocido en el sistema';

            if (response.data && response.data.response) {
                // Hay una respuesta de respaldo del Enhanced system
                addMessage(response.data.response, 'ociel', response.data.confidence || 0.3);

                if (response.data.requires_human_follow_up) {
                    addSystemMessage('⚠️ Esta respuesta fue generada en modo de respaldo. Se recomienda contacto directo.');
                }
            } else {
                addMessage(`🔧 Disculpa, estoy experimentando dificultades técnicas: ${errorMessage}`, 'ociel', 0.2);
                addErrorRecoveryOptions();
            }
        }

        async function handleChatError(error, originalMessage) {
            retryCount++;

            if (retryCount <= maxRetries) {
                updateTypingIndicator(`Reintentando... (${retryCount}/${maxRetries})`);

                // Esperar antes de reintentar (backoff exponencial)
                const delay = Math.pow(2, retryCount) * 1000;
                await new Promise(resolve => setTimeout(resolve, delay));

                try {
                    const retryResponse = await sendChatRequest(originalMessage);
                    handleSuccessfulResponse(retryResponse);
                    return;
                } catch (retryError) {
                    console.warn(`🔄 Retry ${retryCount} failed:`, retryError.message);
                }
            }

            // Si llegamos aquí, todos los reintentos fallaron
            addMessage('🚨 **Disculpa las molestias**\n\nEstoy experimentando dificultades técnicas temporales.', 'ociel', 0.3);
            addErrorRecoveryOptions();
        }

        function addErrorRecoveryOptions() {
            const messagesContainer = document.getElementById('chatMessages');

            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = `
                <strong>Opciones de contacto directo:</strong><br>
                📞 Universidad Autónoma de Nayarit: 311-211-8800<br>
                🌐 Portal oficial: <a href="https://www.uan.edu.mx" target="_blank">https://www.uan.edu.mx</a><br>
                📧 Soporte técnico: sistemas@uan.edu.mx
                <button class="retry-button" onclick="location.reload()">🔄 Reiniciar chat</button>
            `;

            messagesContainer.appendChild(errorDiv);
            scrollToBottom();
        }

        function sendQuickMessage(message) {
            document.getElementById('messageInput').value = message;
            sendMessage();
        }

        function addMessage(text, sender, confidence = null) {
            const messagesContainer = document.getElementById('chatMessages');

            const welcomeMessage = messagesContainer.querySelector('.welcome-message');
            if (welcomeMessage) {
                welcomeMessage.remove();
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;

            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.textContent = sender === 'user' ? 'Tú' : 'O';

            const content = document.createElement('div');
            content.className = 'message-content';

            if (sender === 'ociel') {
                content.innerHTML = formatOcielResponse(text);
            } else {
                content.textContent = text;
            }

            if (sender === 'ociel' && confidence !== null) {
                const confidenceDiv = document.createElement('div');
                confidenceDiv.className = 'confidence-indicator';

                let confidenceClass = 'low-confidence';
                let confidenceText = 'Baja confianza';

                if (confidence >= 0.8) {
                    confidenceClass = 'high-confidence';
                    confidenceText = '✓ Alta confianza';
                } else if (confidence >= 0.6) {
                    confidenceClass = 'medium-confidence';
                    confidenceText = '~ Confianza media';
                } else {
                    confidenceText = '⚠ Baja confianza';
                }

                confidenceDiv.className += ` ${confidenceClass}`;
                confidenceDiv.textContent = `${confidenceText} (${Math.round(confidence * 100)}%)`;
                content.appendChild(confidenceDiv);
            }

            messageDiv.appendChild(avatar);
            messageDiv.appendChild(content);

            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function formatOcielResponse(text) {
            if (systemStatus.debugMode) {
                console.log('🎨 Formateando respuesta:', text.substring(0, 100) + '...');
            }

            // Normalizar texto
            let formatted = text
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n')
                .trim();

            // Separar en párrafos
            const paragraphs = formatted.split('\n\n').filter(p => p.trim());
            let result = '';

            for (let paragraph of paragraphs) {
                const lines = paragraph.split('\n').filter(l => l.trim());

                // Detectar si es una lista
                const listItems = [];
                const nonListLines = [];

                for (let line of lines) {
                    const trimmed = line.trim();
                    if (trimmed.match(/^[-*•+]\s+/) || trimmed.match(/^\d+\.\s+/)) {
                        listItems.push(trimmed.replace(/^[-*•+]\s+/, '').replace(/^\d+\.\s+/, ''));
                    } else {
                        nonListLines.push(trimmed);
                    }
                }

                // Procesar líneas no-lista
                for (let line of nonListLines) {
                    if (line.match(/^(🎓|📝|📚|💻|📞|🏥|👋|📋|🎯|⏰|📍|🌐)\s*\*\*(.*?)\*\*/)) {
                        result += `<h3>${line.replace(/\*\*(.*?)\*\*/, '$1')}</h3>`;
                    } else if (line.match(/^[A-ZÁÉÍÓÚÑ\s]{4,}:\s*$/)) {
                        result += `<h4>${line}</h4>`;
                    } else if (line.includes('311-211-8800') || line.includes('@uan.edu.mx')) {
                        // Formatear información de contacto
                        if (!line.includes('📞') && !line.includes('📧')) {
                            if (line.includes('311-211-8800')) {
                                result += `<div class="contact-item">📞 ${line}</div>`;
                            } else if (line.includes('@uan.edu.mx')) {
                                result += `<div class="contact-item">📧 ${line}</div>`;
                            }
                        } else {
                            result += `<div class="contact-item">${line}</div>`;
                        }
                    } else {
                        // Aplicar formato de negrita
                        const boldFormatted = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                        result += `<p>${boldFormatted}</p>`;
                    }
                }

                // Agregar lista si existe
                if (listItems.length > 0) {
                    result += '<ul>';
                    for (let item of listItems) {
                        result += `<li>${item.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}</li>`;
                    }
                    result += '</ul>';
                }
            }

            // Envolver información de contacto
            result = result.replace(
                /(<div class="contact-item">.*?<\/div>)+/gs,
                function(match) {
                    return '<div class="contact-info"><div class="contact-title">📞 Información de contacto:</div>' + match + '</div>';
                }
            );

            return result;
        }

        function addContactInfo(contactInfo) {
            const messagesContainer = document.getElementById('chatMessages');

            const contactDiv = document.createElement('div');
            contactDiv.className = 'message ociel';

            let contactHtml = '<div class="contact-info"><div class="contact-title">📞 Información de contacto oficial:</div>';

            if (contactInfo.primary) {
                contactHtml += `<div class="contact-item">📞 ${contactInfo.primary.name}: ${contactInfo.primary.phone}</div>`;
                if (contactInfo.primary.email) {
                    contactHtml += `<div class="contact-item">📧 ${contactInfo.primary.email}</div>`;
                }
            }

            if (contactInfo.secondary) {
                contactHtml += `<div class="contact-item">📞 ${contactInfo.secondary.name}: ${contactInfo.secondary.phone}</div>`;
            }

            if (contactInfo.hours) {
                contactHtml += `<div class="contact-item">⏰ ${contactInfo.hours}</div>`;
            }

            if (contactInfo.location) {
                contactHtml += `<div class="contact-item">📍 ${contactInfo.location}</div>`;
            }

            contactHtml += '</div>';

            contactDiv.innerHTML = `
                <div class="message-avatar">📞</div>
                <div class="message-content">${contactHtml}</div>
            `;

            messagesContainer.appendChild(contactDiv);
            scrollToBottom();
        }

        function addSystemMessage(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const systemDiv = document.createElement('div');
            systemDiv.className = 'system-message';
            systemDiv.textContent = message;
            messagesContainer.appendChild(systemDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            isLoading = true;
            const indicator = document.getElementById('typingIndicator');
            indicator.style.display = 'flex';

            // Restaurar los dots originales si fueron modificados
            const dots = indicator.querySelector('.typing-dots');
            dots.innerHTML = `
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            `;

            document.getElementById('sendButton').disabled = true;
            scrollToBottom();
        }

        function updateTypingIndicator(message) {
            const indicator = document.getElementById('typingIndicator');
            const dots = indicator.querySelector('.typing-dots');
            if (dots) {
                dots.innerHTML = `<span style="font-size: 12px; color: #666; padding: 0 12px;">${message}</span>`;
            }
        }

        function hideTypingIndicator() {
            isLoading = false;
            document.getElementById('typingIndicator').style.display = 'none';
            toggleSendButton();
        }

        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // === FUNCIONES DE DEBUG ===
        async function showDebugInfo() {
            if (!systemStatus.debugMode) return;

            try {
                const response = await fetch(API_CONFIG.DEBUG_URL);
                const debugData = await response.json();

                console.group('🔧 Debug Info - ¡Hola Ociel! v2.0');
                console.log('📊 System Status:', systemStatus);
                console.log('📈 API Stats:', debugData);
                console.log('🔗 API URLs:', API_CONFIG);
                console.groupEnd();

                // Mostrar en el chat también
                addSystemMessage(`🔧 Debug: ${debugData.knowledge_base?.active || 0} entradas activas, API: ${systemStatus.apiOnline ? 'Online' : 'Offline'}`);
            } catch (error) {
                console.error('❌ Error getting debug info:', error);
            }
        }

        // === MANEJO DE CONECTIVIDAD ===
        window.addEventListener('online', function() {
            systemStatus.errorCount = 0;
            updateStatusIndicator('online');
            addSystemMessage('✅ Conexión restaurada. ¡Ya puedes continuar con tus consultas!');
        });

        window.addEventListener('offline', function() {
            updateStatusIndicator('offline');
            addSystemMessage('📱 Se perdió la conexión a internet. Verifica tu conectividad y vuelve a intentar.');
        });

        // === FUNCIONES DE UTILIDAD ===
        function checkConnectivity() {
            return fetch(API_CONFIG.HEALTH_URL, {
                method: 'GET',
                timeout: 5000
            })
            .then(response => response.ok)
            .catch(() => false);
        }

        // Verificación periódica de conectividad más inteligente
        setInterval(async () => {
            if (!systemStatus.apiOnline) {
                const isOnline = await checkConnectivity();
                if (isOnline) {
                    systemStatus.apiOnline = true;
                    systemStatus.errorCount = 0;
                    updateStatusIndicator('online');
                    addSystemMessage('🔄 Servicio restaurado automáticamente.');
                }
            }
        }, 60000); // Cada minuto cuando está offline

        // === INICIALIZACIÓN FINAL ===
        if (systemStatus.debugMode) {
            console.log(`
🚀 ¡Hola Ociel! Widget v2.0 - Modo Debug Activado

📋 Configuración:
- Chat API: ${API_CONFIG.CHAT_URL}
- Health API: ${API_CONFIG.HEALTH_URL}
- Debug API: ${API_CONFIG.DEBUG_URL}
- Fallback API: ${API_CONFIG.FALLBACK_CHAT_URL}

🔧 Funciones Debug:
- Doble-click en avatar de Ociel para mostrar debug info
- Logs detallados en consola
- Información de timing y calidad de respuestas

📞 Contacto del proyecto:
- DGS (Sistemas): 311-211-8800 ext. 8640
- Email: sistemas@uan.edu.mx
            `);
        }
    </script>
</body>
</html>
