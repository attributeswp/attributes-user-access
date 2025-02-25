<?php
namespace Attributes\Front;

use Attributes\Core\Settings;

/**
 * Login Handler Class
 *
 * Manages user authentication, login form rendering, and related functionality.
 * Implements secure login processes with proper validation and error handling.
 *
 * @package Attributes\Front
 * @since   1.0.0
 */
class Login {

    /**
     * Core settings instance.
     *
     * @since  1.0.0
     * @access private
     * @var    Settings
     */
    private Settings $settings;

    /**
     * Constructor.
     *
     * Initialize the login handler and set up required hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->settings = new Settings();
        $this->attrua_init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     *
     * Sets up all necessary action and filter hooks for login functionality.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function attrua_init_hooks(): void {
        // Form processing
        add_action('init', [$this, 'attrua_handle_login_form']);
        add_action('wp_login_failed', [$this, 'attrua_handle_failed_login']);

        // Page redirection
        add_action('init', [$this, 'attrua_login_page_redirect']);
        add_filter('login_redirect', [$this, 'attrua_handle_login_redirect'], 10, 3);

        // Shortcode registration
        add_shortcode('attributes_login_form', [$this, 'attrua_render_login_form']);

        // Error messages
        add_filter('login_errors', [$this, 'attrua_customize_login_errors']);

        // Custom logout handling
        add_action('wp_loaded', [$this, 'attrua_handle_custom_logout'], 120);
        add_filter('logout_url', [$this, 'attrua_modify_logout_url'], 100, 2);
    }

    /**
     * Render login form via shortcode.
     *
     * Generates and returns the HTML for the login form, including error messages
     * and success notifications.
     *
     * @since  1.0.0
     * @param  array  $atts    Shortcode attributes.
     * @param  string $content Shortcode content.
     * @return string Generated HTML for the login form.
     */
    public function attrua_render_login_form(array $atts = [], string $content = ''): string {
        // Early return for logged-in users
        if (is_user_logged_in()) {
            return sprintf(
                '<p>%s <a href="%s">%s</a></p>',
                esc_html__('You are already logged in.', 'attributes-user-access'),
                esc_url(wp_logout_url(home_url())),
                esc_html__('Logout', 'attributes-user-access')
            );
        }

        // Parse shortcode attributes
        $args = shortcode_atts([
            'redirect' => '',
            'form_id' => 'attrua_login_form',
            'label_username' => __('Username or Email', 'attributes-user-access'),
            'label_password' => __('Password', 'attributes-user-access'),
            'label_remember' => __('Remember Me', 'attributes-user-access'),
            'label_log_in' => __('Log In', 'attributes-user-access'),
            'remember' => true,
            'value_username' => '',
            'value_remember' => false
        ], $atts);

        // Get any error messages
        $error_message = $this->attrua_get_error_message();

        // Start output buffering
        ob_start();

        // Include the login form template
        include ATTRUA_PATH . 'templates/front/forms/login-form.php';

        // Return the generated HTML
        return ob_get_clean();
    }

    /**
     * Handle login form submission.
     *
     * Processes the login form submission, validates credentials,
     * and handles authentication.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_handle_login_form(): void {
        if (!isset($_POST['attrua_login_submit'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['attrua_login_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['attrua_login_nonce'])), 'attrua_login')) {
            wp_die(esc_html__('Security check failed.', 'attributes-user-access'));
        }

        // Extract and sanitize credentials
        $credentials = [
            'user_login' => isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '',
            'user_password' => isset($_POST['pwd']) ? wp_unslash($_POST['pwd']) : '',
            'remember' => isset($_POST['rememberme'])
        ];

        // Validate required fields
        if (empty($credentials['user_login']) || empty($credentials['user_password'])) {
            $this->attrua_handle_failed_login(
                new \WP_Error('empty_fields', __('Required fields missing.', 'attributes-user-access'))
            );
            return;
        }

        /**
         * Filter: attrua_login_credentials
         * 
         * Filters login credentials before authentication.
         * Allows modification of credentials before WordPress processes them.
         *
         * @since 1.0.0
         * @param array $credentials Array of login credentials.
         * @return array Modified credentials.
         */
        $credentials = apply_filters('attrua_login_credentials', $credentials);

