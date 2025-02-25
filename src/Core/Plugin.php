<?php
namespace Attributes\Core;

use Attributes\Admin\Admin;
use Attributes\Front\Login;

/**
 * Core Plugin Class
 * 
 * Manages core plugin functionality and bootstrapping with extension support.
 *
 * @package Attributes\Core
 * @since 1.0.0
 */
final class Plugin {
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Extension manager instance
     *
     * @var Extension_Manager
     */
    private Extension_Manager $extensions;

    /**
     * Settings instance
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Asset manager instance
     *
     * @var Assets
     */
    private Assets $assets;

    /**
     * Registered components
     *
     * @var array
     */
    private array $components = [];

    /**
     * Get plugin instance
     *
     * @return self Plugin instance
     */
    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize core services
        $this->settings = new Settings();
        $this->extensions = new Extension_Manager($this);
        $this->assets = new Assets($this->settings);
        
        // Initialize components
        $this->attrua_initialize_core();
        $this->attrua_init_hooks();
    }

    /**
     * Initialize core dependencies
     */
    private function attrua_initialize_core(): void {
        // Initialize assets
        // Initialize core services
        $this->settings->init();
        $this->extensions->init();
        $this->assets->init();
        
        /**
         * Action: attrua_init_services
         * 
         * Allows extensions to initialize their services.
         *
         * @param Plugin $plugin Plugin instance
         */
        do_action('attrua_init_services', $this);
    }

    /**
     * Initialize WordPress hooks
     */
    private function attrua_init_hooks(): void {
        // Core WordPress hooks
        add_action('plugins_loaded', [$this, 'attrua_load_textdomain']);
        add_action('init', [$this, 'init']);

        // Feature initialization based on context
        if (is_admin()) {
            $this->attrua_init_admin();
        } else {
            $this->attrua_init_front();
        }

        /**
         * Action: attrua_loaded
         * 
         * Fires after plugin is fully loaded.
         *
         * @param Plugin $plugin Plugin instance
         */
        do_action('attrua_loaded', $this);
    }

    /**
     * Initialize plugin textdomain
     */
    public function attrua_load_textdomain(): void {
        load_plugin_textdomain(
            'attributes-user-access',
            false,
            dirname(plugin_basename(ATTRUA_FILE)) . '/languages'
        );
    }

    /**
     * Initialize plugin core functionality
     */
    public function init(): void {
        /**
         * Action: attrua_before_init
         * 
         * Fires before plugin initialization.
         */
        do_action('attrua_before_init');

        // Handle upgrades
        $this->attrua_maybe_upgrade();

        /**
         * Action: attrua_after_init
         * 
         * Fires after plugin initialization.
         */
        do_action('attrua_after_init');
    }

    /**
     * Initialize admin components
     */
    private function attrua_init_admin(): void {
        $this->components['admin'] = new Admin($this->settings);
        
        /**
         * Action: attrua_admin_init
         * 
         * Fires after admin components are initialized.
         *
         * @param array $components Registered components
         */
        do_action('attrua_admin_init', $this->components);
    }

    /**
     * Initialize frontend components
     */
    private function attrua_init_front(): void {
        $this->components['front.login'] = new Login($this->settings);
        
        /**
         * Action: attrua_front_init
         * 
         * Fires after frontend components are initialized.
         *
         * @param array $components Registered components
         */
        do_action('attrua_front_init', $this->components);
    }

    /**
     * Plugin activation handler
     */
    public static function attrua_activate(): void {
        // Version requirements check
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(ATTRUA_FILE));
            wp_die(
                esc_html__('Attributes requires PHP 7.4 or higher.', 'attributes-user-access'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        // Initialize settings
        $settings = new Settings();
        $settings->attrua_init_options();

        /**
         * Action: attrua_activated
         * 
         * Fires after plugin activation.
         */
        do_action('attrua_activated');

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation handler
     */
    public static function attrua_deactivate(): void {
        /**
         * Action: attrua_deactivated
         * 
         * Fires during plugin deactivation.
         */
        do_action('attrua_deactivated');
        flush_rewrite_rules();
    }

    /**
     * Check and handle plugin upgrades
     */
    private function attrua_maybe_upgrade(): void {
        $current_version = get_option('attrua_version');

        if ($current_version !== ATTRUA_VERSION) {
            /**
             * Action: attrua_upgrade
             * 
             * Fires during version upgrade.
             *
             * @param string $new_version New version
             * @param string $old_version Old version
             */
            do_action('attrua_upgrade', ATTRUA_VERSION, $current_version);
            
            // Update version in database
            update_option('attrua_version', ATTRUA_VERSION);
        }
    }

    /**
     * Get plugin settings
     *
     * @return Settings
     */
    public function attrua_settings(): Settings {
        return $this->settings;
    }

    /**
     * Get extension manager
     *
     * @return Extension_Manager
     */
    public function attrua_extensions(): Extension_Manager {
        return $this->extensions;
    }

    /**
     * Get plugin component
     *
     * @param string $key Component identifier
     * @return mixed|null Component instance or null if not found
     */
    public function attrua_component(string $key) {
        return $this->components[$key] ?? null;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}