<?php
namespace Attributes\Core;

/**
 * Assets Management Class
 *
 * Handles registration and enqueuing of scripts and styles
 * with performance optimization and extension support.
 *
 * @package Attributes\Core
 * @since 1.0.0
 */
class Assets {
    /**
     * Settings instance
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Script and style handles
     *
     * @var array
     */
    private array $handles = [
        'styles' => [],
        'scripts' => []
    ];

    /**
     * External dependencies configuration
     *
     * @var array
     */
    private const EXTERNAL_DEPS = [
        'tabler-icons' => [
            'url' => 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css',
            'version' => ATTRUA_ICON_VERSION,
            'media' => 'all'
        ]
    ];

    /**
     * Constructor
     *
     * @param Settings $settings Settings instance
     */
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * Initialize assets management
     *
     * @return void
     */
    public function init(): void {
        // Register asset hooks
        add_action('wp_enqueue_scripts', [$this, 'attrua_register_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'attrua_enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'attrua_register_admin_assets']);
        add_action('admin_enqueue_scripts', [$this, 'attrua_enqueue_admin_assets']);

        // Allow extensions to register assets
        do_action('attrua_register_assets', $this);
    }

    /**
     * Register frontend assets
     *
     * @return void
     */
    public function attrua_register_frontend_assets(): void {
        // Register external dependencies
        $this->attrua_register_external_deps();

        // Register plugin styles
        $this->attrua_register_style(
            'attrua-front',
            'css/min/front.min.css',
            ['tabler-icons']
        );

        // Register login validation script
        $this->attrua_register_script(
            'attrua-validation',
            'js/min/validation.min.js',
            ['jquery']
        );

        // Localize script data
        wp_localize_script('attrua-validation', 'attruaFront', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('attrua_frontend'),
            'i18n' => [
                'invalidCredentials' => __('Invalid username or password.', 'attributes-user-access'),
                'loggingIn' => __('Logging in...', 'attributes-user-access')
            ]
        ]);

