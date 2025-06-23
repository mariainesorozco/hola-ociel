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
        }

        .chat-header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ociel-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .header-info h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .header-info p {
            font-size: 12px;
            opacity: 0.9;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            margin-left: auto;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: #f8fafc;
        }

        .message {
            display: flex;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
        }

        .message.user .message-avatar {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .message-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.4;
            font-size: 14px;
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
        }

        .typing-indicator {
            display: none;
            align-items: center;
            gap: 10px;
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
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #9ca3af;
            animation: typing 1.4s ease-in-out infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-8px); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .chat-input-container {
            padding: 20px;
            background: white;
            border-top: 1px solid #e5e7eb;
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
            resize: none;
            max-height: 100px;
            transition: border-color 0.2s ease;
            font-family: inherit;
        }

        .chat-input:focus {
            border-color: #3b82f6;
        }

        .send-button {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 16px;
        }

        .send-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
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
        }

        .welcome-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
            margin: 0 auto 16px;
        }

        .welcome-message h3 {
            margin-bottom: 8px;
            color: #374151;
        }

        .welcome-message p {
            font-size: 14px;
            line-height: 1.5;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 16px;
            margin: 10px 0;
            color: #991b1b;
            font-size: 13px;
            line-height: 1.4;
        }

        .retry-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
            transition: background 0.2s ease;
        }

        .retry-button:hover {
            background: #2563eb;
        }

        /* Scrollbar personalizada */
        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .chat-container {
                height: 100vh;
                max-height: 100vh;
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
                <h1>¡Hola Ociel!</h1>
                <p>Asistente Virtual UAN</p>
            </div>
            <div class="status-indicator"></div>
        </div>

        <div id="chatMessages" class="chat-messages">
            <div class="welcome-message">
                <div class="welcome-logo">O</div>
                <h3>¡Hola! Soy Ociel</h3>
                <p>Tu asistente virtual de la Universidad Autónoma de Nayarit. Pregúntame sobre servicios académicos, trámites, tecnología y más.</p>
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
            <div class="chat-input-wrapper">
                <textarea id="messageInput" class="chat-input" placeholder="Escribe tu mensaje..." rows="1"></textarea>
                <button id="sendButton" class="send-button" onclick="sendMessage()">➤</button>
            </div>
        </div>
    </div>

    <script>
        let isLoading = false;
        let sessionId = generateSessionId();

        function generateSessionId() {
            return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        // Auto-resize textarea
        document.getElementById('messageInput').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Send on Enter (but not Shift+Enter)
        document.getElementById('messageInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || isLoading) return;

            // Add user message
            addMessage(message, 'user');
            input.value = '';
            input.style.height = 'auto';

            // Show typing indicator
            showTypingIndicator();
            isLoading = true;
            document.getElementById('sendButton').disabled = true;

            try {
                const response = await fetch('/api/v1/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: sessionId,
                        user_type: 'public'
                    })
                });

                const data = await response.json();

                if (data.success && data.response) {
                    addMessage(data.response, 'ociel');
                } else {
                    throw new Error(data.error || 'Error al procesar la respuesta');
                }

            } catch (error) {
                console.error('Error:', error);
                addMessage('Disculpa, estoy teniendo dificultades técnicas. Por favor intenta de nuevo en un momento.', 'ociel');
                addErrorRecoveryOptions();
            } finally {
                hideTypingIndicator();
                isLoading = false;
                document.getElementById('sendButton').disabled = false;
                input.focus();
            }
        }

        function addMessage(text, sender) {
            const messagesContainer = document.getElementById('chatMessages');
            
            // Remove welcome message if exists
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
                content.innerHTML = formatResponse(text);
            } else {
                content.textContent = text;
            }

            messageDiv.appendChild(avatar);
            messageDiv.appendChild(content);

            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function formatResponse(text) {
            // Simple formatting for better readability
            return text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>')
                .replace(/^/, '<p>')
                .replace(/$/, '</p>')
                .replace(/<p><\/p>/g, '');
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'flex';
            scrollToBottom();
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'none';
        }

        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function addErrorRecoveryOptions() {
            const messagesContainer = document.getElementById('chatMessages');
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = `
                <strong>Servicios disponibles:</strong><br>
                🎓 Servicios Académicos<br>
                💻 Servicios Tecnológicos<br>
                📋 Servicios Administrativos<br>
                <button class="retry-button" onclick="location.reload()">🔄 Reiniciar chat</button>
            `;

            messagesContainer.appendChild(errorDiv);
            scrollToBottom();
        }

        // Focus input on load
        window.addEventListener('load', () => {
            document.getElementById('messageInput').focus();
        });
    </script>
</body>
</html>