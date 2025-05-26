<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Hola Ociel! - Asistente Virtual UAN</title>
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
            max-width: 450px;
            height: 600px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .chat-header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .header-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 2px;
        }

        .header-subtitle {
            font-size: 12px;
            opacity: 0.9;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            margin-left: auto;
            animation: pulse 2s infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
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
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: fadeInUp 0.3s ease-out;
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
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
            line-height: 1.5;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 6px;
        }

        .message.ociel .message-content {
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .message.ociel .message-content .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin: 12px 0;
            border-radius: 0 6px 6px 0;
            font-size: 14px;
        }

        .message.ociel .message-content .info-box strong {
            color: #1e40af;
            font-weight: 600;
        }

        .message.ociel .message-content h3 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .message.ociel .message-content h4 {
            color: #1d4ed8;
            font-size: 15px;
            margin: 15px 0 8px 0;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .message.ociel .message-content p {
            margin-bottom: 8px;
            color: #374151;
            line-height: 1.5;
        }

        .message.ociel .message-content ul {
            margin: 12px 0;
            padding-left: 20px;
            list-style-type: none;
        }

        .message.ociel .message-content li {
            margin-bottom: 6px;
            line-height: 1.5;
            position: relative;
            padding-left: 8px;
        }

        .message.ociel .message-content li:before {
            content: "•";
            color: #3b82f6;
            font-weight: bold;
            position: absolute;
            left: -12px;
        }

        .message.ociel .message-content .contact-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 10px;
            border-radius: 8px;
            margin: 8px 0;
            font-size: 13px;
            color: #166534;
            font-weight: 500;
        }

        .message.ociel .message-content .highlight {
            background: linear-gradient(120deg, #fef3c7 0%, #fde68a 100%);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
            color: #92400e;
        }

        .message.ociel .message-content strong {
            font-weight: 600;
            color: #1f2937;
        }

        .message.ociel .message-content .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin: 12px 0;
            border-radius: 0 6px 6px 0;
        }

        .message.ociel .message-content .contact-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 10px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
        }

        .message.ociel .message-content .contact-info .contact-title {
            font-weight: 600;
            color: #166534;
            margin-bottom: 6px;
        }

        .message.ociel .message-content .program-duration {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin: 2px 4px 2px 0;
            vertical-align: baseline;
            white-space: nowrap;
        }

        /* Prevenir duplicación de badges */
        .message.ociel .message-content .program-duration .program-duration {
            background: none;
            padding: 0;
            margin: 0;
            border-radius: 0;
            font-size: inherit;
            font-weight: inherit;
            color: inherit;
            display: inline;
        }

        /* Estilos para evitar solapamiento */
        .message.ociel .message-content li {
            margin-bottom: 4px;
            color: #4b5563;
            line-height: 1.6;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .message.ociel .message-content .subjects-list {
            background: #fafafa;
            border-radius: 6px;
            padding: 10px;
            margin: 8px 0;
            font-size: 13px;
        }

        /* Estilos especiales para categorías de carreras */
        .message.ociel .message-content .career-category {
            background: #f0f9ff;
            border-left: 3px solid #0ea5e9;
            padding: 8px 12px;
            margin: 8px 0;
            border-radius: 0 6px 6px 0;
        }

        .message.ociel .message-content .career-category h5 {
            color: #0369a1;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: white;
            flex-shrink: 0;
        }

        .message.user .message-avatar {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .typing-indicator {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
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

        .chat-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }

        .quick-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            padding: 6px 12px;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 20px;
            font-size: 12px;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-action-btn:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }

        .chat-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            line-height: 1.4;
            resize: none;
            max-height: 100px;
            transition: border-color 0.2s ease;
        }

        .chat-input:focus {
            border-color: #3b82f6;
        }

        .send-button {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .send-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
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

        .welcome-message {
            text-align: center;
            color: #6b7280;
            padding: 40px 20px;
            line-height: 1.6;
        }

        .welcome-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(251, 191, 36, 0.3);
        }

        .confidence-indicator {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 8px;
            text-align: right;
        }

        .high-confidence { color: #10b981; }
        .medium-confidence { color: #f59e0b; }
        .low-confidence { color: #ef4444; }

        .footer-info {
            text-align: center;
            padding: 10px 20px;
            background: #f9fafb;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .chat-container {
                height: 100vh;
                border-radius: 0;
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
                <div class="header-subtitle">Asistente Virtual UAN</div>
            </div>
            <div class="status-indicator"></div>
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
                <button class="quick-action-btn" onclick="sendQuickMessage('Información sobre inscripción')">📝 Inscripción</button>
                <button class="quick-action-btn" onclick="sendQuickMessage('Servicios de biblioteca')">📚 Biblioteca</button>
                <button class="quick-action-btn" onclick="sendQuickMessage('Soporte técnico')">💻 Sistemas</button>
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
            Universidad Autónoma de Nayarit • Tel: 311-211-8800
        </div>
    </div>

    <script>
        let isLoading = false;
        let sessionId = null;

        // Configuración de la API
        const API_BASE_URL = 'http://localhost:8000/api/v1';

        // Inicializar el chat
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            autoResizeTextarea();
        });

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

            // Auto-focus en el input
            messageInput.focus();
        }

        function autoResizeTextarea() {
            const textarea = document.getElementById('messageInput');
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
        }

        function toggleSendButton() {
            const input = document.getElementById('messageInput');
            const button = document.getElementById('sendButton');

            if (input.value.trim() && !isLoading) {
                button.disabled = false;
            } else {
                button.disabled = true;
            }
        }

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message || isLoading) return;

            // Limpiar input inmediatamente
            input.value = '';
            autoResizeTextarea();
            toggleSendButton();

            // Agregar mensaje del usuario
            addMessage(message, 'user');

            // Mostrar indicador de escritura
            showTypingIndicator();

            let retryCount = 0;
            const maxRetries = 3;
            const retryDelay = 2000; // 2 segundos

            while (retryCount < maxRetries) {
                try {
                    console.log(`Intento ${retryCount + 1} de ${maxRetries}`);

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 segundos timeout

                    const response = await fetch(`${API_BASE_URL}/chat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            message: message,
                            user_type: 'student',
                            session_id: sessionId
                        }),
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        sessionId = data.data.session_id;
                        addMessage(data.data.response, 'ociel', data.data.confidence);

                        // Agregar información de contacto si es relevante
                        if (data.data.contact_info && data.data.requires_human_follow_up) {
                            addContactInfo(data.data.contact_info);
                        }

                        break; // Éxito, salir del loop de reintentos

                    } else {
                        // El servidor respondió pero hubo un error en el procesamiento
                        if (data.data && data.data.response) {
                            // Usar respuesta fallback del servidor
                            sessionId = data.data.session_id;
                            addMessage(data.data.response, 'ociel', data.data.confidence || 0.3);
                            addMessage('⚠️ Nota: Esta respuesta se generó en modo de respaldo. Para información más precisa, contacta directamente a la UAN.', 'ociel', 0.3);
                            break;
                        } else {
                            throw new Error(data.error || 'Error procesando la respuesta');
                        }
                    }

                } catch (error) {
                    console.error(`Error en intento ${retryCount + 1}:`, error);
                    retryCount++;

                    if (error.name === 'AbortError') {
                        console.log('Solicitud cancelada por timeout');
                    }

                    if (retryCount < maxRetries) {
                        // Mostrar mensaje de reintento
                        updateTypingIndicator(`Reintentando... (${retryCount}/${maxRetries})`);
                        await new Promise(resolve => setTimeout(resolve, retryDelay));
                    } else {
                        // Todos los reintentos fallaron
                        hideTypingIndicator();
                        addMessage('🔧 Disculpa, estoy experimentando problemas técnicos temporales. Aquí tienes información de contacto directo:', 'ociel', 0.3);
                        addContactInfo({
                            phone: '311-211-8800',
                            email: 'contacto@uan.edu.mx',
                            website: 'https://www.uan.edu.mx'
                        });
                        addMessage('💡 Tip: Puedes intentar hacer tu consulta de nuevo en unos momentos, o contactar directamente usando la información arriba.', 'ociel', 0.3);
                    }
                }
            }

            hideTypingIndicator();
        }

        // Función para actualizar el indicador de escritura con mensaje personalizado
        function updateTypingIndicator(message) {
            const indicator = document.getElementById('typingIndicator');
            const dots = indicator.querySelector('.typing-dots');
            if (dots) {
                dots.innerHTML = `<span style="font-size: 12px; color: #666;">${message}</span>`;
            }
        }

        // Función mejorada para detectar conectividad
        function checkConnectivity() {
            return fetch(`${API_BASE_URL}/ping`, {
                method: 'GET',
                timeout: 5000
            })
            .then(response => response.ok)
            .catch(() => false);
        }

        // Verificar conectividad periódicamente
        setInterval(async () => {
            const isOnline = await checkConnectivity();
            if (!isOnline && !document.querySelector('.connectivity-warning')) {
                addMessage('🔌 Detecté problemas de conectividad. Las respuestas pueden tardar más de lo normal.', 'ociel', 0.3);

                // Agregar clase para evitar múltiples warnings
                const lastMessage = document.querySelector('.message:last-child .message-content');
                if (lastMessage) {
                    lastMessage.classList.add('connectivity-warning');
                }
            }
        }, 30000); // Verificar cada 30 segundos

        // Mejorar el manejo de eventos offline/online
        window.addEventListener('online', function() {
            addMessage('✅ Conexión restaurada. ¡Ya puedes continuar con tus consultas!', 'ociel', 0.8);
        });

        window.addEventListener('offline', function() {
            addMessage('📱 Se perdió la conexión a internet. Verifica tu conectividad y vuelve a intentar.', 'ociel', 0.3);
        });

        function sendQuickMessage(message) {
            document.getElementById('messageInput').value = message;
            sendMessage();
        }

        function addMessage(text, sender, confidence = null) {
            const messagesContainer = document.getElementById('chatMessages');

            // Remover mensaje de bienvenida si existe
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

            // IMPORTANTE: Para Ociel, aplicar formato HTML
            if (sender === 'ociel') {
                content.innerHTML = formatOcielResponse(text);
            } else {
                // Para usuario, mantener texto plano
                content.textContent = text;
            }

            // Agregar indicador de confianza para respuestas de Ociel
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

        function addFormattedMessage(text, sender, confidence = null) {
            const messagesContainer = document.getElementById('chatMessages');

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;

            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.textContent = sender === 'user' ? 'Tú' : 'O';

            const content = document.createElement('div');
            content.className = 'message-content';

            // Aplicar formato mejorado al texto
            content.innerHTML = formatOcielResponse(text);

            // Agregar indicador de confianza
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
            let formatted = text;

            // 1. Proteger saltos de línea importantes
            formatted = formatted.replace(/\n\n/g, '||PARAGRAPH_BREAK||');

            // 2. Detectar y formatear diferentes tipos de listas y elementos

            // Listas con • - * +
            formatted = formatted.replace(/^[\*\-\•\+]\s+(.+)$/gm, '<li>$1</li>');

            // Listas numeradas (1. 2. etc.)
            formatted = formatted.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');

            // Elementos que empiezan con + (como en tu ejemplo)
            formatted = formatted.replace(/^\+\s+(.+)$/gm, '<li>$1</li>');

            // 3. Agrupar elementos <li> consecutivos en <ul>
            formatted = formatted.replace(/(<li>.*?<\/li>(?:\s*<li>.*?<\/li>)*)/gs, '<ul>$1</ul>');

            // 4. Formatear títulos principales con emojis y **
            formatted = formatted.replace(/^(🎓|📝|📚|💻|📞|🏥|👋|📋|🎯|⏰|📍|🌐)\s*\*\*(.*?)\*\*/gm, '<h3>$1 $2</h3>');

            // 5. Formatear subtítulos importantes (MAYÚSCULAS con :)
            formatted = formatted.replace(/^([A-ZÁÉÍÓÚÑ\s]{3,}):?\s*$/gm, '<h4>$1:</h4>');

            // 6. Formatear elementos especiales como NOTA, IMPORTANTE, etc.
            formatted = formatted.replace(/^(NOTA|IMPORTANTE|ATENCIÓN|REQUISITOS|PROCESO|FECHAS IMPORTANTES):?\s*(.*?)$/gm,
                '<div class="info-box"><strong>$1:</strong> $2</div>');

            // 7. Formatear texto en negrita **texto**
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // 8. Formatear información de contacto
            formatted = formatted.replace(/^📞\s*(.*?)$/gm, '<div class="contact-info">📞 $1</div>');
            formatted = formatted.replace(/^📧\s*(.*?)$/gm, '<div class="contact-info">📧 $1</div>');
            formatted = formatted.replace(/^🌐\s*(.*?)$/gm, '<div class="contact-info">🌐 $1</div>');

            // Detectar líneas que contengan teléfonos (311-211-8800)
            formatted = formatted.replace(/^(.*?311-211-8800.*?)$/gm, '<div class="contact-info">📞 $1</div>');

            // Detectar líneas que contengan emails (@uan.edu.mx)
            formatted = formatted.replace(/^(.*?@uan\.edu\.mx.*?)$/gm, '<div class="contact-info">📧 $1</div>');

            // 9. Restaurar párrafos
            formatted = formatted.replace(/\|\|PARAGRAPH_BREAK\|\|/g, '</p><p>');

            // 10. Envolver contenido en párrafos apropiados
            // Dividir por elementos de bloque para procesar
            let lines = formatted.split('\n');
            let result = [];
            let inParagraph = false;

            for (let line of lines) {
                line = line.trim();
                if (!line) continue;

                // Si es un elemento de bloque, cerrar párrafo previo si existe
                if (line.match(/^<(h[34]|ul|div class="(info-box|contact-info)")/)) {
                    if (inParagraph) {
                        result.push('</p>');
                        inParagraph = false;
                    }
                    result.push(line);
                } else {
                    // Si es texto normal, abrir párrafo si no está abierto
                    if (!inParagraph && !line.match(/^<\/?(p|h[34]|ul|div)/)) {
                        result.push('<p>');
                        inParagraph = true;
                    }
                    result.push(line);
                }
            }

            // Cerrar párrafo final si está abierto
            if (inParagraph) {
                result.push('</p>');
            }

            formatted = result.join('\n');

            // 11. Limpiar elementos mal formados
            formatted = formatted.replace(/<p>\s*<\/p>/g, '');
            formatted = formatted.replace(/<p>\s*(<[hud])/g, '$1');
            formatted = formatted.replace(/(<\/[hud]>)\s*<\/p>/g, '$1');

            return formatted;
        }

        function addContactInfo(contactInfo) {
            const messagesContainer = document.getElementById('chatMessages');

            const contactDiv = document.createElement('div');
            contactDiv.className = 'message ociel';
            contactDiv.innerHTML = `
                <div class="message-avatar">📞</div>
                <div class="message-content">
                    <div class="contact-info">
                        <div class="contact-title">📞 Información de contacto:</div>
                        📞 ${contactInfo.phone}<br>
                        📧 ${contactInfo.email}<br>
                        🌐 ${contactInfo.website || 'https://www.uan.edu.mx'}
                    </div>
                </div>
            `;

            messagesContainer.appendChild(contactDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            isLoading = true;
            document.getElementById('typingIndicator').style.display = 'flex';
            document.getElementById('sendButton').disabled = true;
            scrollToBottom();
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

        // Manejar errores de red
        window.addEventListener('online', function() {
            console.log('Conexión restaurada');
        });

        window.addEventListener('offline', function() {
            addMessage('Se perdió la conexión a internet. Las respuestas pueden no funcionar correctamente.', 'ociel', 0);
        });
    </script>
</body>
</html>
