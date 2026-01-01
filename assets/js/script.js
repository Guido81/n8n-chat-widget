(function() {
    'use strict';
    
    // Get configuration from localized script
    const config = window.ChatWidgetConfig || {};
    
    // DOM elements
    let chatButton;
    let chatBadge;
    let teaserBubble;
    let teaserClose;
    let chatWindow;
    let chatMessages;
    let inputField;
    let sendButton;
    let headerClose;
    let container;
    
    // State
    let isOpen = false;
    let isFirstOpen = true;
    let md; // Markdown-it instance
    
    /**
     * Session Management (In-Memory)
     * 
     * SECURITY: Session identifiers are stored in-memory only to prevent XSS attacks.
     * The sessionId will be lost on page reload, which is acceptable for chat sessions.
     * 
     * For production environments, implement server-side HttpOnly cookie management:
     * 1. Backend should set session cookie with HttpOnly, Secure, and SameSite=Strict flags
     * 2. Backend should validate session on each request using the cookie
     * 3. Backend should clear cookie on logout/session end
     * 4. Client includes credentials in fetch (already configured below)
     * 
     * Example PHP backend response headers:
     * Set-Cookie: n8n_chat_session=<session_id>; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=3600
     */
    let sessionId = null; // In-memory session token, lost on page reload
    
    /**
     * Generate a unique session ID (UUID v4) using cryptographically secure methods
     */
    function generateSessionId() {
        // Use native crypto.randomUUID() if available (modern browsers)
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        
        // Fallback: Use crypto.getRandomValues() to generate RFC4122 v4 UUID
        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            const bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            
            // Set version (4) and variant bits according to RFC4122
            bytes[6] = (bytes[6] & 0x0f) | 0x40; // Version 4
            bytes[8] = (bytes[8] & 0x3f) | 0x80; // Variant 10
            
            // Convert to UUID string format
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
            return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        }
        
        // Should not reach here in modern browsers
        throw new Error('Crypto API not available for secure session ID generation');
    }
    
    /**
     * Initialize the widget
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupWidget);
        } else {
            setupWidget();
        }
    }
    
    /**
     * Setup widget after DOM is ready
     */
    function setupWidget() {
        // Get DOM elements
        container = document.getElementById('chat-widget-container');
        chatButton = document.getElementById('chat-button');
        chatBadge = document.getElementById('chat-badge');
        teaserBubble = document.getElementById('teaser-bubble');
        teaserClose = document.getElementById('teaser-close');
        chatWindow = document.getElementById('chat-window');
        chatMessages = document.getElementById('chat-messages');
        inputField = document.getElementById('input-field');
        sendButton = document.getElementById('send-button');
        headerClose = document.getElementById('header-close');
        
        if (!container) return;
        
        // Initialize markdown-it if available
        if (typeof window.markdownit !== 'undefined') {
            md = window.markdownit({
                html: false, // Disable HTML tags in markdown to prevent XSS
                linkify: true,
                typographer: true
            });
        }
        
        // Apply configuration
        applyConfig();
        
        // Setup event listeners
        setupEventListeners();
        
        // Show teaser if configured
        if (config.showTeaserOnLoad) {
            setTimeout(function() {
                showTeaser();
            }, 2000); // Show after 2 seconds
        }
        
        // Show badge if configured
        if (config.showBadge && chatBadge) {
            chatBadge.style.display = 'block';
        }
    }
    
    /**
     * Apply configuration from PHP
     */
    function applyConfig() {
        // Set CSS custom properties
        const root = document.documentElement;
        root.style.setProperty('--primary-color', config.primaryColor || '#00BFA5');
        root.style.setProperty('--secondary-color', config.secondaryColor || '#009688');
        root.style.setProperty('--background-color', config.backgroundColor || '#FFFFFF');
        
        // Apply position
        if (config.position === 'left') {
            container.classList.add('position-left');
            container.style.left = '20px';
            container.style.right = 'auto';
        }
        
        // Update teaser text
        const teaserText = document.getElementById('teaser-text');
        if (teaserText && config.teaserText) {
            teaserText.textContent = config.teaserText;
        }
        
        // Update teaser avatar
        const teaserAvatar = document.getElementById('teaser-avatar');
        if (teaserAvatar && config.teaserAvatar) {
            teaserAvatar.src = config.teaserAvatar;
        }
        
        // Update header name
        const headerName = document.getElementById('header-name');
        if (headerName && config.headerName) {
            headerName.textContent = config.headerName;
        }
        
        // Update header response time
        const headerResponse = document.getElementById('header-response');
        if (headerResponse && config.responseTimeText) {
            headerResponse.textContent = config.responseTimeText;
        }
        
        // Update header avatar
        const headerAvatar = document.getElementById('header-avatar');
        if (headerAvatar && config.teaserAvatar) {
            headerAvatar.src = config.teaserAvatar;
        }
        
        // Update powered by section
        const poweredBy = document.getElementById('powered-by');
        if (poweredBy && config.poweredByText) {
            if (config.poweredByLink && config.poweredByLink !== '#') {
                poweredBy.innerHTML = '<a href="' + escapeHtml(config.poweredByLink) + '" target="_blank" rel="noopener">' + escapeHtml(config.poweredByText) + '</a>';
            } else {
                poweredBy.textContent = config.poweredByText;
            }
        }
        
        // Update badge count
        if (chatBadge && config.badgeCount) {
            chatBadge.textContent = config.badgeCount;
        }
    }
    
    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Chat button click
        if (chatButton) {
            chatButton.addEventListener('click', toggleChat);
        }
        
        // Teaser close button
        if (teaserClose) {
            teaserClose.addEventListener('click', hideTeaser);
        }
        
        // Header close button
        if (headerClose) {
            headerClose.addEventListener('click', closeChat);
        }
        
        // Send button
        if (sendButton) {
            sendButton.addEventListener('click', sendMessage);
        }
        
        // Input field enter key
        if (inputField) {
            inputField.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });
        }
    }
    
    /**
     * Toggle chat window
     */
    function toggleChat() {
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }
    
    /**
     * Open chat window
     */
    function openChat() {
        if (!chatWindow) return;
        
        // Hide teaser and badge
        hideTeaser();
        if (chatBadge) {
            chatBadge.style.display = 'none';
        }
        
        // Show chat window
        chatWindow.style.display = 'flex';
        isOpen = true;
        
        // Show welcome message on first open
        if (isFirstOpen && config.welcomeMessage) {
            addBotMessage(config.welcomeMessage);
            isFirstOpen = false;
        }
        
        // Focus input field
        if (inputField) {
            inputField.focus();
        }
    }
    
    /**
     * Close chat window
     */
    function closeChat() {
        if (!chatWindow) return;
        
        chatWindow.style.display = 'none';
        isOpen = false;
    }
    
    /**
     * Show teaser bubble
     */
    function showTeaser() {
        if (teaserBubble && !isOpen) {
            teaserBubble.style.display = 'block';
        }
    }
    
    /**
     * Hide teaser bubble
     */
    function hideTeaser() {
        if (teaserBubble) {
            teaserBubble.style.display = 'none';
        }
    }
    
    /**
     * Send message
     */
    function sendMessage() {
        if (!inputField) return;
        
        const message = inputField.value.trim();
        if (!message) return;
        
        // Add user message to chat
        addUserMessage(message);
        
        // Clear input
        inputField.value = '';
        
        // Send to webhook
        sendToWebhook(message);
    }
    
    /**
     * Add user message to chat
     */
    function addUserMessage(message) {
        if (!chatMessages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message user-message';
        
        const textDiv = document.createElement('div');
        textDiv.className = 'message-text';
        textDiv.textContent = message;
        
        messageDiv.appendChild(textDiv);
        chatMessages.appendChild(messageDiv);
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    /**
     * Add bot message to chat
     */
    function addBotMessage(message) {
        if (!chatMessages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message bot-message';
        
        // Add avatar
        const avatarImg = document.createElement('img');
        avatarImg.className = 'avatar';
        avatarImg.src = config.teaserAvatar || 'https://example.com/avatar.jpg';
        avatarImg.alt = 'Avatar';
        
        const textDiv = document.createElement('div');
        textDiv.className = 'message-text';
        
        // Render markdown if available, otherwise use plain text
        if (md && typeof DOMPurify !== 'undefined') {
            // Sanitize rendered markdown to prevent XSS attacks from untrusted content
            const renderedMarkdown = md.render(message);
            textDiv.innerHTML = DOMPurify.sanitize(renderedMarkdown);
        } else {
            textDiv.textContent = message;
        }
        
        messageDiv.appendChild(avatarImg);
        messageDiv.appendChild(textDiv);
        chatMessages.appendChild(messageDiv);
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    /**
     * Add typing indicator
     */
    function addTypingIndicator() {
        if (!chatMessages) return;
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot-message';
        typingDiv.id = 'typing-indicator';
        
        const avatarImg = document.createElement('img');
        avatarImg.className = 'avatar';
        avatarImg.src = config.teaserAvatar || 'https://example.com/avatar.jpg';
        avatarImg.alt = 'Avatar';
        
        const textDiv = document.createElement('div');
        textDiv.className = 'message-text';
        textDiv.textContent = '...';
        
        typingDiv.appendChild(avatarImg);
        typingDiv.appendChild(textDiv);
        chatMessages.appendChild(typingDiv);
        
        scrollToBottom();
    }
    
    /**
     * Remove typing indicator
     */
    function removeTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    /**
     * Send message to webhook
     */
    function sendToWebhook(message) {
        const webhookUrl = config.webhookUrl;
        
        if (!webhookUrl) {
            addBotMessage('Webhook URL not configured. Please contact administrator.');
            return;
        }
        
        // Generate sessionId if it doesn't exist (first message)
        if (!sessionId) {
            sessionId = generateSessionId();
        }
        
        // Show typing indicator
        addTypingIndicator();
        
        // Prepare request body with sessionId (required for n8n memory)
        const requestBody = {
            message: message,
            sessionId: sessionId
        };
        
        // Send POST request
        fetch(webhookUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            // Remove typing indicator
            removeTypingIndicator();
            
            // Update sessionId if backend returns a different one
            // (n8n typically echoes back the same sessionId we sent)
            if (data.sessionId) {
                sessionId = data.sessionId;
            }
            
            // Add bot response
            if (data.response || data.output) {
                // Support both 'response' and 'output' fields from n8n
                addBotMessage(data.response || data.output);
            } else {
                addBotMessage('Sorry, I received an invalid response. Please try again.');
            }
        })
        .catch(function(error) {
            console.error('Error sending message:', error);
            
            // Remove typing indicator
            removeTypingIndicator();
            
            // Show error message
            addBotMessage('Sorry, I\'m having trouble connecting. Please try again later.');
        });
    }
    
    /**
     * Scroll chat to bottom
     */
    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize the widget
    init();
    
})();

