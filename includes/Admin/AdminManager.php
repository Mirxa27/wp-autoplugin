<?php
/**
 * Admin Manager - Handles all admin-related functionality
 *
 * @package WP_Autoplugin\Admin
 * @since 2.0.0
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\Agent\AgentManager;
use WP_Autoplugin\API\ApiManager;
use WP_Autoplugin\Features\FeatureManager;
use WP_Autoplugin\Utils\Assets;
use WP_Autoplugin\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Manager Class
 */
class AdminManager {
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private ApiManager $apiManager;

    /**
     * Feature Manager instance
     *
     * @var FeatureManager
     */
    private FeatureManager $featureManager;

    /**
     * Assets Manager instance
     *
     * @var Assets
     */
    private Assets $assets;

    /**
     * Agent Manager instance
     *
     * @var AgentManager
     */
    private AgentManager $agentManager;

    /**
     * Ajax Handler instance
     *
     * @var AjaxHandler
     */
    private AjaxHandler $ajaxHandler;

    /**
     * Admin Pages instance
     *
     * @var AdminPages
     */
    private AdminPages $adminPages;

    /**
     * Agent Ajax Handler instance
     *
     * @var AgentAjaxHandler
     */
    private AgentAjaxHandler $agentAjaxHandler;

    /**
     * Agent Pages instance
     *
     * @var AgentPages
     */
    private AgentPages $agentPages;

    /**
     * Constructor
     */
    public function __construct(ApiManager $apiManager, FeatureManager $featureManager, Assets $assets, AgentManager $agentManager) {
        $this->apiManager = $apiManager;
        $this->featureManager = $featureManager;
        $this->assets = $assets;
        $this->agentManager = $agentManager;
    }

    /**
     * Initialize admin functionality
     */
    public function initialize(): void {
        // Initialize components
        $this->ajaxHandler = new AjaxHandler($this->apiManager, $this->featureManager);
        $this->adminPages = new AdminPages($this->featureManager);

        // Initialize agent components
        $this->agentAjaxHandler = new AgentAjaxHandler($this->agentManager);
        $this->agentPages = new AgentPages($this->agentManager, $this->assets);

        // Register hooks
        $this->registerHooks();

        // Initialize sub-components
        $this->ajaxHandler->initialize();
        $this->adminPages->initialize();
        $this->agentAjaxHandler->initialize();
        $this->agentPages->initialize();
    }

    /**
     * Register admin hooks
     */
    private function registerHooks(): void {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Admin notices
        add_action('admin_notices', [$this, 'displayAdminNotices']);

        // Plugin action links
        add_filter('plugin_action_links_' . WP_AUTOPLUGIN_BASENAME, [$this, 'addActionLinks']);

        // Admin bar
        add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 100);

        // Dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidgets']);

