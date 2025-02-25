<?php
namespace Attributes\Core;

/**
 * Extension Manager Class
 *
 * Handles discovery, registration, and loading of plugin extensions.
 *
 * @package Attributes\Core
 * @since 1.0.0
 */
class Extension_Manager {
    /**
     * Registered extensions
     *
     * @var array
     */
    private array $extensions = [];

    /**
     * Parent plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin Parent plugin instance
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize extension manager
     *
     * @return void
     */
    public function init(): void {
        // Register hooks for extensions to integrate
        add_action('plugins_loaded', [$this, 'attrua_discover_extensions'], 5);
    }

    /**
     * Discover available extensions
     *
     * @return void
     */
    public function attrua_discover_extensions(): void {
        /**
         * Action: attrua_register_extensions
         * 
         * Allow extensions to register themselves
         *
         * @param Extension_Manager $this Extension manager instance
         */
        do_action('attrua_register_extensions', $this);
    }

    /**
     * Register an extension
     *
     * @param string $id Extension identifier

     * @param array  $config Extension configuration

     * @return void
     */
    public function attrua_register(string $id, array $config): void {
        // Validate required config fields
        if (!isset($config['name'], $config['version'])) {
            return;
        }

        $this->extensions[$id] = wp_parse_args($config, [
            'name' => '',
            'version' => '',
            'description' => '',
            'author' => '',
            'url' => '',
            'requires' => [
                'php' => '7.4',
                'wp' => '5.8',
                'core' => '1.0.0'
            ]
        ]);
    }

    /**
     * Get registered extension
     *
     * @param string $id Extension identifier
     * @return array|null Extension config or null if not found
     */
    public function attrua_get(string $id): ?array {
        return $this->extensions[$id] ?? null;
    }

    /**
     * Get all registered extensions
     *
     * @return array Registered extensions
     */
    public function attrua_get_all(): array {
        return $this->extensions;
    }

    /**
     * Check if extension is registered
     *
     * @param string $id Extension identifier
     * @return bool Whether extension is registered
     */
    public function attrua_has(string $id): bool {
        return isset($this->extensions[$id]);
    }

    /**
     * Get count of registered extensions
     *
     * @return int Extension count
     */
    public function attrua_count(): int {
        return count($this->extensions);
    }

    /**
     * Check if extension requirements are met
     *
     * @param array $config Extension configuration

     * @return bool Whether requirements are met
     */
    public function attrua_meets_requirements(array $config): bool {
        // Check PHP version
        if (!empty($config['requires']['php'])) {
            if (version_compare(PHP_VERSION, $config['requires']['php'], '<')) {
                return false;
            }
        }

        // Check WordPress version
        if (!empty($config['requires']['wp'])) {
            if (version_compare(get_bloginfo('version'), $config['requires']['wp'], '<')) {
                return false;
            }
        }

        // Check core plugin version
        if (!empty($config['requires']['core'])) {
            if (version_compare(ATTRUA_VERSION, $config['requires']['core'], '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get extension status information
     *
     * @param string $id Extension identifier
     * @return array Status information
     */
    public function attrua_get_status(string $id): array {
        $extension = $this->attrua_get($id);

        if (!$extension) {
            return [
                'installed' => false,
                'active' => false,
                'meets_requirements' => false
            ];
        }

        return [
            'installed' => true,
            'active' => true, // Could be extended to check license status
            'meets_requirements' => $this->attrua_meets_requirements($extension)
        ];
    }

    /**
     * Check if any extensions are registered
     *
     * @return bool Whether any extensions are registered
     */
    public function attrua_has_extensions(): bool {
        return !empty($this->extensions);
    }

    /**
     * Remove an extension registration
     *
     * @param string $id Extension identifier
     * @return bool Whether extension was removed
     */
    public function attrua_remove(string $id): bool {
        if ($this->attrua_has($id)) {
            unset($this->extensions[$id]);
            return true;
        }

        return false;
    }

    /**
     * Clear all extension registrations
     *
     * @return void
     */
    public function attrua_clear(): void {
        $this->extensions = [];
    }
}