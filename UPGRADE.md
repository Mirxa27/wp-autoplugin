# WP-Autoplugin 2.0 Upgrade Guide

## Overview

WP-Autoplugin 2.0 introduces a complete overhaul of the codebase with modern architecture, enhanced UI/UX, and improved performance. This guide will help you upgrade from version 1.x to 2.0.

## Major Changes

### 1. **Modern Architecture**
- Migrated to PSR-4 autoloading with proper namespacing
- Implemented service-based architecture with dependency injection
- Added comprehensive error handling and logging system
- Introduced provider-based API system for multiple AI services

### 2. **Enhanced Visual Feedback System**
- Real-time progress indicators for all operations
- Visual operation monitor showing current and past operations
- Toast notifications for quick feedback
- Smooth animations and transitions
- Dark mode support

### 3. **Modern JavaScript Build Pipeline**
- Webpack-based build system
- React components for complex UI elements
- ES6+ with Babel transpilation
- Code splitting for optimal performance
- Hot module replacement for development

### 4. **Improved Plugin Generation**
- Step-by-step wizard interface
- Live preview of generated code
- Advanced options for AI model selection
- Temperature control for creativity vs consistency
- Auto-save functionality

### 5. **Better Error Handling**
- Comprehensive logging system
- Graceful error recovery
- User-friendly error messages
- Automatic retry mechanism
- Debug mode for developers

### 6. **Performance Optimizations**
- Lazy loading of components
- Optimized asset delivery
- Caching improvements
- Reduced API calls
- Background processing for long operations

## Breaking Changes

### PHP Changes
- Minimum PHP version is now 7.4 (was 7.0)
- All classes are now namespaced under `WP_Autoplugin`
- Main plugin class is now `WP_Autoplugin\Core\Plugin`
- API classes moved to `WP_Autoplugin\API` namespace

### JavaScript Changes
- jQuery dependency reduced (vanilla JS and React)
- New module-based architecture
- Changed global object from `wp_autoplugin` to `wpAutoPlugin`

### Database Changes
- New table: `wp_autoplugin_operations` for operation history
- Updated option names (prefixed with `wp_autoplugin_`)

## Migration Steps

### 1. Backup Your Site
Before upgrading, create a complete backup of your WordPress site and database.

### 2. Check Requirements
- PHP 7.4 or higher
- WordPress 5.0 or higher
- Node.js 14+ (for development only)

### 3. Update Plugin
1. Deactivate the current version
2. Delete the old plugin files
3. Upload the new version
4. Activate the plugin

### 4. Update Settings
- API keys are migrated automatically
- Review and update any custom settings
- Test plugin generation functionality

### 5. Clear Caches
- Clear browser cache
- Clear any WordPress caching plugins
- Clear CDN cache if applicable

## New Features

### Visual Feedback System
```javascript
// Operations are now tracked visually
visualFeedback.startOperation('generate_plugin', {
    title: 'Generating Plugin',
    showProgress: true
});
```

### Modern API Client
```javascript
// New promise-based API
const plan = await apiClient.generatePlan(description, {
    model: 'gpt-4o',
    temperature: 0.7
});
```

### Enhanced UI Components
- Progress bars with real-time updates
- Status indicators for operations
- Modern card-based layouts
- Responsive design improvements

## Developer Notes

### Using the New Architecture
```php
// Get plugin instance
$plugin = \WP_Autoplugin\Core\Plugin::getInstance();

// Access services
$logger = $plugin->getService('logger');
$api = $plugin->getService('api');
```

### Custom Providers
```php
// Add custom AI provider
add_filter('wp_autoplugin_api_providers', function($providers) {
    $providers['custom'] = new CustomProvider();
    return $providers;
});
```

### JavaScript Hooks
```javascript
// Listen for operation events
visualFeedback.on('operation:completed', (operation) => {
    console.log('Operation completed:', operation);
});
```

## Troubleshooting

### Common Issues

1. **White screen after upgrade**
   - Check PHP version (must be 7.4+)
   - Enable WP_DEBUG to see errors
   - Check error logs

2. **JavaScript not loading**
   - Clear browser cache
   - Check console for errors
   - Verify asset files uploaded correctly

3. **API errors**
   - Re-enter API keys in settings
   - Check API provider status
   - Verify network connectivity

### Getting Help
- Check the [documentation](https://wp-autoplugin.com/docs)
- Visit our [support forum](https://wp-autoplugin.com/support)
- Report issues on [GitHub](https://github.com/wp-autoplugin/wp-autoplugin)

## Rollback Instructions

If you need to rollback to version 1.x:
1. Deactivate version 2.0
2. Delete version 2.0 files
3. Restore version 1.x files from backup
4. Activate the plugin
5. Restore database if needed