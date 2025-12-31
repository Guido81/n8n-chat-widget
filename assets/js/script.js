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
        
        // Show typing indicator
        addTypingIndicator();
        
        // Send POST request
        fetch(webhookUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message
            })
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
            
            // Add bot response
            if (data.response) {
                addBotMessage(data.response);
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

