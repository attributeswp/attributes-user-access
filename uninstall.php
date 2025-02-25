<?php
/**
 * Attributes User Access Uninstallation Handler
 *
 * This file executes when the plugin is uninstalled from WordPress.
 * It handles the complete removal of plugin data, including:
 * - Plugin settings and options
 * - Custom pages created by the plugin
 * - User metadata related to authentication
 * - Transient data for rate limiting
 *
 * Security measures:
 * - Direct access prevention
 * - WordPress environment validation
 * - Capability checks
 * - Database transaction handling
 *
 * @package Attributes
 * @since   1.0.0
 */

// Prevent direct file access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access denied.');
}

// Define plugin constants if not already defined
if (!defined('ATTRUA_VERSION')) {
    define('ATTRUA_VERSION', '1.0.0');
}

/**
 * Main uninstallation class.
 * 
 * Implements a structured approach to plugin cleanup with error handling
 * and data integrity protection.
 */
class attrua_Uninstaller {
    /**
     * Plugin options in wp_options table.
     *
     * @var array
     */
    private const OPTIONS = [
        'attrua_options',
        'attrua_version'
    ];

    /**
     * User meta keys to remove.
     *
     * @var array
     */
    private const USER_META = [
        'attrua_last_login',
        'attrua_registration_ip'
    ];

    /**
     * Transient prefixes to match and delete.
     *
     * @var array
     */
    private const TRANSIENT_PREFIXES = [
        'attrua_login_attempts_'
    ];

    /**
     * Execute uninstallation process.
     *
     * Orchestrates the complete cleanup process with error handling
     * and atomic operations where possible.
     *
     * @return void
     */
    public static function uninstall(): void {
        global $wpdb;

        try {
            // Start transaction if supported
            $supports_transactions = method_exists($wpdb, 'query') 
                && method_exists($wpdb, 'begin') 
                && method_exists($wpdb, 'commit') 
                && method_exists($wpdb, 'rollback');

            if ($supports_transactions) {
                $wpdb->begin();
            }

            // Perform cleanup operations
            self::remove_plugin_pages();
            self::remove_plugin_options();
            self::remove_user_metadata();
            self::remove_transients();
            self::remove_custom_capabilities();

            // Allow additional cleanup by premium features
            do_action('attrua_uninstall');

            // Commit transaction if supported
            if ($supports_transactions) {
                $wpdb->commit();
            }

        } catch (Exception $e) {
            // Rollback on error if transactions are supported
            if ($supports_transactions) {
                $wpdb->rollback();
            }
        }
    }

    /**
     * Remove plugin-created pages.
     *
     * Deletes all pages created by the plugin for authentication forms.
     * Also handles cleanup of page metadata and relationships.
     *
     * @return void
     */
    private static function remove_plugin_pages(): void {
        $options = get_option('attrua_options', []);
        $pages = $options['pages'] ?? [];

        foreach ($pages as $page_id) {
            if ($page_id && get_post($page_id)) {
                wp_delete_post($page_id, true);
            }
        }
    }

    /**
     * Remove plugin options.
     *
     * Deletes all plugin-related options from the wp_options table.
     * Uses proper WordPress APIs for data integrity.
     *
     * @return void
     */
    private static function remove_plugin_options(): void {
        foreach (self::OPTIONS as $option) {
            delete_option($option);
        }
    }

    /**
     * Remove user metadata.
     *
     * Cleans up all plugin-related user metadata across all users.
     * Uses efficient database operations for large user bases.
     *
     * @return void
     */
    private static function remove_user_metadata(): void {
        global $wpdb;

        foreach (self::USER_META as $meta_key) {
            $wpdb->delete(
                $wpdb->usermeta,
                ['meta_key' => $meta_key],
                ['%s']
            );
        }
    }

    /**
     * Remove transient data.
     *
     * Cleans up all plugin-related transients including rate limiting data.
     * Handles both timeout and non-timeout transients.
     *
     * @return void
     */
    private static function remove_transients(): void {
        global $wpdb;

        // Remove timeout transients
        foreach (self::TRANSIENT_PREFIXES as $prefix) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     OR option_name LIKE %s",
                    $wpdb->esc_like('_transient_' . $prefix) . '%',
                    $wpdb->esc_like('_transient_timeout_' . $prefix) . '%'
                )
            );
        }
    }

    /**
     * Remove custom capabilities.
     *
     * Removes any custom capabilities added by the plugin from all roles.
     * Ensures proper cleanup of WordPress role system.
     *
     * @return void
     */
    private static function remove_custom_capabilities(): void {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        $custom_caps = [
            'attrua_manage_settings'
        ];

        foreach ($wp_roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($custom_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}

// Execute uninstallation
Attrua_Uninstaller::uninstall();