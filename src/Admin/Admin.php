<?php
namespace Attributes\Admin;

use Attributes\Core\Settings;

/**
 * Admin Settings Management Class
 *
 * Handles the plugin's administrative interface, settings registration,
 * and AJAX operations for the WordPress admin panel.
 *
 * @package Attributes\Admin
 * @since   1.0.0
 */
class Admin {

    /**
     * Core settings instance.
     *
     * @since  1.0.0
     * @access private
     * @var    Settings
     */
    private Settings $settings;

    /**
     * Admin page hook suffix.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private string $page_hook;

    /**
     * Initialize the class and set its properties.
     *
     * @since  1.0.0
     * @param  Settings $settings Core settings instance.
     * @return void
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->attrua_init_hooks();
    }

    /**
     * Register all necessary admin hooks.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function attrua_init_hooks(): void {
        // Admin menu and settings
        add_action('admin_menu', [$this, 'attrua_add_settings_page']);
        add_action('admin_init', [$this, 'attrua_register_settings']);

        // AJAX handlers
        add_action('wp_ajax_attrua_create_page', [$this, 'attrua_handle_create_page']);
        add_action('wp_ajax_attrua_delete_page', [$this, 'attrua_handle_delete_page']);
        add_action('wp_ajax_attrua_toggle_redirect', [$this, 'attrua_handle_toggle_redirect']);

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'attrua_enqueue_assets']);

        // Add post state for Attributes pages
        add_filter('display_post_states', [$this, 'attrua_add_post_state'], 10, 2);
    }

    /**
     * Add plugin settings page to WordPress admin menu.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_add_settings_page(): void {
        $icon_url = ATTRUA_URL . 'assets/img/attrua-icon.png';

        $this->page_hook = add_menu_page(
            __('Attributes User Access', 'attributes-user-access'),
            __('User Access', 'attributes-user-access'),
            'manage_options',
            'attributes-user-access',
            [$this, 'attrua_render_settings_page'],
            $icon_url,
            30
        );
    }

    /**
     * Register plugin settings and sections.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_register_settings(): void { 
        // Register Pages Settings
        register_setting(
            'attrua_pages_group',
            'attrua_pages_options',
            [
                'type' => 'array',
                'description' => __('Authentication pages settings', 'attributes-user-access'),
                'sanitize_callback' => [$this, 'attrua_sanitize_pages_settings'],
                'default' => [
                    'login' => null
                ]
            ]
        );

        // Register Redirects Settings
        register_setting(
            'attrua_pages_group',
            'attrua_redirect_options',
            [
                'type' => 'array',
                'description' => __('Page redirect settings', 'attributes-user-access'),
                'sanitize_callback' => [$this, 'attrua_sanitize_redirect_settings'],
                'default' => [
                    'login' => false
                ]
            ]
        );

        // Add settings sections
        add_settings_section(
            'attrua_pages',
            __('Authentication Pages', 'attributes-user-access'),
            [$this, 'render_pages_section'],
            'attributes-user-access'
        );

        /**
         * Hook: attrua_register_settings_sections
         * 
         * Fired during settings registration.
         * Use this hook to register additional settings sections.
         *
         * @since 1.0.0
         */
        do_action('attrua_register_settings_sections');
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since  1.0.0
     * @access public
     * @param  string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function attrua_enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== $this->page_hook) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'attrua-admin',
            ATTRUA_URL . 'assets/css/admin.css',
            [],
            ATTRUA_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'attrua-admin',
            ATTRUA_URL . 'assets/js/admin.js',
            ['jquery'],
            ATTRUA_VERSION,
            true
        );

        wp_localize_script(
            'attrua-admin',
            'attruaAdmin',
            array_merge(
                $this->attrua_get_script_data(),
                ['wpLoginUrl' => wp_login_url()]
            )
        );
    }

    /**
     * Get localized script data.
     *
     * @since  1.0.0
     * @access private
     * @return array
     */
    private function attrua_get_script_data(): array {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('attrua_admin'),
            'pageTypes' => [
                'login' => [
                    'title' => __('Login', 'attributes-user-access'),
                    'slug' => 'login'
                ]
            ],
            'i18n' => [
                'editPage' => __('Edit Page', 'attributes-user-access'),
                'viewPage' => __('View Page', 'attributes-user-access'),
                'delete' => __('Delete', 'attributes-user-access'),
                'createPage' => __('Create Page', 'attributes-user-access'),
                'creatingPage' => __('Creating...', 'attributes-user-access'),
                'deletingPage' => __('Deleting...', 'attributes-user-access'),
                'confirmDelete' => __('Are you sure you want to delete this page?', 'attributes-user-access'),
                'pageCreated' => __('Page created successfully.', 'attributes-user-access'),
                'pageDeleted' => __('Page deleted successfully.', 'attributes-user-access'),
                'settingsSaved' => __('Settings saved.', 'attributes-user-access'),
                'error' => __('An error occurred.', 'attributes-user-access'),
                'redirectToggle' => __('Redirect WordPress page to this page', 'attributes-user-access')
            ]
        ];
    }

    /**
     * Validates settings based on active tab context.
     *
     * @param array $input Raw settings input
     * @return array Sanitized settings
     */
    public function attrua_validate_settings(array $input): array {
        $active_tab = 'pages'; // Default
        if (isset($_POST['tab'])) {
            // Verify nonce before processing POST data
            check_admin_referer('attrua_settings_action');
            $active_tab = sanitize_key(wp_unslash($_POST['tab']));
        }
        $validator_method = "validate_{$active_tab}_settings";

        if (method_exists($this, $validator_method)) {
            return $this->$validator_method($input);
        }

        return $input;
    }

    /**
     * Render the settings page content.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once ATTRUA_PATH . 'display/admin/settings-page.php';
    }

    /**
     * Render shortcode management cell
     *
     * @param int|null $page_id Page ID if exists
     */
    private function attrua_render_shortcode_cell(?int $page_id): void {
        if (!$page_id) {
            return;
        }
        ?>
        <div class="attrua-page-shortcode">
            <span>[attributes_login_form]</span>
            <button type="button" 
                    class="attrua-copy-shortcode" 
                    data-shortcode="[attributes_login_form]">
                <i class="ti ti-copy"></i>
            </button>
        </div>
        <?php
    }

    /**
     * Render page action buttons
     *
     * @param int|null $page_id Page ID if exists
     */
    private function attrua_render_page_actions(?int $page_id): void {
        if ($page_id) {
            ?>
            <div class="attrua-page-actions">
                <a href="<?php echo esc_url(get_edit_post_link($page_id)); ?>" class="button">
                    <i class="ti ti-pencil"></i>&nbsp;<?php esc_html_e('Edit Page', 'attributes-user-access'); ?>
                </a>
                <a href="<?php echo esc_url(get_permalink($page_id)); ?>" class="button" target="_blank">
                    <i class="ti ti-eye"></i>&nbsp;<?php esc_html_e('View Page', 'attributes-user-access'); ?>
                </a>
                <button type="button" 
                        class="button attrua-delete-page"
                        data-page-id="<?php echo esc_attr($page_id); ?>"
                        data-page-type="login">
                    <i class="ti ti-trash"></i>&nbsp;<?php esc_html_e('Delete', 'attributes-user-access'); ?>
                </button>
            </div>
            <?php
        } else {
            ?>
            <button type="button" 
                    class="button attrua-create-page"
                    data-page-type="login"
                    data-default-title="<?php echo esc_attr__('Login Page', 'attributes-user-access'); ?>"
                    data-default-slug="<?php echo esc_attr__('login', 'attributes-user-access'); ?>">
                <?php esc_html_e('Create Page', 'attributes-user-access'); ?>
            </button>
            <?php
        }
    }

    /**
     * Render redirect toggle switch
     *
     * @param int|null $page_id Page ID if exists
     */
    private function attrua_render_redirect_toggle(?int $page_id): void {
        if (!$page_id) {
            return;
        }
        ?>
        <label class="attrua-redirect-toggle">
            <input type="checkbox" 
                name="attrua_redirect_options[login]" 
                value="1"
                <?php checked($this->settings->attrua_get('redirects.login')); ?>
                data-wp-url="<?php echo esc_url(wp_login_url()); ?>"
                data-custom-url="<?php echo esc_url(get_permalink($page_id)); ?>">
            <span class="slider"></span>
            <span class="attrua-redirect-url">
                <small><?php echo esc_url($this->settings->attrua_get('redirects.login') ? get_permalink($page_id) : wp_login_url()); ?></small>
            </span>
        </label>
        <?php
    }

    /**
     * Render login page configuration row
     * 
     * Generates the table row for login page settings including:
     * - Page title and slug inputs/display
     * - Shortcode management
     * - Page actions (create/edit/delete)
     * - Redirect toggle
     *
     * @since 1.0.0
     * @access private
     * @return void
     */
    private function attrua_render_login_page_row(): void {
        $login_page_id = $this->settings->attrua_get('pages.login');
        
        // Start row with the required class
        echo '<tr class="attrua-page-row" data-page-type="login">';
        
        // Title column
        echo '<th scope="row">';
        if ($login_page_id) {
            $page = get_post($login_page_id);
            echo '<strong class="attrua-page-title-display">' . esc_html($page->post_title) . '</strong>';
        } else {
            ?>
            <input type="text" 
                class="attrua-page-title" 
                name="attrua_pages_options[login_title]" 
                value="<?php esc_attr_e('Login Page', 'attributes-user-access'); ?>" 
                placeholder="<?php esc_attr_e('Login Page', 'attributes-user-access'); ?>" />
            <?php
        }
        echo '</th>';
        
        // Slug column
        echo '<td>';
        if ($login_page_id) {
            $slug = get_post_field('post_name', $login_page_id);
            echo '<strong class="attrua-page-slug-display"><span class="attrua-page-prefix">/</span>' . esc_html($slug) . '</strong>';
        } else {
            ?>
            <input type="text" 
                class="attrua-page-slug" 
                name="attrua_pages_options[login_slug]"
                value="<?php esc_attr_e('login', 'attributes-user-access'); ?>"
                placeholder="<?php esc_attr_e('login', 'attributes-user-access'); ?>" />
            <?php
        }
        echo '</td>';
        
        // Shortcode column
        echo '<td style="width: 200px;">';
        echo '<div class="attrua-page-shortcode">';
        $this->attrua_render_shortcode_cell($login_page_id);
        echo '</div>';
        echo '</td>';
        
        // Actions column
        echo '<td style="width: 300px;">';
        echo '<div class="attrua-page-control">';
        $this->attrua_render_page_actions($login_page_id);
        echo '</div>';
        echo '</td>';
        
        // Redirect column
        echo '<td>';
        echo '<div class="attrua-redirect-toggle">';
        $this->attrua_render_redirect_toggle($login_page_id);
        echo '</div>';
        echo '</td>';

        echo '</tr>';
    }

    /**
     * Handle AJAX page creation request.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_handle_create_page(): void {
        check_ajax_referer('attrua_admin');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'attributes-user-access')]);
        }

        $page_type = isset($_POST['page_type']) ? sanitize_text_field(wp_unslash($_POST['page_type'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';

        if (empty($page_type) || empty($title)) {
            wp_send_json_error(['message' => __('Missing required fields.', 'attributes-user-access')]);
        }

        // If no custom slug provided, WordPress will auto-generate one from the title
        $page_data = [
            'post_title' => $title,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => $this->attrua_get_page_shortcode($page_type)
        ];

        // Only add slug if one was provided
        if (!empty($slug)) {
            $page_data['post_name'] = $slug;
        }

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error(['message' => $page_id->attrua_get_error_message()]);
        }

        // Get current pages options
        $current_pages = get_option('attrua_pages_options', []);
        
        // If empty, initialize with default structure
        if (empty($current_pages)) {
            $current_pages = [
                'login' => null
            ];
        }

        // Update the specific page type
        $current_pages[$page_type] = $page_id;

        // Save the updated pages options
        $update_result = update_option('attrua_pages_options', $current_pages);

        // Return debug information along with success response
        wp_send_json_success([
            'page_id' => $page_id,
            'page_type' => $page_type,
            'edit_url' => get_edit_post_link($page_id, 'raw'),
            'view_url' => get_permalink($page_id),
            'debug' => [
                'current_pages' => $current_pages,
                'update_result' => $update_result,
                'option_after' => get_option('attrua_pages_options')
            ]
        ]);
    }

    /**
     * Handle AJAX page deletion request.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_handle_delete_page(): void {
        check_ajax_referer('attrua_admin');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'attributes-user-access')]);
        }

        $page_id = intval($_POST['page_id'] ?? 0);
        $page_type = isset($_POST['page_type']) ? sanitize_text_field(wp_unslash($_POST['page_type'])) : '';

        if (!$page_id || !$page_type) {
            wp_send_json_error(['message' => __('Invalid request.', 'attributes-user-access')]);
        }

        $result = wp_delete_post($page_id, true);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete page.', 'attributes-user-access')]);
        }

        // Update pages setting using the new settings manager
        $this->settings->attrua_update("pages.$page_type", null);
        $this->settings->attrua_update("redirects.$page_type", 0);

        wp_send_json_success(['message' => __('Page deleted successfully.', 'attributes-user-access')]);
    }

    /**
     * Handle AJAX redirect toggle request.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function attrua_handle_toggle_redirect(): void {
        check_ajax_referer('attrua_admin');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'attributes-user-access')]);
            exit;
        }
    
        $page_type = isset($_POST['page_type']) ? sanitize_text_field(wp_unslash($_POST['page_type'])) : '';
        $enabled = isset($_POST['enabled']) ? filter_var(wp_unslash($_POST['enabled']), FILTER_VALIDATE_BOOLEAN) : false;
    
        if (!$page_type) {
            wp_send_json_error(['message' => __('Invalid request.', 'attributes-user-access')]);
            exit;
        }
    
        // Update redirect setting using the new settings manager
        $this->settings->attrua_update("redirects.$page_type", $enabled);
    
        wp_send_json_success(['message' => __('Setting updated successfully.', 'attributes-user-access')]);
        exit;
    }

    /**
     * Get shortcode for page type.
     *
     * @since  1.0.0
     * @access private
     * @param  string $type Page type identifier.
     * @return string
     */
    private function attrua_get_page_shortcode(string $type): string {
        $shortcodes = [
            'login' => '[attributes_login_form]'
        ];

        return $shortcodes[$type] ?? '';
    }

    /**
     * Add post state to Attributes pages
     * 
     * @param array   $post_states Array of post states
     * @param WP_Post $post        Post object
     * @return array Modified post states
     */
    public function attrua_add_post_state($post_states, $post) {
        // Get all Attributes pages
        $pages = get_option('attrua_pages_options', []);
        
        // Check if current post is an Attributes page
        if (in_array($post->ID, $pages)) {
            $post_states['attrua_page'] = 'Attributes';
        }
        
        return $post_states;
    }

    /**
     * Sanitize pages settings
     *
     * @param array $input The input array to sanitize
     * @return array Sanitized input
     */
    public function attrua_sanitize_pages_settings($input): array {
        // Get existing values
        $existing_values = get_option('attrua_pages_options', []);

        // If input is null or empty, preserve existing values
        if (empty($input)) {
            return $existing_values;
        }

        $sanitized = [];
        foreach (['login'] as $key) {
            // Preserve existing value if not in input
            $sanitized[$key] = isset($input[$key]) ? absint($input[$key]) : ($existing_values[$key] ?? null);
        }
        return $sanitized;
    }

    /**
     * Sanitize redirect settings
     *
     * @param array $input The input array to sanitize
     * @return array Sanitized input
     */
    public function attrua_sanitize_redirect_settings($input): array {
        // Get existing values
        $existing_values = get_option('attrua_redirect_options', []);

        // If input is null or empty, preserve existing values
        if (empty($input)) {
            return $existing_values;
        }

        $sanitized = [];
        foreach (['login'] as $key) {
            // Preserve existing value if not in input
            $sanitized[$key] = isset($input[$key]) ? (bool) $input[$key] : ($existing_values[$key] ?? false);
        }

        return $sanitized;
    }
}