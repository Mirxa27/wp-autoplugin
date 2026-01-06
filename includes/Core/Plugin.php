<?php
/**
 * Core Plugin Class - Main plugin initialization
 *
 * @package WP_Autoplugin\Core
 * @since 2.0.0
 */

namespace WP_Autoplugin\Core;

use WP_Autoplugin\Admin\AdminManager;
use WP_Autoplugin\Agent\AgentManager;
use WP_Autoplugin\API\ApiManager;
use WP_Autoplugin\Features\FeatureManager;
use WP_Autoplugin\Utils\Assets;
use WP_Autoplugin\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
final class Plugin {
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    private string $version = '2.0.0';

    /**
     * Container for all services
     *
     * @var array
     */
    private array $services = [];

    /**
     * Plugin initialization status
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Get plugin instance (Singleton)
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->defineConstants();
        $this->registerHooks();
    }

    /**
     * Define plugin constants
     *
     * @return void
     */
    private function defineConstants(): void {
        if (!defined('WP_AUTOPLUGIN_VERSION')) {
            define('WP_AUTOPLUGIN_VERSION', $this->version);
        }
        if (!defined('WP_AUTOPLUGIN_DIR')) {
            define('WP_AUTOPLUGIN_DIR', plugin_dir_path(dirname(dirname(__FILE__))));
        }
        if (!defined('WP_AUTOPLUGIN_URL')) {
            define('WP_AUTOPLUGIN_URL', plugin_dir_url(dirname(dirname(__FILE__))));
        }
        if (!defined('WP_AUTOPLUGIN_BASENAME')) {
            define('WP_AUTOPLUGIN_BASENAME', plugin_basename(dirname(dirname(dirname(__FILE__))) . '/wp-autoplugin.php'));
        }
    }

    /**
     * Register core hooks
     *
     * @return void
     */
    private function registerHooks(): void {
        // Activation/Deactivation hooks
        register_activation_hook(WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php', [$this, 'activate']);
        register_deactivation_hook(WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php', [$this, 'deactivate']);

        // Initialize plugin
        add_action('plugins_loaded', [$this, 'initialize']);
        add_action('init', [$this, 'loadTextdomain']);
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    public function initialize(): void {
        if ($this->initialized) {
            return;
        }

        try {
            // Initialize logger first
            $this->services['logger'] = new Logger();

            // Initialize core services
            $this->initializeServices();

            // Set initialization flag
            $this->initialized = true;

            // Fire action for extensions
            do_action('wp_autoplugin_initialized', $this);
        } catch (\Exception $e) {
            if (isset($this->services['logger'])) {
                $this->services['logger']->error('Plugin initialization failed: ' . $e->getMessage());
            }
            add_action('admin_notices', function() use ($e) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__('WP-Autoplugin initialization failed: ', 'wp-autoplugin') . esc_html($e->getMessage())
                );
            });
        }
    }

    /**
     * Initialize all services
     *
     * @return void
     */
    private function initializeServices(): void {
        // Assets manager
        $this->services['assets'] = new Assets();

        // API Manager
        $this->services['api'] = new ApiManager();

        // Feature Manager
        $this->services['features'] = new FeatureManager($this->services['api']);

        // Agent Manager
        $this->services['agent'] = new AgentManager($this->services['api']);

        // Admin Manager (only in admin)
        if (is_admin()) {
            $this->services['admin'] = new AdminManager(
                $this->services['api'],
                $this->services['features'],
                $this->services['assets'],
                $this->services['agent']
            );
        }

        // Initialize all services
        foreach ($this->services as $service) {
            if (method_exists($service, 'initialize')) {
                $service->initialize();
            }
        }
    }

    /**
     * Load plugin textdomain
     *
     * @return void
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            'wp-autoplugin',
            false,
            dirname(plugin_basename(WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php')) . '/languages'
        );
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate(): void {
        // Create necessary database tables
        $this->createTables();

        // Set default options
        $this->setDefaultOptions();

        // Clear any caches
        wp_cache_flush();

        // Log activation
        if (isset($this->services['logger'])) {
            $this->services['logger']->info('Plugin activated');
        }
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate(): void {
        // Clean up scheduled tasks
        wp_clear_scheduled_hook('wp_autoplugin_cleanup');

        // Log deactivation
        if (isset($this->services['logger'])) {
            $this->services['logger']->info('Plugin deactivated');
        }
    }

    /**
     * Create database tables
     *
     * @return void
     */
    private function createTables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Plugin operations log table
        $table_name = $wpdb->prefix . 'autoplugin_operations';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            details longtext,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY operation_type (operation_type),
            KEY status (status),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private function setDefaultOptions(): void {
        $defaults = [
            'wp_autoplugin_api_provider' => 'openai',
            'wp_autoplugin_model' => 'gpt-4o',
            'wp_autoplugin_enable_visual_feedback' => true,
            'wp_autoplugin_enable_notifications' => true,
            'wp_autoplugin_log_level' => 'info',
            'wp_autoplugin_max_retries' => 3,
            'wp_autoplugin_timeout' => 120
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Get service instance
     *
     * @param string $service Service name
     * @return mixed|null
     */
    public function getService(string $service) {
        return $this->services[$service] ?? null;
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getVersion(): string {
        return $this->version;
    }
}