        /**
         * Action: attrua_after_register_frontend_assets
         * 
         * Allow extensions to register additional frontend assets
         */
        do_action('attrua_after_register_frontend_assets', $this);
    }

    /**
     * Register admin assets
     *
     * @return void
     */
    public function attrua_register_admin_assets(): void {
        // Register external dependencies
        $this->attrua_register_external_deps();

        // Register admin styles
        $this->attrua_register_style(
            'attrua-admin',
            'css/min/admin.min.css',
            ['tabler-icons']
        );

        // Register admin script
        $this->attrua_register_script(
            'attrua-admin',
            'js/min/admin.min.js',
            ['jquery']
        );

        /**
         * Action: attrua_after_register_admin_assets
         * 
         * Allow extensions to register additional admin assets
         */
        do_action('attrua_after_register_admin_assets', $this);
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function attrua_enqueue_frontend_assets(): void {
        // Only enqueue on relevant pages
        if ($this->attrua_should_load_frontend_assets()) {
            wp_enqueue_style('attrua-front');
            wp_enqueue_script('attrua-validation');

            /**
             * Action: attrua_enqueue_frontend_assets
             * 
             * Allow extensions to enqueue additional frontend assets
             */
            do_action('attrua_enqueue_frontend_assets');
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page
     * @return void
     */
    public function attrua_enqueue_admin_assets(string $hook_suffix): void {
        // Only enqueue on plugin admin pages
        if ($this->attrua_is_plugin_admin_page($hook_suffix)) {
            wp_enqueue_style('attrua-admin');
            wp_enqueue_script('attrua-admin');

            // Localize admin script
            wp_localize_script('attrua-admin', 'attruaAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('attrua_admin'),
                'wpLoginUrl' => wp_login_url(),
                'i18n' => $this->attrua_get_admin_i18n()
            ]);

            /**
             * Action: attrua_enqueue_admin_assets
             * 
             * Allow extensions to enqueue additional admin assets
             * 
             * @param string $hook_suffix Current admin page hook
             */
            do_action('attrua_enqueue_admin_assets', $hook_suffix);
        }
    }

    /**
     * Register external dependencies
     *
     * @return void
     */
    private function attrua_register_external_deps(): void {
        foreach (self::EXTERNAL_DEPS as $handle => $config) {
            wp_register_style(
                $handle,
                sprintf($config['url'], $config['version']),
                [],
                $config['version'],
                $config['media']
            );
        }
    }

    /**
     * Register a style
     *
     * @param string $handle Style handle
     * @param string $path Path relative to assets directory
     * @param array $deps Dependencies
     * @param string|null $version Version string
     * @param string $media Media type
     * @return void
     */
    public function attrua_register_style(
        string $handle,
        string $path,
        array $deps = [],
        ?string $version = null,
        string $media = 'all'
    ): void {
        $this->handles['styles'][] = $handle;

        wp_register_style(
            $handle,
            ATTRUA_URL . 'assets/' . $path,
            $deps,
            $version ?? ATTRUA_VERSION,
            $media
        );
    }

    /**
     * Register a script
     *
     * @param string $handle Script handle
     * @param string $path Path relative to assets directory
     * @param array $deps Dependencies
     * @param string|null $version Version string
     * @param bool $in_footer Whether to enqueue in footer
     * @return void
     */
    public function attrua_register_script(
        string $handle,
        string $path,
        array $deps = [],
        ?string $version = null,
        bool $in_footer = true
    ): void {
        $this->handles['scripts'][] = $handle;

        wp_register_script(
            $handle,
            ATTRUA_URL . 'assets/' . $path,
            $deps,
            $version ?? ATTRUA_VERSION,
            $in_footer
        );
    }

    /**
     * Check if frontend assets should be loaded
     *
     * @return bool
     */
    private function attrua_should_load_frontend_assets(): bool {
        global $post;

        // Check if current post/page has login shortcode
        if ($post && has_shortcode($post->post_content, 'attributes_login_form')) {
            return true;
        }

        // Check if current page is plugin page
        $login_page_id = $this->settings->attrua_get('pages.login');
        if ($login_page_id && is_page($login_page_id)) {
            return true;
        }

        /**
         * Filter: attrua_load_frontend_assets
         * 
         * Allow extensions to determine if assets should be loaded
         * 
         * @param bool $load Whether to load assets
         */
        return apply_filters('attrua_load_frontend_assets', false);
    }

    /**
     * Check if current admin page is plugin page
     *
     * @param string $hook_suffix Current admin page hook
     * @return bool
     */
    private function attrua_is_plugin_admin_page(string $hook_suffix): bool {
        return strpos($hook_suffix, 'attributes-user-access') !== false;
    }

    /**
     * Get admin script translations
     *
     * @return array
     */
    private function attrua_get_admin_i18n(): array {
        return [
            'createPage' => __('Create Page', 'attributes-user-access'),
            'editPage' => __('Edit Page', 'attributes-user-access'),
            'viewPage' => __('View Page', 'attributes-user-access'),
            'delete' => __('Delete', 'attributes-user-access'),
            'creatingPage' => __('Creating...', 'attributes-user-access'),
            'deletingPage' => __('Deleting...', 'attributes-user-access'),
            'saving' => __('Saving...', 'attributes-user-access'),
            'saveChanges' => __('Save Changes', 'attributes-user-access'),
            'retry' => __('Retry', 'attributes-user-access'),
            'dismiss' => __('Dismiss this notice', 'attributes-user-access'),
            'confirmDelete' => __('Are you sure you want to delete this page?', 'attributes-user-access'),
            'pageCreated' => __('Page created successfully.', 'attributes-user-access'),
            'pageDeleted' => __('Page deleted successfully.', 'attributes-user-access'),
            'settingsSaved' => __('Settings saved.', 'attributes-user-access'),
            'error' => __('An error occurred.', 'attributes-user-access'),
            'invalidData' => __('Invalid data provided.', 'attributes-user-access')
        ];
    }

    /**
     * Get registered asset handles
     *
     * @param string $type Asset type (styles|scripts)
     * @return array Asset handles
     */
    public function attrua_get_handles(string $type = 'all'): array {
        if ($type === 'all') {
            return $this->handles;
        }
        return $this->handles[$type] ?? [];
    }
}