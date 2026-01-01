# Backend Integration Guide for Secure Session Management

## Overview

This document provides guidance for implementing secure session management for the n8n Chat Widget using HttpOnly cookies instead of localStorage.

## Security Context

**Previous Implementation (INSECURE):**
- Session IDs were stored in `localStorage`
- Vulnerable to XSS attacks (malicious scripts could steal session tokens)
- Session tokens exposed to any JavaScript running on the page

**Current Implementation (SECURE):**
- Session IDs stored in-memory on client (lost on page reload)
- Client configured to send `credentials: 'include'` with all requests
- Backend should implement HttpOnly cookie-based session management

## Backend Requirements

### 1. Session Cookie Configuration

When creating a session, your backend (n8n workflow or API) should set a cookie with the following flags:

```http
Set-Cookie: n8n_chat_session=<session_id>; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=3600
```

**Cookie Attributes Explained:**
- `HttpOnly`: Prevents JavaScript from accessing the cookie (XSS protection)
- `Secure`: Cookie only sent over HTTPS (man-in-the-middle protection)
- `SameSite=Strict`: Cookie only sent to same-site requests (CSRF protection)
- `Path=/`: Cookie available for all paths
- `Max-Age=3600`: Cookie expires after 1 hour (adjust as needed)

### 2. n8n Workflow Implementation

#### Example n8n Workflow Structure:

**Node 1: Webhook (Trigger)**
- Method: POST
- Path: `/chat`
- Response Mode: "Respond to Webhook"

**Node 2: Function - Extract Session**
```javascript
// Check if session cookie exists in request
const cookies = $input.item.json.headers.cookie || '';
const sessionMatch = cookies.match(/n8n_chat_session=([^;]+)/);
const existingSessionId = sessionMatch ? sessionMatch[1] : null;

// Generate new session ID if none exists
const sessionId = existingSessionId || `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

return {
  json: {
    sessionId: sessionId,
    message: $input.item.json.body.message,
    isNewSession: !existingSessionId
  }
};
```

**⚠️ CRITICAL SECURITY NOTE:**

When implementing HttpOnly cookie-based session management:

- **ALWAYS extract the `sessionId` from the cookie** (as shown in the code above)
- **NEVER trust or use `$input.item.json.body.sessionId`** from the request body when cookies are being used
- **IGNORE any sessionId sent in the request body** - it could be malicious or tampered with
- **ONLY rely on the cookie-derived sessionId** for authentication and session management

The session extraction code above demonstrates the correct approach: extract the session from the `cookie` header only. Any sessionId included in the request body should be completely ignored to prevent session fixation and hijacking attacks.

**Node 3: Your Chat Logic**
- Process the message
- Use sessionId to maintain conversation context
- Store session data in database or memory

**Node 4: Function - Prepare Response**
```javascript
const sessionId = $input.item.json.sessionId;
const isNewSession = $input.item.json.isNewSession;
const response = $input.item.json.response;

// Prepare response body
const responseBody = {
  response: response
  // DO NOT include sessionId in response body for security
};

// Prepare headers with Set-Cookie if new session
const headers = {
  'Content-Type': 'application/json'
};

if (isNewSession) {
  // Set HttpOnly cookie for new sessions
  headers['Set-Cookie'] = `n8n_chat_session=${sessionId}; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=3600`;
}

return {
  json: {
    body: responseBody,
    headers: headers,
    statusCode: 200
  }
};
```

**Node 5: Respond to Webhook**
- Response Code: `{{$json.statusCode}}`
- Response Body: `{{$json.body}}`
- Response Headers: `{{$json.headers}}`

### 3. Alternative Backend Implementations

#### PHP Example:

```php
<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Get message from request
$data = json_decode(file_get_contents('php://input'), true);
$message = $data['message'] ?? '';

// Session ID is automatically managed by PHP
$sessionId = session_id();

// Process message and generate response
$response = processMessage($message, $sessionId);

// Return response (session cookie is automatically sent by PHP)
header('Content-Type: application/json');
echo json_encode([
    'response' => $response
    // DO NOT include sessionId in response
]);
```

#### Node.js/Express Example:

```javascript
const express = require('express');
const session = require('express-session');
const app = express();

app.use(express.json());

// Configure session middleware
app.use(session({
  name: 'n8n_chat_session',
  secret: 'your-secret-key-here',
  resave: false,
  saveUninitialized: true,
  cookie: {
    httpOnly: true,
    secure: true, // Set to true in production with HTTPS
    sameSite: 'strict',
    maxAge: 3600000 // 1 hour
  }
}));

// CORS configuration to allow credentials
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', 'https://your-wordpress-site.com');
  res.header('Access-Control-Allow-Credentials', 'true');
  res.header('Access-Control-Allow-Headers', 'Content-Type');
  next();
});

