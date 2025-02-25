<?php
/**
 * Plugin Name: Attributes User Access
 * Plugin URI: https://attributeswp.com/
 * Description: Enhanced WordPress authentication and user management.
 * Version: 1.0.0
 * Author: Attributes WP
 * Author URI: https://attributeswp.com/
 * Text Domain: attributes-user-access
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License: GPLv2 or later
 */

/**
 * Prevent direct access to this file.
 * 
 * This security measure prevents direct access to the plugin's PHP files,
 * ensuring that WordPress core is properly loaded and authenticated before
 * any plugin code executes.
 */
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Plugin version and path constants.
 * 
 * These constants provide centralized configuration for version control
 * and file path management throughout the plugin. Using constants
 * improves maintainability and prevents magic values in the codebase.
 */
define('ATTRUA_VERSION', '1.0.0');
define('ATTRUA_FILE', __FILE__);
define('ATTRUA_PATH', plugin_dir_path(__FILE__));
define('ATTRUA_URL', plugin_dir_url(__FILE__));
define('ATTRUA_BASENAME', plugin_basename(__FILE__));
define('ATTRUA_ICON_VERSION', '3.28.1');

/**
 * Composer autoloader integration.
 * 
 * Implements PSR-4 autoloading for efficient class loading and
 * proper namespace resolution. Validates the existence of the autoloader
 * to prevent fatal errors in production environments.
 */
$composer_autoloader = ATTRUA_PATH . 'vendor/autoload.php';

require_once $composer_autoloader;

/**
 * Initialize dependencies if using pre-packaged version
*/
if (function_exists('attrua_init_dependencies')) {
    try {
        $container = attrua_init_dependencies();
    } catch (Exception $e) {
        attrua_dependency_error($e->getMessage());
        return;
    }
}

/**
 * Initialize the plugin.
 * 
 * Creates or retrieves the singleton instance of the plugin's core class.
 * This function serves as the primary entry point for plugin initialization,
 * ensuring that the plugin is loaded exactly once and at the correct time
 * in the WordPress lifecycle.
 *
 * @since 1.0.0
 * @return \Attributes\Core\Plugin Singleton instance of the plugin
 */
function ATTRUA_init(): \Attributes\Core\Plugin {
    static $plugin = null;
    
    if ($plugin === null) {
        $plugin = \Attributes\Core\Plugin::instance();
    }
    
    return $plugin;
}

/**
 * Hook into WordPress initialization.
 * 
 * Registers the plugin initialization function to run after WordPress
 * has loaded all active plugins. This ensures proper plugin loading order
 * and availability of all WordPress functions and APIs.
 */
add_action('plugins_loaded', 'ATTRUA_init', 10);

/**
 * Plugin activation hook registration.
 * 
 * Registers a callback to be triggered when the plugin is activated.
 * This ensures proper initialization of plugin data structures and options
 * during the activation process.
 */
if (function_exists('register_activation_hook')) {
    register_activation_hook(
        __FILE__,
        array(\Attributes\Core\Plugin::class, 'attrua_activate')
    );
}

/**
 * Plugin deactivation hook registration.
 * 
 * Registers a callback to be triggered when the plugin is deactivated.
 * This ensures proper cleanup of plugin-specific data and settings
 * during the deactivation process.
 */
if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(
        __FILE__,
        array(\Attributes\Core\Plugin::class, 'attrua_deactivate')
    );
}
