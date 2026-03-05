# GridKing Plugin System

Welcome to the GridKing Plugin System (Legacy 1.4.0)! This directory contains plugins that extend the functionality of your racing league management system.

## Creating a Plugin

To create a plugin, create a new directory inside `/plugins/` with your plugin's name (e.g., `/plugins/my-plugin/`).

### Required Files

Your plugin directory should contain at least:

1. **`plugin.php`** - The main plugin file that gets loaded
2. **`manifest.json`** (optional but recommended) - Plugin metadata

### manifest.json Example

```json
{
    "name": "My Custom Plugin",
    "version": "1.0.0",
    "author": "Your Name",
    "description": "A brief description of what your plugin does",
    "requires": "1.4.0",
    "hooks": ["race_result_saved", "standings_calculated"]
}
```

### plugin.php Example

```php
<?php
/**
 * My Custom Plugin
 * 
 * This plugin demonstrates how to use the GridKing hook system.
 */

// Register a hook callback
PluginHooks::register('race_result_saved', function($data) {
    // Your code here - runs after a race result is saved
    // $data contains information about the saved result
    return $data;
});

// Add a widget to the admin dashboard
PluginHooks::register('admin_dashboard_widgets', function($widgets) {
    $widgets[] = [
        'title' => 'My Widget',
        'content' => '<p>Hello from my plugin!</p>',
        'icon' => 'bi-puzzle'
    ];
    return $widgets;
});
```

## Available Hooks

### Core Hooks
- `plugins_loaded` - Called after all plugins are loaded
- `init` - Called during application initialization
- `shutdown` - Called before application shutdown

### Page Hooks
- `header_before` - Before header output
- `header_after` - After header output
- `footer_before` - Before footer output
- `footer_after` - After footer output
- `page_content` - Modify page content

### Admin Hooks
- `admin_dashboard_widgets` - Add widgets to admin dashboard
- `admin_menu_items` - Add items to admin menu
- `admin_settings_sections` - Add settings sections

### Data Hooks
- `standings_calculated` - After standings are calculated
- `race_result_saved` - After a race result is saved
- `penalty_applied` - After a penalty is applied
- `driver_created` - After a driver is created
- `team_created` - After a team is created

### Export Hooks
- `export_data_prepare` - Modify export data before export
- `import_data_validate` - Validate import data

## Plugin Management

Plugins can be enabled/disabled through the Admin Panel under **Settings > Plugins**.

## Lite Mode

When Lite Mode is enabled (via Feature Toggles), all plugins are automatically disabled to improve performance. This is useful for servers with limited resources.

## Security Notes

- Plugins run with full PHP access - only install plugins from trusted sources
- Plugin files are protected from direct HTTP access via `.htaccess`
- Always validate and sanitize any user input in your plugins
