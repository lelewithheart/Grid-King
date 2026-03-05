<?php
/**
 * GridKing Plugin System Configuration (Legacy 1.4.0)
 * 
 * Provides a basic plugin loader, event hooks, and plugin management.
 * Plugins are loaded from the /plugins directory.
 */

// Plugin directory path
define('PLUGINS_DIR', __DIR__ . '/../plugins/');

// Plugin events/hooks registry
class PluginHooks {
    private static $hooks = [];
    private static $loadedPlugins = [];
    private static $pluginInfo = [];
    
    /**
     * Register a hook callback
     * @param string $hookName Name of the hook
     * @param callable $callback Callback function
     * @param int $priority Priority (lower = earlier execution)
     */
    public static function register(string $hookName, callable $callback, int $priority = 10): void {
        if (!isset(self::$hooks[$hookName])) {
            self::$hooks[$hookName] = [];
        }
        self::$hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        // Sort by priority
        usort(self::$hooks[$hookName], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }
    
    /**
     * Execute all callbacks for a hook
     * @param string $hookName Name of the hook
     * @param mixed $data Data to pass to callbacks
     * @return mixed Modified data
     */
    public static function execute(string $hookName, $data = null) {
        if (!isset(self::$hooks[$hookName])) {
            return $data;
        }
        foreach (self::$hooks[$hookName] as $hook) {
            $result = call_user_func($hook['callback'], $data);
            if ($result !== null) {
                $data = $result;
            }
        }
        return $data;
    }
    
    /**
     * Check if any callbacks are registered for a hook
     * @param string $hookName Name of the hook
     * @return bool
     */
    public static function hasHook(string $hookName): bool {
        return isset(self::$hooks[$hookName]) && count(self::$hooks[$hookName]) > 0;
    }
    
    /**
     * Get list of registered hooks
     * @return array
     */
    public static function getRegisteredHooks(): array {
        return array_keys(self::$hooks);
    }
    
    /**
     * Register a loaded plugin
     * @param array $info Plugin information
     */
    public static function registerPlugin(array $info): void {
        self::$loadedPlugins[] = $info['id'] ?? 'unknown';
        self::$pluginInfo[$info['id'] ?? 'unknown'] = $info;
    }
    
    /**
     * Get loaded plugins
     * @return array
     */
    public static function getLoadedPlugins(): array {
        return self::$loadedPlugins;
    }
    
    /**
     * Get plugin info
     * @param string $pluginId Plugin identifier
     * @return array|null
     */
    public static function getPluginInfo(string $pluginId): ?array {
        return self::$pluginInfo[$pluginId] ?? null;
    }
    
    /**
     * Get all plugin information
     * @return array
     */
    public static function getAllPluginInfo(): array {
        return self::$pluginInfo;
    }
}

/**
 * Plugin Loader
 * Scans the plugins directory and loads valid plugins
 */
class PluginLoader {
    private static $errors = [];
    
    /**
     * Load all enabled plugins from the plugins directory
     * @param PDO $conn Database connection (optional, for checking enabled status)
     * @return array List of loaded plugin IDs
     */
    public static function loadPlugins(?PDO $conn = null): array {
        $loaded = [];
        
        // Check if plugins are enabled (feature toggle or Lite Mode)
        if ($conn) {
            $stmt = $conn->prepare("SELECT is_enabled FROM feature_toggles WHERE feature_code = 'plugins' LIMIT 1");
            $stmt->execute();
            $toggle = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($toggle && !$toggle['is_enabled']) {
                return $loaded; // Plugins disabled
            }
            
            // Check if Lite Mode is active (disables plugins)
            $stmt = $conn->prepare("SELECT is_enabled FROM feature_toggles WHERE feature_code = 'lite_mode' LIMIT 1");
            $stmt->execute();
            $liteMode = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($liteMode && $liteMode['is_enabled']) {
                return $loaded; // Lite Mode - plugins disabled
            }
        }
        
        if (!is_dir(PLUGINS_DIR)) {
            return $loaded;
        }
        
        $dirs = glob(PLUGINS_DIR . '*', GLOB_ONLYDIR);
        foreach ($dirs as $pluginDir) {
            $pluginId = basename($pluginDir);
            $pluginFile = $pluginDir . '/plugin.php';
            $manifestFile = $pluginDir . '/manifest.json';
            
            // Skip if no plugin.php exists
            if (!file_exists($pluginFile)) {
                continue;
            }
            
            // Read manifest if it exists
            $manifest = [];
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true) ?? [];
            }
            
