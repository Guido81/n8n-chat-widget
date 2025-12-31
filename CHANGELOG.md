# Changelog

All notable changes to the n8n Chat Widget plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-12-31

### Security
- Added Subresource Integrity (SRI) verification for markdown-it CDN library
  - Addresses CVE-2025-7969 (reported XSS vulnerability in markdown-it v14.1.0)
  - Implements SHA-384 integrity hash to prevent CDN tampering and MITM attacks
  - Adds `crossorigin="anonymous"` attribute for CORS integrity checks
  - Note: Vendor disputes vulnerability classification, but SRI provides defense-in-depth
- Added monitoring reminder for markdown-it security updates
- Generated and verified SRI hash: `sha384-wLhprpjsmjc/XYIcF+LpMxd8yS1gss6jhevOp6F6zhiIoFK6AmHtm4bGKtehTani`

### Changed
- Enhanced inline documentation for markdown-it script loading with security notes

## [1.0.0] - 2025-12-31

### Added
- Initial release of n8n Chat Widget
- Customizable chat widget with n8n webhook integration
- Admin settings page under Settings > n8n Chat
- Full color customization (primary, secondary, background)
- Position selection (left or right)
- Teaser bubble with auto-show option
- Notification badge with custom count
- Custom avatar support
- Welcome message on first open
- Configurable text for all UI elements
- Mobile-responsive design
- HTML5 color picker for better UX
- Conditional widget display (only shows if webhook URL is configured)
- Proper uninstall cleanup
- Security features:
  - Custom hex color sanitization
  - Input validation and sanitization
  - XSS protection with proper escaping
  - Capability checks for admin functions
- Translation-ready with text domain
- WordPress.org compatible readme.txt

### Security
- Implemented custom `sanitize_hex_color_field()` function
- All user inputs properly sanitized
- All outputs properly escaped
- Capability checks on admin pages
- Direct file access prevention
- CSRF protection via WordPress Settings API

### Performance
- Lightweight implementation
- Minimal JavaScript and CSS
- Efficient asset loading
- CSS variables for dynamic theming

## [Unreleased]

### Planned Features
- Session/cookie support to remember chat state
- Message history persistence
- Typing indicator improvements
- File upload support
- Emoji picker
- Sound notifications
- Custom CSS option in admin
- Widget enable/disable toggle
- Page-specific display rules
- Custom positioning options
- Analytics integration
- Multi-language support

