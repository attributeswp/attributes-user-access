<?php
namespace Attributes\Core;

/**
 * Enhanced Settings Management Class
 *
 * Handles centralized settings storage and retrieval with support
 * for extensibility and caching.
 *
 * @package Attributes\Core
 * @since 1.0.0
 */
class Settings {
    /**
     * Settings schema version
     * 
     * Increment when changing settings structure
     */
    private const SCHEMA_VERSION = '1.0.0';

    /**
     * Option keys for different setting groups
     *
     * @var array
     */
    private const OPTION_KEYS = [
        'pages' => 'attrua_pages_options',
        'redirects' => 'attrua_redirect_options'
    ];

    /**
     * Cached options
     *
     * @var array
     */
    private array $options = [];

    /**
     * Default plugin settings
     *
     * @var array
     */
    private array $defaults = [
        'pages' => [
            'login' => null
        ],
        'redirects' => [
            'login' => false,
            'login_default' => ''
        ]
    ];

    /**
     * Initialize settings
     *
     * @return void
     */
    public function init(): void {
        $this->attrua_load_options();
        $this->attrua_maybe_upgrade_schema();

        // Allow extensions to register settings
        do_action('attrua_register_settings', $this);
    }

    /**
     * Get setting value using dot notation
     *
     * @param string $key Setting key (e.g., 'pages.login')
     * @param mixed $default Default value if not set
     * @return mixed Setting value
     */
    public function attrua_get(string $key, $default = null) {
        $segments = explode('.', $key);
        
        if (count($segments) !== 2) {
            return $default;
        }

        [$group, $setting] = $segments;
        
        if (!isset(self::OPTION_KEYS[$group])) {
            return $default;
        }

        if (!isset($this->options[$group])) {
            $this->attrua_load_group_options($group);
        }

        $value = $this->options[$group][$setting] ?? $default;

        // Type casting for known settings
        if ($group === 'redirects' && $setting !== 'login_default') {
            return (bool)$value;
        }

        /**
         * Filter setting value
         *
         * @param mixed $value Setting value
         * @param string $key Full setting key
         * @param mixed $default Default value
         */
        return apply_filters('attrua_get_setting', $value, $key, $default);
    }

    /**
     * Update setting value
     *
     * @param string $key Setting key using dot notation
     * @param mixed $value Setting value
     * @return bool Success
     */
    public function attrua_update(string $key, $value): bool {
        $segments = explode('.', $key);
        
        if (count($segments) !== 2) {
            return false;
        }

        [$group, $setting] = $segments;
        
        if (!isset(self::OPTION_KEYS[$group])) {
            return false;
        }

        // Get current options
        $options = get_option(self::OPTION_KEYS[$group], []);
        
        // Initialize with defaults if empty
        if (empty($options)) {
            $options = $this->defaults[$group];
        }

        // Sanitize and update value
        $options[$setting] = $this->attrua_sanitize_setting($group, $setting, $value);
        
        /**
         * Filter setting before save
         *
         * @param mixed $value Setting value
         * @param string $key Full setting key
         */
        $options[$setting] = apply_filters('attrua_pre_update_setting', $options[$setting], $key);

        // Save the updated options
        $result = update_option(self::OPTION_KEYS[$group], $options);

        // Update cache if successful
        if ($result) {
            $this->options[$group] = $options;

            /**
             * Action after setting update
             *
             * @param string $key Setting key
             * @param mixed $value New value
             */
            do_action('attrua_after_update_setting', $key, $value);
        }

        return $result;
    }

    /**
     * Get all settings
     *
     * @return array All settings
     */
    public function attrua_get_all(): array {
        $all_settings = [];
        
        foreach (self::OPTION_KEYS as $group => $option_key) {
            if (!isset($this->options[$group])) {
                $this->attrua_load_group_options($group);
            }
            $all_settings[$group] = $this->options[$group];
        }

        /**
         * Filter all settings
         *
         * @param array $all_settings All settings
         */
        return apply_filters('attrua_all_settings', $all_settings);
    }

    /**
     * Initialize default options
     *
     * @return void
     */
    public function attrua_init_options(): void {
        foreach (self::OPTION_KEYS as $group => $option_key) {
            if (!get_option($option_key)) {
                update_option($option_key, $this->defaults[$group]);
            }
        }

        // Store schema version
        update_option('attrua_schema_version', self::SCHEMA_VERSION);
    }

    /**
     * Load settings from database
     *
     * @return void
     */
    private function attrua_load_options(): void {
        foreach (self::OPTION_KEYS as $group => $option_key) {
            $this->attrua_load_group_options($group);
        }
    }

    /**
     * Load options for a specific group
     *
     * @param string $group Group identifier
     * @return void
     */
    private function attrua_load_group_options(string $group): void {
        if (!isset(self::OPTION_KEYS[$group])) {
            return;
        }

        $option_key = self::OPTION_KEYS[$group];
        $options = get_option($option_key, []);

        // Ensure defaults
        if (empty($options)) {
            $options = $this->defaults[$group];
            update_option($option_key, $options);
        }

        $this->options[$group] = wp_parse_args($options, $this->defaults[$group]);
    }

    /**
     * Check and upgrade settings schema if needed
     *
     * @return void
     */
    private function attrua_maybe_upgrade_schema(): void {
        $current_schema = get_option('attrua_schema_version', '0.0.0');
        
        if (version_compare($current_schema, self::SCHEMA_VERSION, '<')) {
            $this->attrua_upgrade_schema($current_schema);
            update_option('attrua_schema_version', self::SCHEMA_VERSION);
        }
    }

    /**
     * Upgrade settings schema
     *
     * @param string $from_version Current schema version
     * @return void
     */
    private function attrua_upgrade_schema(string $from_version): void {
        // Version-specific upgrades
        if (version_compare($from_version, '1.0.0', '<')) {
            $this->attrua_upgrade_to_1_0_0();
        }

        /**
         * Action after schema upgrade
         *
         * @param string $from_version Previous version
         * @param string $to_version New version
         */
        do_action('attrua_after_schema_upgrade', $from_version, self::SCHEMA_VERSION);
    }

    /**
     * Upgrade to version 1.0.0 schema
     *
     * @return void
     */
    private function attrua_upgrade_to_1_0_0(): void {
        // Migrate from legacy single option to group structure
        $old_options = get_option('attrua_options', []);
        
        if (!empty($old_options)) {
            // Extract and migrate login page settings
            if (isset($old_options['pages']['login'])) {
                $this->attrua_update('pages.login', $old_options['pages']['login']);
            }
            
            // Extract and migrate redirect settings
            if (isset($old_options['redirects']['login'])) {
                $this->attrua_update('redirects.login', $old_options['redirects']['login']);
            }
            
            // Clean up old options
            delete_option('attrua_options');
        }
    }

    /**
     * Sanitize setting value
     *
     * @param string $group Setting group
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return mixed Sanitized value
     */
    private function attrua_sanitize_setting(string $group, string $key, $value) {
        switch ($group) {
            case 'pages':
                return absint($value);
            
            case 'redirects':
                if ($key === 'login_default') {
                    return esc_url_raw($value);
                }
                return (bool)$value;

            default:
                /**
                 * Filter custom setting sanitization
                 *
                 * @param mixed $value Raw value
                 * @param string $group Setting group
                 * @param string $key Setting key
                 */
                return apply_filters('attrua_sanitize_setting', 
                    sanitize_text_field($value),
                    $group,
                    $key
                );
        }
    }
}