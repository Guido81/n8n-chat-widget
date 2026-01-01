<?php
/**
 * Plugin Name: n8n Chat Widget
 * Plugin URI: https://example.com/n8n-chat-widget
 * Description: Embeds a customizable chat widget connected to an n8n webhook for seamless customer communication.
 * Version: 1.0.4
 * Author: HVAC Growth
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: n8n-chat-widget
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('N8N_CHAT_WIDGET_VERSION', '1.0.4');
define('N8N_CHAT_WIDGET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('N8N_CHAT_WIDGET_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class N8N_Chat_Widget {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_footer', array($this, 'output_widget_html'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks for chat proxy (works for both logged-in and non-logged-in users)
        add_action('wp_ajax_n8n_chat_proxy', array($this, 'handle_chat_proxy'));
        add_action('wp_ajax_nopriv_n8n_chat_proxy', array($this, 'handle_chat_proxy'));
        
        // Security: Add SRI (Subresource Integrity) to markdown-it CDN script
        add_filter('script_loader_tag', array($this, 'add_sri_to_markdown_it'), 10, 3);
    }
    
    /**
     * Get default settings
     */
    public function get_default_settings() {
        return array(
            'enabled' => true,
            'webhookUrl' => '',
            'primaryColor' => '#00BFA5',
            'secondaryColor' => '#009688',
            'backgroundColor' => '#FFFFFF',
            'position' => 'right',
            'teaserText' => 'Ready to grow your HVAC business? Chat with us now. We typically reply in minutes.',
            'showTeaserOnLoad' => true,
            'teaserAvatar' => 'https://example.com/avatar.jpg',
            'headerName' => 'Kevin from HVAC Growth',
            'responseTimeText' => 'We typically reply in minutes',
            'welcomeMessage' => 'Hi there! 👋 Have a question about HVAC marketing? I\'m here to help!',
            'poweredByText' => 'Powered by HVAC Growth',
            'poweredByLink' => '#',
            'showBadge' => true,
            'badgeCount' => 1
        );
    }
    
    /**
     * Get plugin settings with defaults
     */
    public function get_settings() {
        $defaults = $this->get_default_settings();
        $settings = get_option('n8n_chat_settings', array());
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Handle chat proxy with secure session management using HttpOnly cookies
     * 
     * This endpoint:
     * 1. Checks for existing session cookie
     * 2. Generates new session ID if needed (using cryptographically secure method)
     * 3. Sets HttpOnly, Secure, SameSite=Strict cookie
     * 4. Forwards request to n8n with sessionId
     * 5. Returns n8n response to frontend
     */
    public function handle_chat_proxy() {
        // Verify nonce for CSRF protection
        if (!check_ajax_referer('n8n_chat_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
            return;
        }
        
        // Get settings
        $settings = $this->get_settings();
        $webhook_url = $settings['webhookUrl'];
        
        // Validate webhook URL
        if (empty($webhook_url)) {
            // Log detailed error server-side
            error_log('N8N Chat Widget - Configuration Error: Webhook URL not configured');
            
            // Return generic error to client
            wp_send_json_error(array('message' => 'Chat service is not available'), 500);
            return;
        }
        
        // Get or generate session ID
        $cookie_name = 'n8n_chat_session';
        $session_id = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : null;
        
        // Generate new session ID if none exists
        if (empty($session_id)) {
            // Use WordPress's wp_generate_uuid4() for cryptographically secure UUID generation
            $session_id = wp_generate_uuid4();
            
            // Set HttpOnly cookie with secure flags
            // Cookie expires in 1 hour (3600 seconds)
            $is_secure = is_ssl(); // Only set Secure flag if site uses HTTPS
            $cookie_set = setcookie(
                $cookie_name,
                $session_id,
                array(
                    'expires' => time() + 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $is_secure,
                    'httponly' => true,
                    'samesite' => 'Strict'
                )
            );
            
            // If cookie setting failed, log error but continue (cookie might be set on next request)
            if (!$cookie_set) {
                error_log('N8N Chat Widget: Failed to set session cookie');
            }
        }
        
        // Get message from POST data (sent as FormData from frontend)
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        
        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message is required'), 400);
            return;
        }
        
        // Prepare request to n8n with sessionId
        $n8n_request_body = array(
            'message' => $message,
            'sessionId' => $session_id
        );
        
        // Forward request to n8n webhook
        $response = wp_remote_post($webhook_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($n8n_request_body),
            'timeout' => 30,
            'sslverify' => true
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            // Log detailed error server-side
            error_log('N8N Chat Widget - Connection Error: ' . $response->get_error_message());
            
            // Parse webhook URL and redact sensitive parts (query parameters, tokens)
            $parsed_url = wp_parse_url($webhook_url);
            $safe_url = '';
            if ($parsed_url) {
                $safe_url = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? 'unknown');
                if (!empty($parsed_url['path'])) {
                    $safe_url .= $parsed_url['path'];
                }
                // Indicate query string was present but redacted
                if (!empty($parsed_url['query'])) {
                    $safe_url .= '?[REDACTED]';
                }
            } else {
                $safe_url = '[INVALID_URL]';
            }
            error_log('N8N Chat Widget - Webhook URL (sanitized): ' . $safe_url);
            
            // Return generic error to client
            wp_send_json_error(array('message' => 'Failed to connect to chat service'), 500);
            return;
        }
        
        // Get response body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            // Log safe metadata only (no PII)
            $response_headers = wp_remote_retrieve_headers($response);
            $content_type = isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown';
            $body_length = strlen($response_body);
            $body_checksum = hash('sha256', $response_body);
            $timestamp = gmdate('Y-m-d H:i:s');
            
            error_log('N8N Chat Widget - HTTP Error: Response code ' . $response_code);
            error_log('N8N Chat Widget - Response metadata: Length=' . $body_length . ' bytes, Content-Type=' . $content_type . ', Checksum=' . $body_checksum . ', Timestamp=' . $timestamp);
            
            // Return generic error to client
            wp_send_json_error(array('message' => 'Chat service returned an error'), 500);
            return;
        }
        
        // Parse and return n8n response
        $n8n_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log safe metadata only (no PII)
            $response_headers = wp_remote_retrieve_headers($response);
            $content_type = isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown';
            $body_length = strlen($response_body);
            $body_hash_truncated = substr(hash('sha256', $response_body), 0, 8);
            
            error_log('N8N Chat Widget - JSON Parse Error: ' . json_last_error_msg());
            error_log('N8N Chat Widget - Response metadata: HTTP ' . $response_code . ', Content-Type=' . $content_type . ', Length=' . $body_length . ' bytes, Hash=' . $body_hash_truncated);
            
            // Return generic error to client
            wp_send_json_error(array('message' => 'Invalid response from chat service'), 500);
            return;
        }
        
        // Return successful response
        wp_send_json_success($n8n_data);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Get settings and check if widget is enabled
        $settings = $this->get_settings();
        
        // Return early if widget is not enabled
        if (!(bool)$settings['enabled']) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'n8n-chat-widget-style',
            N8N_CHAT_WIDGET_PLUGIN_URL . 'assets/css/style.css',
            array(),
            N8N_CHAT_WIDGET_VERSION
        );
        
        // Enqueue markdown-it library from CDN
        // Note: v14.1.0 has reported CVE-2025-7969 (disputed by vendor)
        // SRI hash added via script_loader_tag filter for additional security
        // TODO: Monitor https://github.com/markdown-it/markdown-it for patched version
        wp_enqueue_script(
            'markdown-it',
            'https://cdn.jsdelivr.net/npm/markdown-it@14.1.0/dist/markdown-it.min.js',
            array(),
            '14.1.0',
            true
        );
        
        // Enqueue DOMPurify for XSS protection when rendering markdown
        wp_enqueue_script(
            'dompurify',
            'https://cdn.jsdelivr.net/npm/dompurify@3.0.8/dist/purify.min.js',
            array(),
            '3.0.8',
            true
        );
        
        // Enqueue JS with markdown-it and DOMPurify dependencies
        wp_enqueue_script(
            'n8n-chat-widget-script',
            N8N_CHAT_WIDGET_PLUGIN_URL . 'assets/js/script.js',
            array('markdown-it', 'dompurify'),
            N8N_CHAT_WIDGET_VERSION,
            true
        );
        
        // Add AJAX URL and nonce to settings for secure communication
        $settings['ajaxUrl'] = admin_url('admin-ajax.php');
        $settings['nonce'] = wp_create_nonce('n8n_chat_nonce');
        
        // Localize script with config
        wp_localize_script('n8n-chat-widget-script', 'ChatWidgetConfig', $settings);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ('settings_page_n8n-chat-widget' !== $hook) {
            return;
        }
        
        // Add inline script for color picker sync using event delegation
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                // Use event delegation for idempotent handling
                $(document).on('change', '.n8n-color-picker', function() {
                    var textInput = $(this).next('.n8n-color-text');
                    if (textInput.length) {
                        textInput.val(this.value);
                    }
                });
            });
        ");
    }
    
    /**
     * Add Subresource Integrity (SRI) to markdown-it CDN script
     * 
     * Security: markdown-it v14.1.0 has a reported XSS vulnerability (CVE-2025-7969).
     * While the vendor disputes this, we add SRI verification to ensure the CDN
     * file hasn't been tampered with.
     * 
     * @param string $tag    The script tag.
     * @param string $handle The script handle.
     * @param string $src    The script source URL.
     * @return string Modified script tag with integrity attribute.
     */
    public function add_sri_to_markdown_it($tag, $handle, $src) {
        if ($handle === 'markdown-it') {
            // SHA-384 hash computed from https://cdn.jsdelivr.net/npm/markdown-it@14.1.0/dist/markdown-it.min.js
            // Generated on: 2025-12-31
            $integrity = 'sha384-wLhprpjsmjc/XYIcF+LpMxd8yS1gss6jhevOp6F6zhiIoFK6AmHtm4bGKtehTani';
            $tag = str_replace('></script>', ' integrity="' . $integrity . '" crossorigin="anonymous"></script>', $tag);
        }
        return $tag;
    }
    
    /**
     * Output widget HTML in footer
     */
    public function output_widget_html() {
        $settings = $this->get_settings();
        
        // Only show widget if enabled
        if (empty($settings['enabled'])) {
            return;
        }
        
        // Only show widget if webhook URL is configured
        if (empty($settings['webhookUrl'])) {
            return;
        }
        ?>
        <div id="chat-widget-container">
            <div id="chat-button">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                <div id="chat-badge"><?php echo esc_html($settings['badgeCount']); ?></div>
            </div>
            <div id="teaser-bubble">
                <span id="teaser-close">×</span>
                <div style="display: flex; align-items: start;">
                    <img id="teaser-avatar" src="<?php echo esc_url($settings['teaserAvatar']); ?>" alt="Avatar">
                    <div id="teaser-text"><?php echo esc_html($settings['teaserText']); ?></div>
                </div>
            </div>
            <div id="chat-window">
                <div id="chat-header">
                    <img id="header-avatar" src="<?php echo esc_url($settings['teaserAvatar']); ?>" alt="Avatar">
                    <div>
                        <div id="header-name"><?php echo esc_html($settings['headerName']); ?></div>
                        <div id="header-response"><?php echo esc_html($settings['responseTimeText']); ?></div>
                    </div>
                    <span id="header-close">×</span>
                </div>
                <div id="chat-messages"></div>
                <div id="chat-input">
                    <input id="input-field" type="text" placeholder="Type your message...">
                    <button id="send-button">Send</button>
                </div>
                <div id="powered-by">
                    <?php if (!empty($settings['poweredByLink']) && $settings['poweredByLink'] !== '#'): ?>
                        <a href="<?php echo esc_url($settings['poweredByLink']); ?>" target="_blank" rel="noopener"><?php echo esc_html($settings['poweredByText']); ?></a>
                    <?php else: ?>
                        <?php echo esc_html($settings['poweredByText']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('n8n Chat Settings', 'n8n-chat-widget'),
            __('n8n Chat', 'n8n-chat-widget'),
            'manage_options',
            'n8n-chat-widget',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'n8n_chat_settings_group',
            'n8n_chat_settings',
            array($this, 'sanitize_settings')
        );
        
        // Add settings section
        add_settings_section(
            'n8n_chat_main_section',
            __('Chat Widget Configuration', 'n8n-chat-widget'),
            array($this, 'render_section_description'),
            'n8n-chat-widget'
        );
        
        // Enable Widget
        add_settings_field(
            'enabled',
            __('Enable Widget', 'n8n-chat-widget'),
            array($this, 'render_checkbox_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'enabled',
                'description' => __('Check this box to display the chat widget on your website', 'n8n-chat-widget')
            )
        );
        
        // Webhook URL (required)
        add_settings_field(
            'webhookUrl',
            __('Webhook URL', 'n8n-chat-widget'),
            array($this, 'render_text_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'webhookUrl',
                'description' => __('Your n8n webhook URL (required)', 'n8n-chat-widget'),
                'required' => true
            )
        );
        
        // Primary Color
        add_settings_field(
            'primaryColor',
            __('Primary Color', 'n8n-chat-widget'),
            array($this, 'render_color_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'primaryColor',
                'description' => __('Main color for the widget (default: #00BFA5)', 'n8n-chat-widget')
            )
        );
        
        // Secondary Color
        add_settings_field(
            'secondaryColor',
            __('Secondary Color', 'n8n-chat-widget'),
            array($this, 'render_color_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'secondaryColor',
                'description' => __('Secondary color for gradients (default: #009688)', 'n8n-chat-widget')
            )
        );
        
        // Background Color
        add_settings_field(
            'backgroundColor',
            __('Background Color', 'n8n-chat-widget'),
            array($this, 'render_color_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'backgroundColor',
                'description' => __('Chat window background color (default: #FFFFFF)', 'n8n-chat-widget')
            )
        );
        
        // Position
        add_settings_field(
            'position',
            __('Position', 'n8n-chat-widget'),
            array($this, 'render_select_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'position',
                'options' => array(
                    'right' => __('Right', 'n8n-chat-widget'),
                    'left' => __('Left', 'n8n-chat-widget')
                ),
                'description' => __('Widget position on screen', 'n8n-chat-widget')
            )
        );
        
        // Teaser Text
        add_settings_field(
            'teaserText',
            __('Teaser Text', 'n8n-chat-widget'),
            array($this, 'render_textarea_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'teaserText',
                'description' => __('Text shown in the teaser bubble', 'n8n-chat-widget')
            )
        );
        
        // Show Teaser on Load
        add_settings_field(
            'showTeaserOnLoad',
            __('Show Teaser on Load', 'n8n-chat-widget'),
            array($this, 'render_checkbox_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'showTeaserOnLoad',
                'description' => __('Display teaser bubble when page loads', 'n8n-chat-widget')
            )
        );
        
        // Teaser Avatar
        add_settings_field(
            'teaserAvatar',
            __('Avatar URL', 'n8n-chat-widget'),
            array($this, 'render_text_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'teaserAvatar',
                'description' => __('URL to avatar image', 'n8n-chat-widget')
            )
        );
        
        // Header Name
        add_settings_field(
            'headerName',
            __('Header Name', 'n8n-chat-widget'),
            array($this, 'render_text_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'headerName',
                'description' => __('Name shown in chat header', 'n8n-chat-widget')
            )
        );
        
        // Response Time Text
        add_settings_field(
            'responseTimeText',
            __('Response Time Text', 'n8n-chat-widget'),
            array($this, 'render_text_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'responseTimeText',
                'description' => __('Text shown below header name', 'n8n-chat-widget')
            )
        );
        
        // Welcome Message
        add_settings_field(
            'welcomeMessage',
            __('Welcome Message', 'n8n-chat-widget'),
            array($this, 'render_textarea_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'welcomeMessage',
                'description' => __('First message shown when chat opens', 'n8n-chat-widget')
            )
        );
        
        // Powered By Text
        add_settings_field(
            'poweredByText',
            __('Powered By Text', 'n8n-chat-widget'),
            array($this, 'render_text_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'poweredByText',
                'description' => __('Text shown in footer', 'n8n-chat-widget')
            )
        );
        
        // Powered By Link
        add_settings_field(
            'poweredByLink',
            __('Powered By Link', 'n8n-chat-widget'),
            array($this, 'render_text_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'poweredByLink',
                'description' => __('URL for powered by link (optional)', 'n8n-chat-widget')
            )
        );
        
        // Show Badge
        add_settings_field(
            'showBadge',
            __('Show Badge', 'n8n-chat-widget'),
            array($this, 'render_checkbox_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'showBadge',
                'description' => __('Display notification badge on chat button', 'n8n-chat-widget')
            )
        );
        
        // Badge Count
        add_settings_field(
            'badgeCount',
            __('Badge Count', 'n8n-chat-widget'),
            array($this, 'render_number_field'),
            'n8n-chat-widget',
            'n8n_chat_main_section',
            array(
                'field_id' => 'badgeCount',
                'description' => __('Number shown in badge', 'n8n-chat-widget')
            )
        );
    }
    
    /**
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . esc_html__('Configure your n8n chat widget settings below.', 'n8n-chat-widget') . '</p>';
    }
    
    /**
     * Render text field
     */
    public function render_text_field($args) {
        $settings = $this->get_settings();
        $field_id = $args['field_id'];
        $value = isset($settings[$field_id]) ? $settings[$field_id] : '';
        $required = isset($args['required']) && $args['required'] ? 'required' : '';
        
        echo '<input type="text" name="n8n_chat_settings[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="regular-text" ' . $required . '>';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Render color field
     */
    public function render_color_field($args) {
        $settings = $this->get_settings();
        $field_id = $args['field_id'];
        $value = isset($settings[$field_id]) ? $settings[$field_id] : '';
        
        echo '<input type="color" name="n8n_chat_settings[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="n8n-color-picker">';
        echo ' <input type="text" value="' . esc_attr($value) . '" class="regular-text n8n-color-text" readonly style="margin-left: 10px; width: 100px;">';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Render textarea field
     */
    public function render_textarea_field($args) {
        $settings = $this->get_settings();
        $field_id = $args['field_id'];
        $value = isset($settings[$field_id]) ? $settings[$field_id] : '';
        
        echo '<textarea name="n8n_chat_settings[' . esc_attr($field_id) . ']" class="large-text" rows="3">' . esc_textarea($value) . '</textarea>';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Render select field
     */
    public function render_select_field($args) {
        $settings = $this->get_settings();
        $field_id = $args['field_id'];
        $value = isset($settings[$field_id]) ? $settings[$field_id] : '';
        $options = $args['options'];
        
        echo '<select name="n8n_chat_settings[' . esc_attr($field_id) . ']">';
        foreach ($options as $option_value => $option_label) {
            $selected = selected($value, $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Render checkbox field
     */
    public function render_checkbox_field($args) {
        $settings = $this->get_settings();
        $field_id = $args['field_id'];
        $value = isset($settings[$field_id]) ? $settings[$field_id] : false;
        $checked = checked($value, true, false);
        
        echo '<input type="checkbox" name="n8n_chat_settings[' . esc_attr($field_id) . ']" value="1" ' . $checked . '>';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Render number field
     */
    public function render_number_field($args) {
        $settings = $this->get_settings();
        $field_id = $args['field_id'];
        $value = isset($settings[$field_id]) ? $settings[$field_id] : '';
        
        echo '<input type="number" name="n8n_chat_settings[' . esc_attr($field_id) . ']" value="' . esc_attr($value) . '" class="small-text" min="0">';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Sanitize hex color
     */
    private function sanitize_hex_color_field($color) {
        // Remove any whitespace
        $color = trim($color);
        
        // Check if it's a valid hex color
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return $color;
        }
        
        // If no hash, add it
        if (preg_match('/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return '#' . $color;
        }
        
        return '';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        $defaults = $this->get_default_settings();
        
        // Webhook URL (required)
        if (isset($input['webhookUrl'])) {
            $sanitized['webhookUrl'] = esc_url_raw($input['webhookUrl']);
        }
        
        // Colors
        $sanitized['primaryColor'] = isset($input['primaryColor']) ? $this->sanitize_hex_color_field($input['primaryColor']) : $defaults['primaryColor'];
        $sanitized['secondaryColor'] = isset($input['secondaryColor']) ? $this->sanitize_hex_color_field($input['secondaryColor']) : $defaults['secondaryColor'];
        $sanitized['backgroundColor'] = isset($input['backgroundColor']) ? $this->sanitize_hex_color_field($input['backgroundColor']) : $defaults['backgroundColor'];
        
        // Fallback to defaults if sanitization returns empty
        if (empty($sanitized['primaryColor'])) {
            $sanitized['primaryColor'] = $defaults['primaryColor'];
        }
        if (empty($sanitized['secondaryColor'])) {
            $sanitized['secondaryColor'] = $defaults['secondaryColor'];
        }
        if (empty($sanitized['backgroundColor'])) {
            $sanitized['backgroundColor'] = $defaults['backgroundColor'];
        }
        
        // Position
        $sanitized['position'] = isset($input['position']) && in_array($input['position'], array('left', 'right')) ? $input['position'] : $defaults['position'];
        
        // Text fields
        $sanitized['teaserText'] = isset($input['teaserText']) ? sanitize_text_field($input['teaserText']) : $defaults['teaserText'];
        $sanitized['headerName'] = isset($input['headerName']) ? sanitize_text_field($input['headerName']) : $defaults['headerName'];
        $sanitized['responseTimeText'] = isset($input['responseTimeText']) ? sanitize_text_field($input['responseTimeText']) : $defaults['responseTimeText'];
        $sanitized['welcomeMessage'] = isset($input['welcomeMessage']) ? sanitize_textarea_field($input['welcomeMessage']) : $defaults['welcomeMessage'];
        $sanitized['poweredByText'] = isset($input['poweredByText']) ? sanitize_text_field($input['poweredByText']) : $defaults['poweredByText'];
        
        // URLs
        $sanitized['teaserAvatar'] = isset($input['teaserAvatar']) ? esc_url_raw($input['teaserAvatar']) : $defaults['teaserAvatar'];
        $sanitized['poweredByLink'] = isset($input['poweredByLink']) ? esc_url_raw($input['poweredByLink']) : $defaults['poweredByLink'];
        
        // Checkboxes
        $sanitized['enabled'] = isset($input['enabled']) && $input['enabled'] == '1';
        $sanitized['showTeaserOnLoad'] = isset($input['showTeaserOnLoad']) && $input['showTeaserOnLoad'] == '1';
        $sanitized['showBadge'] = isset($input['showBadge']) && $input['showBadge'] == '1';
        
        // Number
        $sanitized['badgeCount'] = isset($input['badgeCount']) ? absint($input['badgeCount']) : $defaults['badgeCount'];
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if settings were updated
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'n8n_chat_messages',
                'n8n_chat_message',
                __('Settings Saved', 'n8n-chat-widget'),
                'updated'
            );
        }
        
        settings_errors('n8n_chat_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('n8n_chat_settings_group');
                do_settings_sections('n8n-chat-widget');
                submit_button(__('Save Settings', 'n8n-chat-widget'));
                ?>
            </form>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin
 */
function n8n_chat_widget_init() {
    return N8N_Chat_Widget::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'n8n_chat_widget_init');