            // Check if plugin is enabled in database
            if ($conn) {
                $stmt = $conn->prepare("SELECT is_enabled FROM plugins WHERE plugin_id = :id LIMIT 1");
                $stmt->execute([':id' => $pluginId]);
                $pluginRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($pluginRow && !$pluginRow['is_enabled']) {
                    continue; // Plugin is disabled
                }
            }
            
            // Load the plugin
            try {
                require_once $pluginFile;
                
                $info = array_merge([
                    'id' => $pluginId,
                    'name' => $manifest['name'] ?? ucfirst($pluginId),
                    'version' => $manifest['version'] ?? '1.0.0',
                    'author' => $manifest['author'] ?? 'Unknown',
                    'description' => $manifest['description'] ?? '',
                    'requires' => $manifest['requires'] ?? '1.4.0',
                    'path' => $pluginDir
                ], $manifest);
                
                PluginHooks::registerPlugin($info);
                $loaded[] = $pluginId;
                
            } catch (Throwable $e) {
                self::$errors[$pluginId] = $e->getMessage();
            }
        }
        
        // Execute plugin initialization hook
        PluginHooks::execute('plugins_loaded', $loaded);
        
        return $loaded;
    }
    
    /**
     * Get plugin loading errors
     * @return array
     */
    public static function getErrors(): array {
        return self::$errors;
    }
    
    /**
     * Discover available plugins (including disabled ones)
     * @return array
     */
    public static function discoverPlugins(): array {
        $plugins = [];
        
        if (!is_dir(PLUGINS_DIR)) {
            return $plugins;
        }
        
        $dirs = glob(PLUGINS_DIR . '*', GLOB_ONLYDIR);
        foreach ($dirs as $pluginDir) {
            $pluginId = basename($pluginDir);
            $pluginFile = $pluginDir . '/plugin.php';
            $manifestFile = $pluginDir . '/manifest.json';
            
            if (!file_exists($pluginFile)) {
                continue;
            }
            
            $manifest = [];
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true) ?? [];
            }
            
            $plugins[$pluginId] = [
                'id' => $pluginId,
                'name' => $manifest['name'] ?? ucfirst($pluginId),
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? 'Unknown',
                'description' => $manifest['description'] ?? '',
                'requires' => $manifest['requires'] ?? '1.4.0',
                'path' => $pluginDir,
                'has_manifest' => file_exists($manifestFile)
            ];
        }
        
        return $plugins;
    }
}

/**
 * Available Plugin Hooks Reference
 * 
 * Core Hooks:
 * - plugins_loaded: Called after all plugins are loaded
 * - init: Called during application initialization
 * - shutdown: Called before application shutdown
 * 
 * Page Hooks:
 * - header_before: Before header output
 * - header_after: After header output
 * - footer_before: Before footer output
 * - footer_after: After footer output
 * - page_content: Modify page content
 * 
 * Admin Hooks:
 * - admin_dashboard_widgets: Add widgets to admin dashboard
 * - admin_menu_items: Add items to admin menu
 * - admin_settings_sections: Add settings sections
 * 
 * Data Hooks:
 * - standings_calculated: After standings are calculated
 * - race_result_saved: After a race result is saved
 * - penalty_applied: After a penalty is applied
 * - driver_created: After a driver is created
 * - team_created: After a team is created
 * 
 * Export Hooks:
 * - export_data_prepare: Modify export data before export
 * - import_data_validate: Validate import data
 */
