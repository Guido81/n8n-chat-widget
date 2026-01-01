# n8n Chat Widget for WordPress

A customizable WordPress plugin that embeds a beautiful chat widget connected to an n8n webhook for seamless customer communication.

## Features

- 🎨 Fully customizable colors and positioning
- 💬 Real-time chat integration with n8n webhooks
- 📱 Responsive design (mobile-friendly)
- ⚡ Lightweight and fast
- 🔒 Secure with WordPress best practices
- 🎯 Teaser bubble to engage visitors
- 🔔 Optional notification badge
- ⚙️ Easy-to-use admin settings page

## Installation

1. Download or clone this repository
2. Upload the `n8n-chat-widget` folder to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings > n8n Chat to configure the widget

## Configuration

Navigate to **Settings > n8n Chat** in your WordPress admin panel to configure:

### Required Settings
- **Webhook URL**: Your n8n webhook endpoint URL (required)

### Appearance Settings
- **Primary Color**: Main color for the widget (default: #00BFA5)
- **Secondary Color**: Secondary color for gradients (default: #009688)
- **Background Color**: Chat window background (default: #FFFFFF)
- **Position**: Widget position on screen (left or right, default: right)

### Content Settings
- **Header Name**: Name shown in chat header (default: "Kevin from HVAC Growth")
- **Response Time Text**: Text shown below header name (default: "We typically reply in minutes")
- **Welcome Message**: First message shown when chat opens (default: "Hi there! 👋 Have a question about HVAC marketing? I'm here to help!")
- **Avatar URL**: URL to avatar image

### Teaser Settings
- **Teaser Text**: Text shown in teaser bubble (default: "Ready to grow your HVAC business? Chat with us now. We typically reply in minutes.")
- **Show Teaser on Load**: Display teaser bubble when page loads (checkbox)

### Badge Settings
- **Show Badge**: Display notification badge on chat button (checkbox)
- **Badge Count**: Number shown in badge (default: 1)

### Branding Settings
- **Powered By Text**: Text shown in footer (default: "Powered by HVAC Growth")
- **Powered By Link**: URL for powered by link (optional)

## n8n Webhook Setup

Your n8n webhook should:

1. Accept POST requests with JSON payload:
```json
{
  "message": "User's message text",
  "sessionId": "optional-session-id"
}
```

2. Return JSON response:
```json
{
  "response": "Bot's reply text"
}
```

### Session Management

**Important:** For secure session management, implement HttpOnly cookies on your backend. See `BACKEND_INTEGRATION.md` for detailed instructions.

The widget now supports two session management approaches:
- **In-Memory (Default)**: Sessions stored in browser memory, lost on page reload
- **HttpOnly Cookies (Recommended)**: Persistent sessions with XSS protection

### Example n8n Workflow

1. Create a new workflow in n8n
2. Add a **Webhook** node (POST method)
3. Add your logic to process the message
4. (Optional) Implement HttpOnly cookie session management
5. Return a response with the format above

For detailed backend integration examples, see `BACKEND_INTEGRATION.md`

## File Structure

```
n8n-chat-widget/
├── n8n-chat-widget.php (Main plugin file)
├── assets/
│   ├── css/
│   │   └── style.css (Widget styles)
│   └── js/
│       └── script.js (Widget functionality)
├── README.md
├── BACKEND_INTEGRATION.md (Backend security guide)
├── SECURITY_FIX_SESSION_MANAGEMENT.md (Security fix documentation)
└── uninstall.php (Clean uninstall)
```

## Usage

Once activated and configured, the chat widget will automatically appear on all pages of your website. Visitors can:

1. Click the chat button to open the chat window
2. Type messages and send them to your n8n webhook
3. Receive responses in real-time
4. Close the chat at any time

## Customization

The plugin follows WordPress coding standards and can be easily customized:

- Modify `assets/css/style.css` for styling changes
- Edit `assets/js/script.js` for functionality changes
- Use WordPress hooks and filters for advanced customization

## Security

The plugin follows WordPress security best practices:

- All input is sanitized
- All output is escaped
- CSRF protection with nonces
- **Secure session management**: Sessions stored in-memory (not localStorage) to prevent XSS attacks
- **HttpOnly cookie support**: Ready for backend cookie-based authentication
- **XSS protection**: DOMPurify sanitization for markdown rendering
- **SRI verification**: Subresource Integrity for CDN dependencies

### Security Updates

**v1.0.2 (January 2026)**: Fixed XSS vulnerability in session management
- Removed localStorage usage for session tokens
- Implemented in-memory session storage
- Added HttpOnly cookie support
- See `SECURITY_FIX_SESSION_MANAGEMENT.md` for details

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Active n8n webhook endpoint

## Support

For issues, questions, or contributions, please contact the plugin author or submit an issue on the project repository.

## License

GPL v2 or later

## Credits

Developed for HVAC Growth