        // Capabilities
        add_action('admin_init', [$this, 'registerCapabilities']);
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void {
        $capability = 'manage_options';

        // Main menu
        add_menu_page(
            __('WP-Autoplugin', 'wp-autoplugin'),
            __('WP-Autoplugin', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin',
            [$this->adminPages, 'renderDashboard'],
            'dashicons-admin-plugins',
            65
        );

        // Submenu pages
        add_submenu_page(
            'wp-autoplugin',
            __('Dashboard', 'wp-autoplugin'),
            __('Dashboard', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin',
            [$this->adminPages, 'renderDashboard']
        );

        add_submenu_page(
            'wp-autoplugin',
            __('Generate Plugin', 'wp-autoplugin'),
            __('Generate Plugin', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin-generate',
            [$this->adminPages, 'renderGeneratePage']
        );

        add_submenu_page(
            'wp-autoplugin',
            __('Fix Plugin', 'wp-autoplugin'),
            __('Fix Plugin', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin-fix',
            [$this->adminPages, 'renderFixPage']
        );

        add_submenu_page(
            'wp-autoplugin',
            __('Extend Plugin', 'wp-autoplugin'),
            __('Extend Plugin', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin-extend',
            [$this->adminPages, 'renderExtendPage']
        );

        add_submenu_page(
            'wp-autoplugin',
            __('Explain Plugin', 'wp-autoplugin'),
            __('Explain Plugin', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin-explain',
            [$this->adminPages, 'renderExplainPage']
        );

        add_submenu_page(
            'wp-autoplugin',
            __('Settings', 'wp-autoplugin'),
            __('Settings', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin-settings',
            [$this->adminPages, 'renderSettingsPage']
        );

        // Hidden pages
        add_submenu_page(
            null,
            __('Operation History', 'wp-autoplugin'),
            __('Operation History', 'wp-autoplugin'),
            $capability,
            'wp-autoplugin-history',
            [$this->adminPages, 'renderHistoryPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook): void {
        // Only load on our pages
        if (!$this->isPluginPage($hook)) {
            return;
        }

        // Get current page
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Core dependencies
        $deps = [
            'jquery',
            'wp-element',
            'wp-components',
            'wp-i18n',
            'wp-hooks',
            'wp-data'
        ];

        // Enqueue vendor bundle
        $this->assets->enqueueScript(
            'wp-autoplugin-vendor',
            'dist/js/vendor.bundle.js',
            [],
            true
        );

        // Enqueue admin core
        $this->assets->enqueueScript(
            'wp-autoplugin-admin',
            'dist/js/admin.bundle.js',
            array_merge(['wp-autoplugin-vendor'], $deps),
            true
        );

        // Page-specific scripts
        $pageScripts = [
            'wp-autoplugin-generate' => 'generator',
            'wp-autoplugin-fix' => 'fixer',
            'wp-autoplugin-extend' => 'extender',
            'wp-autoplugin-explain' => 'explainer'
        ];

        if (isset($pageScripts[$page])) {
            $this->assets->enqueueScript(
                'wp-autoplugin-' . $pageScripts[$page],
                'dist/js/' . $pageScripts[$page] . '.bundle.js',
                ['wp-autoplugin-admin'],
                true
            );
        }

        // Visual feedback system
        $this->assets->enqueueScript(
            'wp-autoplugin-visual-feedback',
            'dist/js/visual-feedback.bundle.js',
            ['wp-autoplugin-admin'],
            true
        );

        // Styles
        $this->assets->enqueueStyle(
            'wp-autoplugin-admin-styles',
            'dist/css/admin-styles.css'
        );

        // Localize script
        wp_localize_script('wp-autoplugin-admin', 'wpAutoPlugin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp-autoplugin-nonce'),
            'apiUrl' => rest_url('wp-autoplugin/v1'),
            'pluginUrl' => WP_AUTOPLUGIN_URL,
            'settings' => $this->getClientSettings(),
            'i18n' => $this->getI18nStrings(),
            'features' => $this->featureManager->getEnabledFeatures(),
            'models' => $this->apiManager->getAvailableModels()
        ]);

        // Add inline styles for immediate visual feedback
        wp_add_inline_style('wp-autoplugin-admin-styles', $this->getInlineStyles());
    }

    /**
     * Check if current page is plugin page
     */
    private function isPluginPage($hook): bool {
        $pluginPages = [
            'toplevel_page_wp-autoplugin',
            'wp-autoplugin_page_wp-autoplugin-generate',
            'wp-autoplugin_page_wp-autoplugin-fix',
            'wp-autoplugin_page_wp-autoplugin-extend',
            'wp-autoplugin_page_wp-autoplugin-explain',
            'wp-autoplugin_page_wp-autoplugin-settings',
            'wp-autoplugin_page_wp-autoplugin-agent',
            'admin_page_wp-autoplugin-history'
        ];

        return in_array($hook, $pluginPages, true);
    }

    /**
     * Get client settings
     */
    private function getClientSettings(): array {
        return [
            'apiProvider' => get_option('wp_autoplugin_api_provider', 'openai'),
            'defaultModel' => get_option('wp_autoplugin_model', 'gpt-4o'),
            'enableVisualFeedback' => get_option('wp_autoplugin_enable_visual_feedback', true),
            'enableNotifications' => get_option('wp_autoplugin_enable_notifications', true),
            'maxRetries' => get_option('wp_autoplugin_max_retries', 3),
            'timeout' => get_option('wp_autoplugin_timeout', 120),
            'streamResponses' => get_option('wp_autoplugin_stream_responses', true)
        ];
    }

    /**
     * Get i18n strings
     */
    private function getI18nStrings(): array {
        return [
            'generating' => __('Generating...', 'wp-autoplugin'),
            'success' => __('Success!', 'wp-autoplugin'),
            'error' => __('Error', 'wp-autoplugin'),
            'retry' => __('Retry', 'wp-autoplugin'),
            'cancel' => __('Cancel', 'wp-autoplugin'),
            'confirmCancel' => __('Are you sure you want to cancel this operation?', 'wp-autoplugin'),
            'networkError' => __('Network error. Please check your connection.', 'wp-autoplugin'),
            'unexpectedError' => __('An unexpected error occurred. Please try again.', 'wp-autoplugin')
        ];
    }

    /**
     * Get inline styles
     */
    private function getInlineStyles(): string {
        return '
            /* Smooth transitions */
            .wp-autoplugin-transition {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            /* Loading states */
            .wp-autoplugin-loading {
                opacity: 0.6;
                pointer-events: none;
            }
            
            /* Focus styles */
            .wp-autoplugin-focus:focus {
                outline: 2px solid #3b82f6;
                outline-offset: 2px;
            }
        ';
    }

    /**
     * Display admin notices
     */
    public function displayAdminNotices(): void {
        // Check for transient notices
        $notice = get_transient('wp_autoplugin_admin_notice');
        if ($notice) {
            $type = $notice['type'] ?? 'info';
            $message = $notice['message'] ?? '';
            $dismissible = $notice['dismissible'] ?? true;

            if ($message) {
                printf(
                    '<div class="notice notice-%s %s"><p>%s</p></div>',
                    esc_attr($type),
                    $dismissible ? 'is-dismissible' : '',
                    esc_html($message)
                );
            }

            delete_transient('wp_autoplugin_admin_notice');
        }

        // Check for API key
        if ($this->isPluginPage($GLOBALS['hook_suffix'] ?? '') && !$this->apiManager->hasValidApiKey()) {
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('WP-Autoplugin requires an API key to function.', 'wp-autoplugin'),
                esc_url(admin_url('admin.php?page=wp-autoplugin-settings')),
                esc_html__('Configure Settings', 'wp-autoplugin')
            );
        }
    }

    /**
     * Add plugin action links
     */
    public function addActionLinks($links): array {
        $actionLinks = [
            '<a href="' . esc_url( admin_url('admin.php?page=wp-autoplugin-settings') ) . '">' . esc_html__('Settings', 'wp-autoplugin') . '</a>',
            '<a href="' . esc_url( admin_url('admin.php?page=wp-autoplugin-generate') ) . '">' . esc_html__('Generate Plugin', 'wp-autoplugin') . '</a>'
        ];

        return array_merge($actionLinks, $links);
    }

    /**
     * Add admin bar menu
     */
    public function addAdminBarMenu($wpAdminBar): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wpAdminBar->add_node([
            'id' => 'wp-autoplugin',
            'title' => '<span class="ab-icon dashicons dashicons-admin-plugins"></span>' . __('WP-Autoplugin', 'wp-autoplugin'),
            'href' => admin_url('admin.php?page=wp-autoplugin'),
            'meta' => [
                'title' => __('WP-Autoplugin Quick Access', 'wp-autoplugin')
            ]
        ]);

        // Sub items
        $wpAdminBar->add_node([
            'id' => 'wp-autoplugin-agent',
            'parent' => 'wp-autoplugin',
            'title' => __('AI Agent', 'wp-autoplugin'),
            'href' => admin_url('admin.php?page=wp-autoplugin-agent')
        ]);

        $wpAdminBar->add_node([
            'id' => 'wp-autoplugin-generate',
            'parent' => 'wp-autoplugin',
            'title' => __('Generate Plugin', 'wp-autoplugin'),
            'href' => admin_url('admin.php?page=wp-autoplugin-generate')
        ]);

        $wpAdminBar->add_node([
            'id' => 'wp-autoplugin-history',
            'parent' => 'wp-autoplugin',
            'title' => __('Operation History', 'wp-autoplugin'),
            'href' => admin_url('admin.php?page=wp-autoplugin-history')
        ]);
    }

    /**
     * Add dashboard widgets
     */
    public function addDashboardWidgets(): void {
        wp_add_dashboard_widget(
            'wp_autoplugin_stats',
            __('WP-Autoplugin Statistics', 'wp-autoplugin'),
            [$this, 'renderDashboardWidget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget(): void {
        $stats = $this->getPluginStats();
        ?>
        <div class="wp-autoplugin-dashboard-widget">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($stats['total_generated']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Plugins Generated', 'wp-autoplugin'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($stats['total_fixed']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Plugins Fixed', 'wp-autoplugin'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html($stats['total_extended']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Plugins Extended', 'wp-autoplugin'); ?></span>
                </div>
            </div>
            <p class="widget-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-autoplugin-generate')); ?>" class="button button-primary">
                    <?php esc_html_e('Generate New Plugin', 'wp-autoplugin'); ?>
                </a>
            </p>
        </div>
        <style>
            .wp-autoplugin-dashboard-widget .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 16px;
            }
            .wp-autoplugin-dashboard-widget .stat-item {
                text-align: center;
            }
            .wp-autoplugin-dashboard-widget .stat-value {
                display: block;
                font-size: 24px;
                font-weight: 600;
                color: #3b82f6;
            }
            .wp-autoplugin-dashboard-widget .stat-label {
                display: block;
                font-size: 12px;
                color: #64748b;
                margin-top: 4px;
            }
            .wp-autoplugin-dashboard-widget .widget-actions {
                text-align: center;
                margin: 0;
                padding-top: 16px;
                border-top: 1px solid #e5e7eb;
            }
        </style>
        <?php
    }

    /**
     * Get plugin statistics
     */
    private function getPluginStats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'autoplugin_operations';

        $stats = [
            'total_generated' => 0,
            'total_fixed' => 0,
            'total_extended' => 0
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats['total_generated'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table}` WHERE operation_type = %s AND status = %s",
                    'generate',
                    'completed'
                )
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats['total_fixed'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table}` WHERE operation_type = %s AND status = %s",
                    'fix',
                    'completed'
                )
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats['total_extended'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table}` WHERE operation_type = %s AND status = %s",
                    'extend',
                    'completed'
                )
            );
        }

        return $stats;
    }

    /**
     * Register capabilities
     */
    public function registerCapabilities(): void {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('wp_autoplugin_generate');
            $role->add_cap('wp_autoplugin_fix');
            $role->add_cap('wp_autoplugin_extend');
            $role->add_cap('wp_autoplugin_explain');
            $role->add_cap('wp_autoplugin_settings');
        }
    }
}