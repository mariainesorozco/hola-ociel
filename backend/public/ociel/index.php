<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Hola Ociel! - Búsqueda Inteligente UAN</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f7f6f3;
            color: #37352f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 900px;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .header p {
            font-size: 20px;
            color: #787774;
            font-weight: 400;
        }

        .search-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 8px;
            margin-bottom: 24px;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            font-size: 16px;
            border: none;
            outline: none;
            background: transparent;
        }

        .search-input::placeholder {
            color: #a8a8a3;
        }

        .search-button {
            background: #0066ff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-button:hover {
            background: #0052cc;
            transform: translateY(-1px);
        }

        .search-button:active {
            transform: translateY(0);
        }


        .chat-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            max-height: 500px;
            display: flex;
            flex-direction: column;
            margin-bottom: 24px;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            max-height: 450px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .message {
            display: flex;
            gap: 12px;
            max-width: 80%;
            animation: fadeIn 0.3s ease;
        }

        .user-message {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .bot-message {
            align-self: flex-start;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .user-message .message-avatar {
            background: #0066ff;
            color: white;
        }

        .bot-message .message-avatar {
            background: #f7f6f3;
            border: 2px solid #e9e9e7;
        }

        .message-content {
            flex: 1;
        }

        .message-text {
            background: #f7f6f3;
            padding: 12px 16px;
            border-radius: 16px;
            line-height: 1.5;
        }

        .user-message .message-text {
            background: #0066ff;
            color: white;
        }

        .message-time {
            font-size: 12px;
            color: #787774;
            margin-top: 4px;
            text-align: right;
        }

        .bot-message .message-time {
            text-align: left;
        }

        .confidence-badge {
            display: inline-block;
            background: #e9e9e7;
            color: #37352f;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }

        .chat-controls {
            text-align: center;
            margin-bottom: 20px;
        }

        .clear-chat-button {
            background: #f7f6f3;
            border: 1px solid #e9e9e7;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #787774;
            cursor: pointer;
            transition: all 0.2s;
        }

        .clear-chat-button:hover {
            background: #eeede9;
            transform: translateY(-1px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .loading {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px;
            color: #787774;
            border-top: 1px solid #e9e9e7;
            background: #f7f6f3;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e0e0e0;
            border-top-color: #0066ff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .result-content {
            line-height: 1.6;
        }

        .result-content h2 {
            font-size: 24px;
            margin-bottom: 16px;
            color: #37352f;
        }

        .result-content h3 {
            font-size: 18px;
            margin-top: 20px;
            margin-bottom: 12px;
            color: #37352f;
        }

        .result-content p {
            margin-bottom: 12px;
            color: #37352f;
        }

        .result-content ul {
            margin-left: 24px;
            margin-bottom: 16px;
        }

        .result-content li {
            margin-bottom: 8px;
            color: #37352f;
        }

        .service-card {
            background: #f7f6f3;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .service-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #787774;
            font-size: 14px;
        }

        .info-value {
            flex: 1;
            color: #37352f;
        }

        .metadata {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9e9e7;
            display: flex;
            gap: 24px;
            font-size: 14px;
            color: #787774;
        }

        .metadata-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .error-message {
            background: #fee;
            color: #d00;
            padding: 12px 16px;
            border-radius: 8px;
            margin-top: 12px;
        }

        .recent-queries {
            margin-top: 40px;
        }

        .recent-queries h3 {
            font-size: 16px;
            color: #787774;
            margin-bottom: 12px;
        }

        .query-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .query-chip {
            background: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #37352f;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e9e9e7;
        }

        .query-chip:hover {
            background: #f7f6f3;
            transform: translateY(-1px);
        }

        /* Estilos para markdown renderizado en mensajes */
        .message-header {
            font-size: 16px;
            font-weight: 600;
            color: #37352f;
            margin: 8px 0 6px 0;
            border-bottom: 1px solid #e9e9e7;
            padding-bottom: 4px;
        }

        .message-list {
            margin: 8px 0;
            padding-left: 20px;
        }

        .message-list li {
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .message-link {
            color: #0066ff;
            text-decoration: none;
            border-bottom: 1px dotted #0066ff;
            transition: all 0.2s;
        }

        .message-link:hover {
            background: #f0f8ff;
            text-decoration: underline;
        }

        .icon-phone, .icon-web, .icon-paw, .icon-tiger {
            font-style: normal;
            font-weight: normal;
            margin: 0 2px;
        }

        .icon-phone {
            color: #2ecc71;
        }

        .icon-web {
            color: #3498db;
        }

        .icon-paw {
            color: #f39c12;
        }

        .icon-tiger {
            color: #e67e22;
        }

        /* Mejor espaciado para contenido estructurado */
        .bot-message .message-text p {
            margin-bottom: 8px;
        }

        .bot-message .message-text p:last-child {
            margin-bottom: 0;
        }

        .bot-message .message-text strong {
            color: #37352f;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>🐯</span>
                ¡Hola Ociel!
            </h1>
            <p>Agente Virtual Universitario de la UAN</p>
        </div>

        <div class="search-container">
            <div class="search-box">
                <input
                    type="text"
                    class="search-input"
                    placeholder="Escribe tu mensaje para Ociel..."
                    id="searchInput"
                >
                <button class="search-button" id="searchButton">
                    <span>Enviar</span>
                </button>
            </div>
        </div>

        <div class="chat-container" id="chatContainer">
            <div class="chat-messages" id="chatMessages">
                <div class="message bot-message" id="welcomeMessage">
                    <div class="message-avatar">🐯</div>
                    <div class="message-content">
                        <div class="message-text">¡Hola! Soy Ociel, tu asistente virtual de la UAN. ¿En qué puedo ayudarte hoy?</div>
                    </div>
                </div>
            </div>
            <div class="loading" id="loadingIndicator" style="display: none;">
                <div class="loading-spinner"></div>
                <span>Ociel está escribiendo...</span>
            </div>
        </div>

        <div class="chat-controls">
            <button class="clear-chat-button" id="clearChatButton">
                🗑️ Nueva conversación
            </button>
        </div>

        <div class="recent-queries">
            <h3>Consultas frecuentes</h3>
            <div class="query-chips">
                <div class="query-chip" data-query="¿Cómo solicito mi correo institucional?">📧 Correo Institucional</div>
                <div class="query-chip" data-query="¿Cómo puedo obtener mi licencia Canva Pro?">🎨 Licencia Canva Pro</div>
                <div class="query-chip" data-query="¿Qué necesito para mi cambio de programa académico?">🎓 Cambio de Programa</div>
                <div class="query-chip" data-query="¿Cómo solicito una constancia académica?">📄 Constancias</div>
                <div class="query-chip" data-query="¿Dónde puedo contactar a la Secretaría Académica?">📞 Contacto SA</div>
            </div>
        </div>
    </div>

    <script>
        // API Configuration
        const API_URL = '/api/v1/chat';

        // Elements
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const chatContainer = document.getElementById('chatContainer');
        const chatMessages = document.getElementById('chatMessages');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const clearChatButton = document.getElementById('clearChatButton');

        // Chat state
        let sessionId = null;
        let messageHistory = [];

        // Event Listeners
        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') performSearch();
        });

        clearChatButton.addEventListener('click', clearChat);

        // Query chips
        document.querySelectorAll('.query-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                searchInput.value = chip.dataset.query;
                performSearch();
            });
        });

        // Functions
        async function performSearch() {
            const query = searchInput.value.trim();
            if (!query) return;

            // Add user message to chat
            addMessage('user', query);
            
            // Clear input and disable button
            searchInput.value = '';
            searchButton.disabled = true;
            
            // Show loading
            loadingIndicator.style.display = 'flex';

            try {
                // Prepare JSON data for chat API
                const requestData = {
                    message: query,
                    user_type: 'public',
                    session_id: sessionId
                };

                // Make API call
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });

                const data = await response.json();

                if (data.success) {
                    // Store session ID for follow-up messages
                    if (data.session_id) {
                        sessionId = data.session_id;
                    }
                    
                    // Add bot response to chat
                    addMessage('bot', data.response, data.confidence);
                } else {
                    addMessage('bot', 'Lo siento, hubo un error al procesar tu consulta. ¿Podrías intentar de nuevo?');
                }
            } catch (error) {
                addMessage('bot', 'Error de conexión. Por favor verifica tu conexión e intenta de nuevo.');
                console.error('Search error:', error);
            } finally {
                loadingIndicator.style.display = 'none';
                searchButton.disabled = false;
                searchInput.focus();
            }
        }

        function addMessage(type, content, confidence = null) {
            const messageContainer = document.createElement('div');
            messageContainer.className = `message ${type}-message`;

            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.textContent = type === 'user' ? '👤' : '🐯';

            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';

            const messageText = document.createElement('div');
            messageText.className = 'message-text';
            
            // Format content for better display
            if (type === 'bot') {
                messageText.innerHTML = formatBotMessage(content);
            } else {
                messageText.textContent = content;
            }

            const messageTime = document.createElement('div');
            messageTime.className = 'message-time';
            messageTime.textContent = new Date().toLocaleTimeString('es-MX', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });

            // Add confidence badge for bot messages
            if (type === 'bot' && confidence) {
                const confidenceBadge = document.createElement('span');
                confidenceBadge.className = 'confidence-badge';
                confidenceBadge.textContent = `${Math.round(confidence * 100)}% confianza`;
                messageTime.appendChild(confidenceBadge);
            }

            messageContent.appendChild(messageText);
            messageContent.appendChild(messageTime);
            messageContainer.appendChild(avatar);
            messageContainer.appendChild(messageContent);

            // Remove welcome message after first interaction
            if (messageHistory.length === 0 && type === 'user') {
                const welcomeMessage = document.getElementById('welcomeMessage');
                if (welcomeMessage) {
                    welcomeMessage.remove();
                }
            }

            chatMessages.appendChild(messageContainer);
            
            // Store in history
            messageHistory.push({
                type,
                content,
                timestamp: new Date(),
                confidence
            });

            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function formatBotMessage(content) {
            // Procesamiento inteligente de markdown optimizado
            let formatted = content;
            
            // 1. Procesar headers (### Título)
            formatted = formatted.replace(/^### (.+)$/gm, '<h4 class="message-header">$1</h4>');
            
            // 2. Procesar texto en negritas (**texto**)
            formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            
            // 3. Procesar enlaces markdown [texto](url)
            formatted = formatted.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" class="message-link">$1</a>');
            
            // 4. Procesar listas (- elemento)
            formatted = formatted.replace(/^- (.+)$/gm, '<li>$1</li>');
            
            // 5. Envolver listas en <ul> si hay elementos <li>
            if (formatted.includes('<li>')) {
                formatted = formatted.replace(/(<li>.*<\/li>)/gs, '<ul class="message-list">$1</ul>');
                // Limpiar múltiples <ul> consecutivos
                formatted = formatted.replace(/<\/ul>\s*<ul class="message-list">/g, '');
            }
            
            // 6. Procesar emojis de teléfono y web como iconos especiales
            formatted = formatted.replace(/📞/g, '<span class="icon-phone">📞</span>');
            formatted = formatted.replace(/🌐/g, '<span class="icon-web">🌐</span>');
            formatted = formatted.replace(/🐾/g, '<span class="icon-paw">🐾</span>');
            formatted = formatted.replace(/🐯/g, '<span class="icon-tiger">🐯</span>');
            
            // 7. Convertir saltos de línea dobles a párrafos
            formatted = formatted.replace(/\n\n/g, '</p><p>');
            formatted = '<p>' + formatted + '</p>';
            
            // 8. Limpiar párrafos vacíos y mejorar estructura
            formatted = formatted.replace(/<p><\/p>/g, '');
            formatted = formatted.replace(/<p>(<h4|<ul|<\/ul>)/g, '$1');
            formatted = formatted.replace(/(<\/h4>|<\/ul>)<\/p>/g, '$1');
            
            return formatted;
        }

        function clearChat() {
            // Reset chat state
            sessionId = null;
            messageHistory = [];
            
            // Clear chat messages and restore welcome message
            chatMessages.innerHTML = `
                <div class="message bot-message" id="welcomeMessage">
                    <div class="message-avatar">🐯</div>
                    <div class="message-content">
                        <div class="message-text">¡Hola! Soy Ociel, tu asistente virtual de la UAN. ¿En qué puedo ayudarte hoy?</div>
                    </div>
                </div>
            `;
            
            // Focus input
            searchInput.focus();
        }

        // Initialize
        searchInput.focus();
    </script>
</body>
</html>