        // Attempt authentication
        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            $this->attrua_handle_failed_login($user);
            return;
        }

        // Get redirect URL
        $redirect_to = isset($_POST['redirect_to']) && !empty($_POST['redirect_to']) 
            ? esc_url_raw(wp_unslash($_POST['redirect_to'])) 
            : $this->attrua_get_default_redirect_url($user);

        /**
         * Filter: attrua_login_redirect
         * 
         * Filters the redirect URL after successful login.
         * Customize where users are sent after logging in.
         *
         * @since 1.0.0
         * @param string $redirect Default redirect URL.
         * @param WP_User $user User who just logged in.
         * @return string Modified redirect URL.
         */
        $redirect_to = apply_filters('attrua_login_redirect_url', $redirect_to, $user);

        wp_safe_redirect($redirect_to);
        exit;
    }

    /**
     * Handle failed login attempts.
     *
     * Processes failed login attempts, stores error messages,
     * and handles redirection.
     *
     * @since  1.0.0
     * @access public
     * @param  \WP_Error $error Error object from failed login attempt.
     * @return void
     */
    public function attrua_handle_failed_login(\WP_Error $error): void {
        // Start session if not started
        if (!session_id()) {
            session_start();
        }

        // Store error message
        $_SESSION['attrua_login_error'] = $this->attrua_get_error_code_message($error->get_error_code());

        // Get redirect URL
        $redirect_url = wp_login_url();
        if ($login_page = $this->attrua_get_login_page()) {
            $redirect_url = get_permalink($login_page);
        }

        // Add error indicator to URL
        wp_safe_redirect(add_query_arg('login', 'failed', $redirect_url));
        exit;
    }

    /**
     * Redirect WordPress login page.
     *
     * Handles redirection of the default WordPress login page
     * to the custom login page when enabled.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_login_page_redirect(): void {
        if (is_admin()) {
            return;
        }

        // Check if redirection is enabled
        $redirect_enabled = $this->settings->attrua_get('redirects.login', false);
        if (!$redirect_enabled) {
            return;
        }

        // Get custom login page
        $login_page = $this->attrua_get_login_page();
        if (!$login_page) {
            return;
        }

        // Check if current request is for wp-login.php
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($request_uri, 'wp-login.php') === false) {
            return;
        }

        // Allow specific actions to bypass redirect
        $allowed_actions = ['logout', 'register', 'lostpassword', 'rp'];
        $query_params = [];
        $query_string = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '';
        parse_str($query_string, $query_params);

        if (isset($query_params['action']) && 
            in_array($query_params['action'], $allowed_actions)) {
            return;
        }

        // Don't redirect POST requests
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            return;
        }

        wp_safe_redirect(get_permalink($login_page));
        exit;
    }

    /**
     * Handle login redirection.
     *
     * Manages redirection after successful login based on user role
     * and plugin settings.
     *
     * @since  1.0.0
     * @access public
     * @param  string   $redirect_to            Default redirect URL.
     * @param  string   $requested_redirect_to  Requested redirect URL.
     * @param  \WP_User $user                  Authenticated user object.
     * @return string Modified redirect URL.
     */
    public function attrua_handle_login_redirect(
        string $redirect_to, 
        string $requested_redirect_to, 
        $user
    ): string {
        // Honor explicit redirect requests if present
        if (!empty($requested_redirect_to)) {
            return $requested_redirect_to;
        }

        // Handle failed login attempts
        if (\is_wp_error($user)) {
            return wp_login_url();
        }

        // Ensure user object is valid
        if (!($user instanceof \WP_User)) {
            return wp_login_url();
        }

        // Process successful login redirect
        return $this->attrua_get_default_redirect_url($user);
    }

    /**
     * Get default redirect URL.
     *
     * Determines the default redirect URL based on user role
     * and plugin settings.
     *
     * @since  1.0.0
     * @access private
     * @param  \WP_User $user User object.
     * @return string Default redirect URL.
     */
    private function attrua_get_default_redirect_url(\WP_User $user): string {
        // Get custom redirect from settings
        $redirect = $this->settings->attrua_get('redirects.login_default', '');

        // If no custom redirect, use role-based default
        if (empty($redirect)) {
            $redirect = current_user_can('manage_options') ? admin_url() : home_url();
        }

        /**
         * Filter the default redirect URL.
         *
         * Allows customization of the redirect path based on user role
         * or other criteria.
         *
         * @param string   $redirect The default redirect URL
         * @param \WP_User $user     The authenticated user object
         */
        return apply_filters('attrua_login_redirect', $redirect, $user);
    }

    /**
     * Get custom login page ID.
     *
     * Retrieves the ID of the custom login page if set.
     *
     * @since  1.0.0
     * @access private
     * @return int|null Page ID or null if not set.
     */
    private function attrua_get_login_page(): ?int {
        return $this->settings->attrua_get('pages.login');
    }

    /**
     * Handle custom logout endpoint
     * 
     * @return void
     */
    public function attrua_handle_custom_logout() {
        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($request_uri, '/logout') === false) {
            return;
        }

        wp_logout();

        // Get settings for custom login page
        $pages = get_option('attrua_pages_options', []);
        $redirects = get_option('attrua_redirect_options', []);
        $redirect_to = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';
        
        // Determine redirect URL
        if (!empty($redirect_to)) {
            $redirect_url = esc_url_raw($redirect_to);
        } else {
            // Use custom login page if enabled, otherwise default login
            $login_page_id = $pages['login'] ?? null;
            $redirect_url = ($login_page_id && !empty($redirects['login'])) 
                ? get_permalink($login_page_id)
                : wp_login_url();
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Modify the WordPress logout URL to use our custom endpoint
     * 
     * @param string $logout_url The default WordPress logout URL
     * @param string $redirect   The redirect URL
     * @return string Modified logout URL
     */
    public function attrua_modify_logout_url($logout_url, $redirect) {
        $logout_url = home_url('/logout');
        
        if (!empty($redirect)) {
            $logout_url = add_query_arg('redirect_to', urlencode($redirect), $logout_url);
        } else {
            // Get settings for custom login page
            $pages = get_option('attrua_pages_options', []);
            $redirects = get_option('attrua_redirect_options', []);
            
            // Add custom login page as default redirect if enabled
            $login_page_id = $pages['login'] ?? null;
            if ($login_page_id && !empty($redirects['login'])) {
                $logout_url = add_query_arg('redirect_to', urlencode(get_permalink($login_page_id)), $logout_url);
            }
        }
        
        return $logout_url;
    }

    /**
     * Get error message.
     *
     * Retrieves and formats error messages for display.
     *
     * @since  1.0.0
     * @access private
     * @return string Formatted error message HTML.
     */
    private function attrua_get_error_message(): string {
        if (!isset($_GET['login']) || $_GET['login'] !== 'failed') {
            return '';
        }

        if (!session_id()) {
            session_start();
        }

        $message = isset($_SESSION['attrua_login_error']) 
            ? sanitize_text_field($_SESSION['attrua_login_error']) 
            : __('Invalid username or password.', 'attributes-user-access');

        unset($_SESSION['attrua_login_error']);

        return sprintf(
            '<div class="attrua-error">%s</div>',
            esc_html($message)
        );
    }

    /**
     * Get error code message.
     *
     * Maps error codes to human-readable messages.
     *
     * @since  1.0.0
     * @access private
     * @param  string $code Error code.
     * @return string Human-readable error message.
     */
    private function attrua_get_error_code_message(string $code): string {
        $messages = [
            'empty_username' => __('Username field is empty.', 'attributes-user-access'),
            'empty_password' => __('Password field is empty.', 'attributes-user-access'),
            'invalid_username' => __('Unknown username.', 'attributes-user-access'),
            'incorrect_password' => __('Incorrect password.', 'attributes-user-access'),
            'empty_fields' => __('Required fields missing.', 'attributes-user-access')
        ];

        return $messages[$code] ?? __('An unknown error occurred.', 'attributes-user-access');
    }

    /**
     * Customize login error messages.
     *
     * Modifies default WordPress login error messages for better UX.
     *
     * @since  1.0.0
     * @access public
     * @param  string $error Default error message.
     * @return string Modified error message.
     */
    public function attrua_customize_login_errors(string $error): string {
        global $errors;
    
        if (!is_wp_error($errors)) {
            return $error;
        }
    
        $codes = $errors->get_error_codes();
        
        foreach ($codes as $code) {
            $message = $this->attrua_get_error_code_message($code);
            if ($message !== null) {
                return $message;
            }
        }
    
        return $error;
    }
}