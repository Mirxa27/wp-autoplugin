<?php
/**
 * Admin Pages - Renders admin page content
 *
 * @package WP_Autoplugin\Admin
 * @since 2.0.0
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\Features\FeatureManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Pages Class
 *
 * Handles rendering of admin page content.
 */
class AdminPages {
    /**
     * Feature Manager instance
     *
     * @var FeatureManager
     */
    private FeatureManager $featureManager;

    /**
     * Constructor
     *
     * @param FeatureManager $featureManager Feature Manager instance
     */
    public function __construct(FeatureManager $featureManager) {
        $this->featureManager = $featureManager;
    }

    /**
     * Initialize admin pages
     *
     * @return void
     */
    public function initialize(): void {
        // Nothing to initialize - pages are rendered by callbacks
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function renderDashboard(): void {
        $this->renderPage('dashboard');
    }

    /**
     * Render generate page
     *
     * @return void
     */
    public function renderGeneratePage(): void {
        if (!$this->featureManager->isEnabled('generate')) {
            $this->renderFeatureDisabled('generate');
            return;
        }
        include WP_AUTOPLUGIN_DIR . 'views/page-generate-plugin.php';
    }

    /**
     * Render fix page
     *
     * @return void
     */
    public function renderFixPage(): void {
        if (!$this->featureManager->isEnabled('fix')) {
            $this->renderFeatureDisabled('fix');
            return;
        }
        include WP_AUTOPLUGIN_DIR . 'views/page-fix-plugin.php';
    }

    /**
     * Render extend page
     *
     * @return void
     */
    public function renderExtendPage(): void {
        if (!$this->featureManager->isEnabled('extend')) {
            $this->renderFeatureDisabled('extend');
            return;
        }
        include WP_AUTOPLUGIN_DIR . 'views/page-extend-plugin.php';
    }

    /**
     * Render explain page
     *
     * @return void
     */
    public function renderExplainPage(): void {
        if (!$this->featureManager->isEnabled('explain')) {
            $this->renderFeatureDisabled('explain');
            return;
        }
        include WP_AUTOPLUGIN_DIR . 'views/page-explain-plugin.php';
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function renderSettingsPage(): void {
        include WP_AUTOPLUGIN_DIR . 'views/page-settings.php';
    }

    /**
     * Render history page
     *
     * @return void
     */
    public function renderHistoryPage(): void {
        $this->renderPage('history');
    }

    /**
     * Render a generic page
     *
     * @param string $page Page name
     * @return void
     */
    private function renderPage(string $page): void {
        $file = WP_AUTOPLUGIN_DIR . 'views/page-' . $page . '.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . esc_html(ucfirst($page)) . '</h1><p>' . 
                 esc_html__('Page content coming soon.', 'wp-autoplugin') . '</p></div>';
        }
    }

    /**
     * Render feature disabled message
     *
     * @param string $feature Feature name
     * @return void
     */
    private function renderFeatureDisabled(string $feature): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Feature Disabled', 'wp-autoplugin'); ?></h1>
            <div class="notice notice-warning">
                <p>
                    <?php
                    printf(
                        /* translators: %s: feature name */
                        esc_html__('The %s feature is currently disabled.', 'wp-autoplugin'),
                        esc_html($feature)
                    );
                    ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-autoplugin-settings')); ?>">
                        <?php esc_html_e('Enable in Settings', 'wp-autoplugin'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}