app.post('/chat', (req, res) => {
  const message = req.body.message;
  const sessionId = req.session.id;
  
  // Initialize session data if new
  if (!req.session.conversationHistory) {
    req.session.conversationHistory = [];
  }
  
  // Process message
  const response = processMessage(message, req.session);
  
  // Return response (session cookie is automatically sent)
  res.json({
    response: response
    // DO NOT include sessionId in response
  });
});
```

### 4. CORS Configuration

**IMPORTANT:** When using HttpOnly cookies with cross-origin requests, you must configure CORS properly:

```http
Access-Control-Allow-Origin: https://your-wordpress-site.com
Access-Control-Allow-Credentials: true
Access-Control-Allow-Headers: Content-Type
```

**Notes:**
- `Access-Control-Allow-Origin` cannot be `*` when using credentials
- Must specify the exact origin of your WordPress site
- `Access-Control-Allow-Credentials: true` is required for cookies to be sent

### 5. Session Validation

On each request, validate the session:

```javascript
// Example validation function
function validateSession(sessionId) {
  // Check if session exists in your storage (database, Redis, etc.)
  const session = getSessionFromStorage(sessionId);
  
  if (!session) {
    return { valid: false, error: 'Session not found' };
  }
  
  // Check if session has expired
  if (session.expiresAt < Date.now()) {
    deleteSession(sessionId);
    return { valid: false, error: 'Session expired' };
  }
  
  return { valid: true, session: session };
}
```

### 6. Session Cleanup

Implement session cleanup to remove expired sessions:

```javascript
// Run periodically (e.g., every hour)
function cleanupExpiredSessions() {
  const now = Date.now();
  const sessions = getAllSessions();
  
  sessions.forEach(session => {
    if (session.expiresAt < now) {
      deleteSession(session.id);
    }
  });
}
```

## Migration Guide

### For Existing Deployments:

1. **Update Backend:** Implement HttpOnly cookie support as described above
2. **Deploy Backend Changes:** Ensure CORS is properly configured
3. **Update Frontend:** The frontend has already been updated to:
   - Remove localStorage usage
   - Use in-memory session storage (temporary)
   - Send `credentials: 'include'` with all requests
4. **Test:** Verify cookies are being set and sent correctly
5. **Monitor:** Check for any session-related errors

### Backward Compatibility:

The current implementation supports both approaches:
- **In-memory (current):** Sessions lost on page reload, but secure
- **HttpOnly cookies (recommended):** Sessions persist, fully secure

If your backend doesn't send a session cookie, the client will use in-memory storage as a fallback.

## Testing

### Test Checklist:

- [ ] Session cookie is set on first message
- [ ] Cookie has `HttpOnly` flag set
- [ ] Cookie has `Secure` flag set (HTTPS only)
- [ ] Cookie has `SameSite=Strict` flag set
- [ ] Cookie is sent with subsequent requests
- [ ] Session persists across page reloads
- [ ] Session expires after configured time
- [ ] CORS headers allow credentials
- [ ] JavaScript cannot access the cookie via `document.cookie`

### Testing Tools:

1. **Browser DevTools:**
   - Open Application/Storage tab
   - Check Cookies section
   - Verify cookie flags

2. **Network Tab:**
   - Check request headers for `Cookie: n8n_chat_session=...`
   - Check response headers for `Set-Cookie: ...`

3. **Security Test:**
   ```javascript
   // This should NOT return the session cookie
   console.log(document.cookie);
   ```

## Security Best Practices

1. **Use HTTPS:** Always use HTTPS in production for the `Secure` flag
2. **Strong Session IDs:** Use cryptographically secure random values
3. **Session Expiration:** Set reasonable expiration times (1-24 hours)
4. **Session Rotation:** Regenerate session IDs periodically
5. **Rate Limiting:** Implement rate limiting to prevent abuse
6. **Input Validation:** Always validate and sanitize user input
7. **Monitoring:** Log and monitor suspicious session activity

## Troubleshooting

### Cookies Not Being Set:

- Check CORS configuration
- Verify `Access-Control-Allow-Credentials: true` header
- Ensure `Secure` flag is only used with HTTPS
- Check browser console for CORS errors

### Cookies Not Being Sent:

- Verify `credentials: 'include'` in fetch request (already configured)
- Check that origin matches CORS configuration
- Ensure cookie hasn't expired
- Verify `SameSite` policy allows the request

### Session Lost on Reload:

- Check if backend is setting the cookie correctly
- Verify cookie `Max-Age` or `Expires` is set
- Check if cookie is being cleared by browser settings

## Support

For questions or issues:
1. Check browser console for errors
2. Verify network requests in DevTools
3. Review backend logs for session-related errors
4. Ensure CORS configuration matches your domain

## References

- [MDN: HTTP Cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies)
- [OWASP: Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [MDN: Fetch API - credentials](https://developer.mozilla.org/en-US/docs/Web/API/fetch#credentials)

