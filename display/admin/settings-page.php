<?php
/**
 * Admin Settings Page Template
 * 
 * Implements an extensible administrative interface that:
 * - Supports modular feature addition through action hooks
 * - Maintains clean separation between free and premium features
 * - Uses WordPress Settings API for configuration management
 * 
 * Extension Points:
 * - attrua_before_admin_settings: Pre-content hook for notifications/headers
 * - attrua_admin_tabs: Tab rendering hook for premium features
 * - attrua_admin_settings_content: Content rendering hook
 * - attrua_after_admin_settings: Post-content hook for additional sections
 *
 * @package Attributes\Admin\Display
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determine if premium features are available
 * Premium plugin will filter this to true
 */
$has_premium_features = apply_filters('attributes_has_premium_features', false);

$icon_url = ATTRUA_URL . 'assets/img/attr-240.png';
?>

<div class="attrua-masthead">
    <div class="attrua-masthead-container">
        <div class="attrua-masthead-logo-container">
            <?php
            // Use wp_get_attachment_image if this is a media library image
            if (function_exists('wp_get_attachment_image') && is_numeric($icon_id)) {
                echo wp_get_attachment_image($icon_id, [128, 128], false, [
                    'class' => 'attrua-masthead-logo',
                    'alt' => 'Attributes WP logo'
                ]);
            } else {
                // Fallback for plugin-bundled images
                ?>
                <img class="attrua-masthead-logo" src="<?php echo esc_url($icon_url); ?>" alt="Attributes WP logo" width="128">
                <?php
            }
            ?>
            <?php echo esc_html(get_admin_page_title()); ?> <span class="attrua-version-number"><?php echo esc_html(ATTRUA_VERSION); ?></span>
    	</div>
    </div>
</div>

<div class="attrua-content-wrap">
    <?php
    /**
     * Pre-content hook for notifications or header content
     * 
     * @since 1.0.0
     * @param bool $has_premium_features Whether premium features are available
     */
    do_action('attrua_before_admin_settings', $has_premium_features); 
    ?>

    <?php if ($has_premium_features): ?>
        <nav class="nav-tab-wrapper">
            <?php
            /**
             * Render admin tabs for premium features
             * Premium plugin implements this to add its tabs
             * 
             * @since 1.0.0
             */
            do_action('attrua_admin_tabs');
            ?>
        </nav>
    <?php endif; ?>

    <!-- Premium Feature Notice -->
    <?php if (!apply_filters('attrua_is_premium', false)): ?>
    <div class="attrua-upgrade-notice notice  notice-info">
        <p>
            <?php
            printf(
            esc_html__('Upgrade to Premium for additional features: Two-Factor Authentication, Social Login, Custom Fields, and more. %1$s Learn More %2$s', 'attributes-user-access'),
            '<a href="' . esc_url('https://attributeswp.com/premium') . '" target="_blank">',
                '</a>'
            );
            ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="attrua-content">
        <div class="description">
            <div>
                <h3><?php esc_html_e('Page Management', 'attributes-user-access'); ?></h3>
                <?php echo esc_html('Create and manage a custom login page. Use the provided shortcode to display the login form on your page.', 'attributes-user-access'); ?>
            </div>
            <div>
                <h3><?php esc_html_e('Redirection', 'attributes-user-access'); ?></h3>
                <?php echo esc_html('Toggle redirection to automatically send users to your custom login page instead of the default WordPress login page.', 'attributes-user-access'); ?>
            </div>
        </div>

        <div id="attrua-pages-settings" class="attrua-settings-section">
            <?php
            /**
             * Render the main settings content
             * Base plugin renders the login table
             * Premium plugin can add additional content
             * 
             * @since 1.0.0
             * @param bool $has_premium_features Whether premium features are available
             */
            do_action('attrua_admin_settings_content', $has_premium_features);
            ?>
            
            <!-- Settings Form -->
            <form method="post" action="options.php" class="attrua-settings-form">                
                <?php
                    settings_fields('attrua_pages_group');
                    do_settings_sections('attributes-settings');
                    ?>
                    
                <table class="form-table attrua-pages-table" role="presentation">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Page Title', 'attributes-user-access'); ?></th>
                            <th><?php esc_html_e('Page Slug', 'attributes-user-access'); ?></th>
                            <th><?php esc_html_e('Shortcode', 'attributes-user-access'); ?></th>
                            <th><?php esc_html_e('Actions', 'attributes-user-access'); ?></th>
                            <th><?php esc_html_e('Redirect', 'attributes-user-access'); ?></th>
                        </tr>
                    </thead>
                    <?php $this->attrua_render_login_page_row(); ?>
                </table>
                <?php # submit_button(); ?>
            </form>
            
            <?php wp_nonce_field('attrua_settings_action', '_wpnonce'); ?>

            <?php do_action('attrua_admin_settings_content', $has_premium_features); ?>
        </div>

        <?php
        /**
         * Post-content hook for additional sections
         * Premium plugin can add marketing or additional features
         * 
         * @since 1.0.0
         * @param bool $has_premium_features Whether premium features are available
         */
        do_action('attrua_after_admin_settings', $has_premium_features);
        ?>
    </div>
</div>

<div class="attrua-footerline">
    <p>
        <a href="https://www.attributeswp.com" target="_blank">www.attributeswp.com</a>
    </p>
</div>