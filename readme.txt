=== n8n Chat Widget ===
Contributors: hvacgrowth
Tags: chat, n8n, webhook, customer support, live chat
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A customizable WordPress plugin that embeds a chat widget connected to an n8n webhook for seamless customer communication.

== Description ==

The n8n Chat Widget plugin allows you to add a beautiful, customizable chat widget to your WordPress site that connects directly to your n8n workflows via webhooks. Perfect for businesses looking to automate customer interactions while maintaining a professional appearance.

= Features =

* 🎨 Fully customizable colors (primary, secondary, background)
* 📍 Flexible positioning (left or right side of screen)
* 💬 Teaser bubble to engage visitors
* 🔔 Optional notification badge
* 👤 Custom avatar support
* 📱 Fully responsive and mobile-friendly
* ⚡ Lightweight and fast
* 🔒 Secure implementation following WordPress best practices
* 🎯 Easy integration with n8n webhooks

= How It Works =

1. Install and activate the plugin
2. Go to Settings > n8n Chat
3. Enter your n8n webhook URL
4. Customize colors, text, and behavior
5. Save settings - the widget appears automatically on your site

= n8n Integration =

This plugin requires an n8n webhook to function. Your n8n workflow should:

* Accept POST requests with JSON payload: `{"message": "user message"}`
* Return JSON response: `{"response": "bot reply"}`

= Privacy & Data =

This plugin sends user messages to your configured n8n webhook URL. You are responsible for ensuring compliance with applicable data protection regulations (GDPR, CCPA, etc.) and informing users about data processing in your privacy policy.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins > Add New
3. Search for "n8n Chat Widget"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Go to Plugins > Add New > Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

= Configuration =

1. Go to Settings > n8n Chat
2. Enter your n8n webhook URL (required)
3. Customize appearance and behavior settings
4. Click "Save Settings"

== Frequently Asked Questions ==

= What is n8n? =

n8n is a workflow automation platform that allows you to connect various apps and services. Visit https://n8n.io for more information.

= Do I need an n8n account? =

Yes, you need a running n8n instance (self-hosted or cloud) with a webhook configured to handle chat messages.

= What format should my webhook use? =

Your webhook should accept POST requests with JSON: `{"message": "text"}` and return: `{"response": "reply text"}`

= Can I customize the widget appearance? =

Yes! You can customize colors, position, avatar, text messages, and more from the Settings page.

= Is the widget mobile-friendly? =

Yes, the widget is fully responsive and works perfectly on mobile devices.

= Does this plugin work with caching plugins? =

Yes, the widget is designed to work with caching plugins as it loads dynamically.

= How do I hide the widget on specific pages? =

Currently, the widget appears on all pages. You can use custom CSS or conditional logic in the plugin code to hide it on specific pages.

== Screenshots ==

1. Chat widget button on website
2. Teaser bubble engaging visitors
3. Chat window interface
4. Admin settings page
5. Color customization options

== Changelog ==

= 1.0.1 =
* Security: Added Subresource Integrity (SRI) verification for markdown-it CDN library
* Security: Addresses CVE-2025-7969 (reported XSS vulnerability in markdown-it v14.1.0)
* Security: Implements SHA-384 integrity hash to prevent CDN tampering and MITM attacks
* Enhanced: Added inline documentation for security considerations

= 1.0.0 =
* Initial release
* Customizable chat widget with n8n webhook integration
* Full color and positioning customization
* Teaser bubble and notification badge
* Mobile-responsive design
* WordPress Settings API implementation
* Secure data handling

== Upgrade Notice ==

= 1.0.1 =
Security update: Added Subresource Integrity (SRI) protection for markdown-it CDN library to address CVE-2025-7969.

= 1.0.0 =
Initial release of the n8n Chat Widget plugin.

== External Services ==

This plugin connects to an external service (n8n) that you configure:

* Service: n8n (https://n8n.io)
* Purpose: Processing chat messages and generating responses
* Data Sent: User messages submitted through the chat widget
* When: Only when users actively send messages through the chat
* Privacy: You control the n8n instance and data handling
* Terms: Refer to your n8n hosting provider's terms of service

You are responsible for:
* Configuring your n8n webhook correctly
* Ensuring GDPR/privacy compliance
* Informing users about data processing in your privacy policy
* Securing your n8n instance

== Support ==

For support, feature requests, or bug reports, please visit the plugin's GitHub repository or WordPress.org support forum.

== Credits ==

Developed for HVAC Growth

