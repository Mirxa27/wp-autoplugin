<?php
/**
 * Ajax Handler - Handles AJAX requests for plugin operations
 *
 * @package WP_Autoplugin\Admin
 * @since 2.0.0
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\API\ApiManager;
use WP_Autoplugin\Features\FeatureManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax Handler Class
 *
 * Handles AJAX requests for plugin generation, fixing, and extending.
 */
class AjaxHandler {
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
     * Constructor
     *
     * @param ApiManager $apiManager API Manager instance
     * @param FeatureManager $featureManager Feature Manager instance
     */
    public function __construct(ApiManager $apiManager, FeatureManager $featureManager) {
        $this->apiManager = $apiManager;
        $this->featureManager = $featureManager;
    }

    /**
     * Initialize AJAX handlers
     *
     * @return void
     */
    public function initialize(): void {
        // Generate plugin
        add_action('wp_ajax_wp_autoplugin_generate_plan', [$this, 'handleGeneratePlan']);
        add_action('wp_ajax_wp_autoplugin_generate_code', [$this, 'handleGenerateCode']);
        
        // Fix plugin
        add_action('wp_ajax_wp_autoplugin_fix_plugin', [$this, 'handleFixPlugin']);
        
        // Extend plugin
        add_action('wp_ajax_wp_autoplugin_extend_plugin', [$this, 'handleExtendPlugin']);
        
        // Explain plugin
        add_action('wp_ajax_wp_autoplugin_explain_plugin', [$this, 'handleExplainPlugin']);
        
        // Install plugin
        add_action('wp_ajax_wp_autoplugin_install_plugin', [$this, 'handleInstallPlugin']);
    }

    /**
     * Verify AJAX request
     *
     * @return bool
     */
    private function verifyRequest(): bool {
        if (!check_ajax_referer('wp-autoplugin-nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'wp-autoplugin')], 403);
            return false;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'wp-autoplugin')], 403);
            return false;
        }

        return true;
    }

    /**
     * Handle generate plan request
     *
     * @return void
     */
    public function handleGeneratePlan(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        if (empty($description)) {
            wp_send_json_error(['message' => __('Description is required', 'wp-autoplugin')], 400);
            return;
        }

        try {
            $plan = $this->apiManager->generatePlan($description);
            wp_send_json_success(['plan' => $plan]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle generate code request
     *
     * @return void
     */
    public function handleGenerateCode(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $planJson = isset($_POST['plan']) ? wp_unslash($_POST['plan']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $plan = json_decode($planJson, true);

        if (empty($plan)) {
            wp_send_json_error(['message' => __('Invalid plan data', 'wp-autoplugin')], 400);
            return;
        }

        try {
            $code = $this->apiManager->generateCode($plan);
            wp_send_json_success(['code' => $code]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle fix plugin request
     *
     * @return void
     */
    public function handleFixPlugin(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $code = isset($_POST['code']) ? wp_unslash($_POST['code']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $errorsJson = isset($_POST['errors']) ? wp_unslash($_POST['errors']) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $errors = json_decode($errorsJson, true) ?: [];

        if (empty($code)) {
            wp_send_json_error(['message' => __('Code is required', 'wp-autoplugin')], 400);
            return;
        }

        try {
            $fixedCode = $this->apiManager->fixPlugin($code, $errors);
            wp_send_json_success(['code' => $fixedCode]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle extend plugin request
     *
     * @return void
     */
    public function handleExtendPlugin(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $code = isset($_POST['code']) ? wp_unslash($_POST['code']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $requirements = isset($_POST['requirements']) ? sanitize_textarea_field(wp_unslash($_POST['requirements'])) : '';

        if (empty($code) || empty($requirements)) {
            wp_send_json_error(['message' => __('Code and requirements are required', 'wp-autoplugin')], 400);
            return;
        }

        try {
            $extendedCode = $this->apiManager->extendPlugin($code, $requirements);
            wp_send_json_success(['code' => $extendedCode]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle explain plugin request
     *
     * @return void
     */
    public function handleExplainPlugin(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $code = isset($_POST['code']) ? wp_unslash($_POST['code']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $detailLevel = isset($_POST['detail_level']) ? sanitize_text_field(wp_unslash($_POST['detail_level'])) : 'medium';

        if (empty($code)) {
            wp_send_json_error(['message' => __('Code is required', 'wp-autoplugin')], 400);
            return;
        }

        try {
            $explanation = $this->apiManager->explainPlugin($code, ['detail_level' => $detailLevel]);
            wp_send_json_success(['explanation' => $explanation]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle install plugin request
     *
     * @return void
     */
    public function handleInstallPlugin(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $code = isset($_POST['code']) ? wp_unslash($_POST['code']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $pluginName = isset($_POST['plugin_name']) ? sanitize_file_name(wp_unslash($_POST['plugin_name'])) : '';

        if (empty($code) || empty($pluginName)) {
            wp_send_json_error(['message' => __('Code and plugin name are required', 'wp-autoplugin')], 400);
            return;
        }

        try {
            // Create plugin file
            $pluginSlug = sanitize_title($pluginName);
            $pluginDir = WP_PLUGIN_DIR . '/' . $pluginSlug;
            $pluginFile = $pluginDir . '/' . $pluginSlug . '.php';

            if (!file_exists($pluginDir)) {
                wp_mkdir_p($pluginDir);
            }

            // Write code to file using WP_Filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $result = $wp_filesystem->put_contents($pluginFile, $code, FS_CHMOD_FILE);

            if ($result === false) {
                throw new \Exception(__('Failed to write plugin file', 'wp-autoplugin'));
            }

            // Track as autoplugin
            $autoplugins = get_option('wp_autoplugins', []);
            $autoplugins[] = $pluginSlug . '/' . $pluginSlug . '.php';
            update_option('wp_autoplugins', array_unique($autoplugins));

            wp_send_json_success([
                'message' => __('Plugin installed successfully', 'wp-autoplugin'),
                'plugin_file' => $pluginSlug . '/' . $pluginSlug . '.php'
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }
}
