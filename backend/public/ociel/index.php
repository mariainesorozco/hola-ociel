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

        /* === CORRECCIÓN PARA CONTENIDO DE OCIEL === */

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

        /* === LISTAS CORREGIDAS === */

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

        /* === INFORMACIÓN DE CONTACTO CORREGIDA === */

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

        /* === CAJAS INFORMATIVAS CORREGIDAS === */

        .info-box {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #93c5fd;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 0 8px 8px 0;
            font-size: 14px;
        }

        .warning-box {
            background: linear-gradient(135deg, #fefce8, #fef3c7);
            border: 1px solid #fbbf24;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 0 8px 8px 0;
            font-size: 14px;
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

            input.value = '';
            autoResizeTextarea();
            toggleSendButton();

            addMessage(message, 'user');
            showTypingIndicator();

            let retryCount = 0;
            const maxRetries = 3;
            const retryDelay = 2000;

            while (retryCount < maxRetries) {
                try {
                    console.log(`Intento ${retryCount + 1} de ${maxRetries}`);

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 30000);

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

                        if (data.data.contact_info && data.data.requires_human_follow_up) {
                            addContactInfo(data.data.contact_info);
                        }

                        break;

                    } else {
                        if (data.data && data.data.response) {
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
                        updateTypingIndicator(`Reintentando... (${retryCount}/${maxRetries})`);
                        await new Promise(resolve => setTimeout(resolve, retryDelay));
                    } else {
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

        function updateTypingIndicator(message) {
            const indicator = document.getElementById('typingIndicator');
            const dots = indicator.querySelector('.typing-dots');
            if (dots) {
                dots.innerHTML = `<span style="font-size: 12px; color: #666;">${message}</span>`;
            }
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
            console.log('Formateando texto:', text);

            // 1. Normalizar texto
            let formatted = text
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n')
                .trim();

            // 2. Separar en párrafos
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
                        // NO agregar ícono si ya existe en el texto
                        if (!line.includes('📞') && !line.includes('📧')) {
                            if (line.includes('311-211-8800')) {
                                result += `<div class="contact-item">📞 ${line}</div>`;
                            } else if (line.includes('@uan.edu.mx')) {
                                result += `<div class="contact-item">📧 ${line}</div>`;
                            }
                        } else {
                            // Ya tiene ícono, solo formatear
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

            // 3. Envolver información de contacto y limpiar duplicados
            result = result.replace(
                /(<div class="contact-item">.*?<\/div>)+/gs,
                function(match) {
                    // Limpiar duplicaciones dentro del bloque de contacto
                    let cleanMatch = match
                        .replace(/📞\s*📞/g, '📞')
                        .replace(/📧\s*📧/g, '📧')
                        .replace(/311-211-8800\s+ext\.\s+311-211-8800\s+ext\.\s+(\d+)/g, '311-211-8800 ext. $1');

                    return '<div class="contact-info"><div class="contact-title">📞 Información de contacto:</div>' + cleanMatch + '</div>';
                }
            );

            console.log('Resultado formateado:', result);
            return result;
        }

        function addContactInfo(contactInfo) {
            const messagesContainer = document.getElementById('chatMessages');

            const contactDiv = document.createElement('div');
            contactDiv.className = 'message ociel';
            contactDiv.innerHTML = `
                <div class="message-avatar">📞</div>
                <div class="message-content">
                    <div class="contact-info">
                        <div class="contact-title">📞 Información de contacto oficial:</div>
                        <div class="contact-item">📞 ${contactInfo.phone}</div>
                        <div class="contact-item">📧 ${contactInfo.email}</div>
                        <div class="contact-item">🌐 ${contactInfo.website || 'https://www.uan.edu.mx'}</div>
                    </div>
                </div>
            `;

            messagesContainer.appendChild(contactDiv);
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

        function hideTypingIndicator() {
            isLoading = false;
            document.getElementById('typingIndicator').style.display = 'none';
            toggleSendButton();
        }

        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Manejar conectividad
        function checkConnectivity() {
            return fetch(`${API_BASE_URL}/ping`, {
                method: 'GET',
                timeout: 5000
            })
            .then(response => response.ok)
            .catch(() => false);
        }

        setInterval(async () => {
            const isOnline = await checkConnectivity();
            if (!isOnline && !document.querySelector('.connectivity-warning')) {
                addMessage('🔌 Detecté problemas de conectividad. Las respuestas pueden tardar más de lo normal.', 'ociel', 0.3);

                const lastMessage = document.querySelector('.message:last-child .message-content');
                if (lastMessage) {
                    lastMessage.classList.add('connectivity-warning');
                }
            }
        }, 30000);

        window.addEventListener('online', function() {
            addMessage('✅ Conexión restaurada. ¡Ya puedes continuar con tus consultas!', 'ociel', 0.8);
        });

        window.addEventListener('offline', function() {
            addMessage('📱 Se perdió la conexión a internet. Verifica tu conectividad y vuelve a intentar.', 'ociel', 0.3);
        });
    </script>
</body>
</html>
