<?php
/**
 * Plugin Name: WP-Autoplugin
 * Description: A plugin that generates other plugins on-demand using AI.
 * Version: 2.0.0
 * Author: Balázs Piller
 * Author URI: https://wp-autoplugin.com
 * Text Domain: wp-autoplugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 2.0.0
 * @link https://wp-autoplugin.com
 *
 * @wordpress-plugin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version
define('WP_AUTOPLUGIN_VERSION', '2.0.0');

// Plugin paths
define('WP_AUTOPLUGIN_FILE', __FILE__);
define('WP_AUTOPLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_AUTOPLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_AUTOPLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum requirements
define('WP_AUTOPLUGIN_MIN_PHP', '7.4');
define('WP_AUTOPLUGIN_MIN_WP', '5.0');

// Check requirements
if (version_compare(PHP_VERSION, WP_AUTOPLUGIN_MIN_PHP, '<')) {
    add_action('admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            sprintf(
                esc_html__('WP-Autoplugin requires PHP version %s or higher. You are running %s.', 'wp-autoplugin'),
                WP_AUTOPLUGIN_MIN_PHP,
                PHP_VERSION
            )
        );
    });
    return;
}

// Include the autoloader
require_once WP_AUTOPLUGIN_DIR . 'vendor/autoload.php';

// Include the main plugin class
require_once WP_AUTOPLUGIN_DIR . 'includes/Core/Plugin.php';

/**
 * Main plugin instance
 *
 * @return \WP_Autoplugin\Core\Plugin
 */
function wp_autoplugin() {
    return \WP_Autoplugin\Core\Plugin::getInstance();
}

// Initialize the plugin
wp_autoplugin();

// Global access functions

/**
 * Get plugin version
 *
 * @return string
 */
function wp_autoplugin_get_version() {
    return WP_AUTOPLUGIN_VERSION;
}

/**
 * Get plugin URL
 *
 * @param string $path Optional path to append
 * @return string
 */
function wp_autoplugin_get_url($path = '') {
    return WP_AUTOPLUGIN_URL . ltrim($path, '/');
}

/**
 * Get plugin path
 *
 * @param string $path Optional path to append
 * @return string
 */
function wp_autoplugin_get_path($path = '') {
    return WP_AUTOPLUGIN_DIR . ltrim($path, '/');
}

/**
 * Log a message
 *
 * @param string $message
 * @param string $level
 * @return void
 */
function wp_autoplugin_log($message, $level = 'info') {
    $logger = wp_autoplugin()->getService('logger');
    if ($logger) {
        $logger->log($message, $level);
    }
}