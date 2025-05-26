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
            align-items: flex-end;
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
            line-height: 1.4;
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

        .message-time {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 4px;
            text-align: center;
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

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 12px;
        }

        .confidence-indicator {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 4px;
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

            try {
                const response = await fetch(`${API_BASE_URL}/chat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        user_type: 'student',
                        session_id: sessionId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    sessionId = data.data.session_id;
                    addMessage(data.data.response, 'ociel', data.data.confidence);

                    // Agregar información de contacto si es relevante
                    if (data.data.contact_info && data.data.requires_human_follow_up) {
                        addContactInfo(data.data.contact_info);
                    }
                } else {
                    addMessage('Lo siento, hubo un problema procesando tu mensaje. Por favor intenta de nuevo.', 'ociel', 0);
                }

            } catch (error) {
                console.error('Error:', error);
                addMessage('Parece que hay un problema de conexión. Por favor, verifica tu conexión a internet e intenta de nuevo.', 'ociel', 0);
            } finally {
                hideTypingIndicator();
            }
        }

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
            content.textContent = text;

            // Agregar indicador de confianza para respuestas de Ociel
            if (sender === 'ociel' && confidence !== null) {
                const confidenceDiv = document.createElement('div');
                confidenceDiv.className = 'confidence-indicator';

                let confidenceClass = 'low-confidence';
                let confidenceText = 'Baja confianza';

                if (confidence >= 0.8) {
                    confidenceClass = 'high-confidence';
                    confidenceText = 'Alta confianza';
                } else if (confidence >= 0.6) {
                    confidenceClass = 'medium-confidence';
                    confidenceText = 'Confianza media';
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

        function addContactInfo(contactInfo) {
            const messagesContainer = document.getElementById('chatMessages');

            const contactDiv = document.createElement('div');
            contactDiv.className = 'message ociel';
            contactDiv.innerHTML = `
                <div class="message-avatar">📞</div>
                <div class="message-content">
                    <strong>Información de contacto:</strong><br>
                    📞 ${contactInfo.phone}<br>
                    📧 ${contactInfo.email}<br>
                    🌐 ${contactInfo.website || 'https://www.uan.edu.mx'}
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

        // Funciones de utilidad
        function formatTime(date) {
            return date.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
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
