<?php
/**
 * Example Plugin for GridKing
 * 
 * This is a sample plugin demonstrating the GridKing plugin system.
 * It shows how to use hooks to extend functionality.
 * 
 * @version 1.0.0
 * @author GridKing Team
 */

// Register a hook that runs when the dashboard loads widgets
PluginHooks::register('admin_dashboard_widgets', function($widgets) {
    $widgets = $widgets ?? [];
    $widgets[] = [
        'title' => 'Example Widget',
        'content' => '<p class="text-muted">This widget was added by the Example Plugin!</p>
                      <p class="small">The plugin system is working correctly.</p>',
        'icon' => 'bi-puzzle-fill',
        'color' => 'info'
    ];
    return $widgets;
}, 10);

// Register a hook that runs after race results are saved
PluginHooks::register('race_result_saved', function($data) {
    // Example: Log when a race result is saved
    // In a real plugin, you might send a Discord notification or update statistics
    error_log('[ExamplePlugin] Race result saved: ' . json_encode($data));
    return $data;
}, 50);

// Register an initialization hook
PluginHooks::register('plugins_loaded', function($loadedPlugins) {
    // This runs after all plugins are loaded
    // Useful for setting up plugin dependencies or configurations
    return $loadedPlugins;
}, 100);
