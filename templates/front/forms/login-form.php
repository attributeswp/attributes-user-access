<?php
/**
 * Template for the login form
 *
 * This template handles the rendering of the login form with proper
 * security measures and error handling.
 *
 * @package Attributes\Front
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $args is set
if (!isset($args)) {
    $args = [];
}

// Start session if not already started
if (!session_id()) {
    session_start();
}

// Get the redirect URL
$redirect_to = !empty($args['redirect']) ? $args['redirect'] : '';

// Get potential error message
$error_message = isset($error_message) ? $error_message : '';
?>

<div class="attrua-form-wrapper">
    <!-- Error Messages Container -->
    <?php if (!empty($error_message)): ?>
        <div class="attrua-message-container error visible">
            <div class="attrua-message">
                <?php echo wp_kses_post($error_message); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form id="<?php echo esc_attr($args['form_id']); ?>" 
          class="attrua-login-form" 
          method="post" 
          action="">
        
        <?php wp_nonce_field('attrua_login', 'attrua_login_nonce'); ?>
        <input type="hidden" name="attrua_login_submit" value="1">

        <!-- Username/Email Field -->
        <div class="attrua-form-row">
            <label for="attrua_username">
                <?php echo esc_html($args['label_username']); ?>
                <span class="required">*</span>
            </label>
            <input type="text" 
                   name="log" 
                   id="attrua_username"
                   class="attrua-input" 
                   value="<?php echo esc_attr($args['value_username']); ?>"
                   required
                   autocomplete="username">
            <div class="attrua-field-error"></div>
        </div>

        <!-- Password Field -->
        <div class="attrua-form-row">
            <label for="attrua_password">
                <?php echo esc_html($args['label_password']); ?>
                <span class="required">*</span>
            </label>
            <div class="attrua-password-field">
                <input type="password" 
                       name="pwd" 
                       id="attrua_password"
                       class="attrua-input" 
                       required
                       autocomplete="current-password">
                <button type="button" 
                        class="attrua-toggle-password" 
                        aria-label="<?php esc_attr_e('Toggle password visibility', 'attributes-user-access'); ?>">
                    <span class="ti ti-eye"></span>
                </button>
            </div>
            <div class="attrua-field-error"></div>
        </div>

        <!-- Remember Me -->
        <?php if ($args['remember']): ?>
            <div class="attrua-form-row">
                <label class="attrua-checkbox-label">
                    <input type="checkbox" 
                           name="rememberme" 
                           id="attrua_remember"
                           value="forever"
                           <?php checked($args['value_remember'], true); ?>>
                    <span><?php echo esc_html($args['label_remember']); ?></span>
                </label>
            </div>
        <?php endif; ?>

        <!-- Submit Button -->
        <div class="attrua-form-row">
            <button type="submit" 
                    class="attrua-submit-button"
                    data-loading-text="<?php esc_attr_e('Logging in...', 'attributes-user-access'); ?>">
                <?php echo esc_html($args['label_log_in']); ?>
            </button>
        </div>

        <?php if (!empty($redirect_to)): ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
        <?php endif; ?>

        <!-- Additional Links -->
        <div class="attrua-form-links">
            <?php if (get_option('users_can_register')): ?>
                <?php
                // Get settings
                $pages = get_option('attrua_pages_options', []);
                $redirects = get_option('attrua_redirect_options', []);
                
                // Check if custom registration page is enabled
                $register_page_id = $pages['register'] ?? null;
                $register_url = ($register_page_id && !empty($redirects['register'])) 
                    ? get_permalink($register_page_id) 
                    : wp_registration_url();
                ?>
                <a href="<?php echo esc_url($register_url); ?>" class="attrua-register-link">
                    <?php esc_html_e('Register', 'attributes-user-access'); ?>
                </a>
                <span class="attrua-link-separator">|</span>
            <?php endif; ?>

            <?php
            // Check if custom lost password page is enabled
            $lost_password_page_id = $pages['lost_password'] ?? null;
            $lost_password_url = ($lost_password_page_id && !empty($redirects['lost_password'])) 
                ? get_permalink($lost_password_page_id) 
                : wp_lostpassword_url();
            ?>
            <a href="<?php echo esc_url($lost_password_url); ?>" class="attrua-lost-password-link">
                <?php esc_html_e('Lost your password?', 'attributes-user-access'); ?>
            </a>
        </div>

        <?php
        /**
         * Hook for adding custom fields to login form
         *
         * @since 1.0.0
         */
        do_action('attrua_login_form_fields');
        ?>
    </form>

    <?php
    /**
     * Hook for adding content after login form
     *
     * @since 1.0.0
     */
    do_action('attrua_after_login_form');
    ?>
</div>