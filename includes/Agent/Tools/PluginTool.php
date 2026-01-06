<?php
/**
 * Plugin Tool - Manages WordPress plugins and WP.org API
 *
 * @package WP_Autoplugin\Agent\Tools
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent\Tools;

use WP_Autoplugin\Agent\AbstractTool;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Tool Class
 *
 * Provides tools for managing plugins and searching the WordPress.org plugin directory.
 * Does NOT hallucinate plugins - always verifies existence via WP.org API.
 */
class PluginTool extends AbstractTool {
    /**
     * Tool name
     *
     * @var string
     */
    protected string $name = 'plugins';

    /**
     * Required capability
     *
     * @var string
     */
    protected string $requiredCapability = 'activate_plugins';

    /**
     * WordPress.org API endpoint
     *
     * @var string
     */
    private string $apiEndpoint = 'https://api.wordpress.org/plugins/info/1.2/';

    /**
     * Get tool name
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get tool description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Search for plugins in WordPress.org directory, list installed plugins, activate/deactivate plugins, and get plugin recommendations. Always verifies plugins exist before recommending.', 'wp-autoplugin');
    }

    /**
     * Get parameter schema
     *
     * @return array
     */
    public function getParameterSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['search', 'info', 'list_installed', 'activate', 'deactivate', 'recommend'],
                    'description' => __('Action to perform', 'wp-autoplugin')
                ],
                'search_term' => [
                    'type' => 'string',
                    'description' => __('Search term for plugin search or recommendation', 'wp-autoplugin')
                ],
                'plugin_slug' => [
                    'type' => 'string',
                    'description' => __('Plugin slug (e.g., "contact-form-7")', 'wp-autoplugin')
                ],
                'plugin_file' => [
                    'type' => 'string',
                    'description' => __('Plugin file path for activate/deactivate (e.g., "akismet/akismet.php")', 'wp-autoplugin')
                ],
                'category' => [
                    'type' => 'string',
                    'description' => __('Plugin category for recommendations (e.g., "seo", "ecommerce", "forms")', 'wp-autoplugin')
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => __('Number of results to return', 'wp-autoplugin'),
                    'default' => 5
                ]
            ],
            'required' => ['action']
        ];
    }

    /**
     * Execute the tool
     *
     * @param array $params Parameters
     * @return array Result
     */
    public function execute(array $params): array {
        $validation = $this->validateParams($params);
        if (!$validation['valid']) {
            return $this->error(implode(', ', $validation['errors']), 'validation_error');
        }

        $action = $params['action'];

        switch ($action) {
            case 'search':
                return $this->searchPlugins($params);
            case 'info':
                return $this->getPluginInfo($params);
            case 'list_installed':
                return $this->listInstalledPlugins();
            case 'activate':
                return $this->activatePlugin($params);
            case 'deactivate':
                return $this->deactivatePlugin($params);
            case 'recommend':
                return $this->recommendPlugins($params);
            default:
                return $this->error(
                    sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action),
                    'invalid_action'
                );
        }
    }

    /**
     * Search plugins in WordPress.org directory
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function searchPlugins(array $params): array {
        if (empty($params['search_term'])) {
            return $this->error(__('Search term is required', 'wp-autoplugin'), 'missing_search_term');
        }

        $searchTerm = $this->sanitizeString($params['search_term']);
        $limit = min(absint($params['limit'] ?? 5), 20);

        $response = wp_remote_post($this->apiEndpoint, [
            'timeout' => 15,
            'body' => [
                'action' => 'query_plugins',
                'request' => serialize((object) [
                    'search' => $searchTerm,
                    'per_page' => $limit,
                    'fields' => [
                        'short_description' => true,
                        'icons' => true,
                        'ratings' => true,
                        'active_installs' => true,
                        'downloaded' => true,
                        'last_updated' => true
                    ]
                ])
            ]
        ]);

        if (is_wp_error($response)) {
            return $this->error(
                sprintf(__('API request failed: %s', 'wp-autoplugin'), $response->get_error_message()),
                'api_error'
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = unserialize($body);

        if (!$data || !isset($data->plugins)) {
            return $this->error(__('Invalid response from WordPress.org API', 'wp-autoplugin'), 'invalid_response');
        }

        $plugins = [];
        foreach ($data->plugins as $plugin) {
            $plugins[] = [
                'name' => $plugin->name,
                'slug' => $plugin->slug,
                'version' => $plugin->version,
                'author' => strip_tags($plugin->author),
                'description' => wp_trim_words($plugin->short_description, 20),
                'rating' => round($plugin->rating / 20, 1), // Convert to 5-star scale
                'active_installs' => $this->formatNumber($plugin->active_installs),
                'last_updated' => $plugin->last_updated,
                'download_link' => "https://wordpress.org/plugins/{$plugin->slug}/"
            ];
        }

        return $this->success([
            'plugins' => $plugins,
            'total' => $data->info['results'] ?? count($plugins),
            'search_term' => $searchTerm
        ]);
    }

    /**
     * Get detailed plugin info from WordPress.org
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function getPluginInfo(array $params): array {
        if (empty($params['plugin_slug'])) {
            return $this->error(__('Plugin slug is required', 'wp-autoplugin'), 'missing_plugin_slug');
        }

        $slug = $this->sanitizeString($params['plugin_slug']);

        $response = wp_remote_post($this->apiEndpoint, [
            'timeout' => 15,
            'body' => [
                'action' => 'plugin_information',
                'request' => serialize((object) [
                    'slug' => $slug,
                    'fields' => [
                        'short_description' => true,
                        'sections' => true,
                        'ratings' => true,
                        'active_installs' => true,
                        'downloaded' => true,
                        'last_updated' => true,
                        'homepage' => true,
                        'requires' => true,
                        'tested' => true,
                        'requires_php' => true
                    ]
                ])
            ]
        ]);

        if (is_wp_error($response)) {
            return $this->error(
                sprintf(__('API request failed: %s', 'wp-autoplugin'), $response->get_error_message()),
                'api_error'
            );
        }

        $body = wp_remote_retrieve_body($response);
        $plugin = unserialize($body);

        if (!$plugin || isset($plugin->error)) {
            return $this->error(
                sprintf(__('Plugin not found: %s', 'wp-autoplugin'), $slug),
                'plugin_not_found'
            );
        }

        return $this->success([
            'name' => $plugin->name,
            'slug' => $plugin->slug,
            'version' => $plugin->version,
            'author' => strip_tags($plugin->author),
            'description' => wp_trim_words($plugin->short_description, 50),
            'rating' => round($plugin->rating / 20, 1),
            'active_installs' => $this->formatNumber($plugin->active_installs),
            'downloaded' => $this->formatNumber($plugin->downloaded),
            'last_updated' => $plugin->last_updated,
            'requires_wp' => $plugin->requires ?? 'Unknown',
            'tested_wp' => $plugin->tested ?? 'Unknown',
            'requires_php' => $plugin->requires_php ?? 'Unknown',
            'homepage' => $plugin->homepage ?? '',
            'download_link' => "https://wordpress.org/plugins/{$plugin->slug}/",
            'exists_in_directory' => true
        ]);
    }

    /**
     * List installed plugins
     *
     * @return array Result
     */
    private function listInstalledPlugins(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);

        $plugins = [];
        foreach ($allPlugins as $pluginFile => $pluginData) {
            $plugins[] = [
                'name' => $pluginData['Name'],
                'file' => $pluginFile,
                'version' => $pluginData['Version'],
                'author' => $pluginData['AuthorName'],
                'description' => wp_trim_words($pluginData['Description'], 20),
                'is_active' => in_array($pluginFile, $activePlugins, true),
                'text_domain' => $pluginData['TextDomain'] ?? ''
            ];
        }

        return $this->success([
            'plugins' => $plugins,
            'total' => count($plugins),
            'active_count' => count($activePlugins)
        ]);
    }

    /**
     * Activate a plugin
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function activatePlugin(array $params): array {
        if (empty($params['plugin_file'])) {
            return $this->error(__('Plugin file is required', 'wp-autoplugin'), 'missing_plugin_file');
        }

        if (!current_user_can('activate_plugins')) {
            return $this->error(__('You do not have permission to activate plugins', 'wp-autoplugin'), 'permission_denied');
        }

        $pluginFile = $this->sanitizeString($params['plugin_file']);

        // Validate plugin exists
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        if (!isset($allPlugins[$pluginFile])) {
            return $this->error(
                sprintf(__('Plugin not found: %s', 'wp-autoplugin'), $pluginFile),
                'plugin_not_found'
            );
        }

        // Check if already active
        if (is_plugin_active($pluginFile)) {
            return $this->success([
                'plugin_file' => $pluginFile,
                'already_active' => true
            ], __('Plugin is already active', 'wp-autoplugin'));
        }

        // Activate the plugin
        $result = activate_plugin($pluginFile);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 'activation_failed');
        }

        $this->logExecution('activate', $params, ['success' => true]);

        return $this->success([
            'plugin_file' => $pluginFile,
            'plugin_name' => $allPlugins[$pluginFile]['Name']
        ], sprintf(__('Activated plugin: %s', 'wp-autoplugin'), $allPlugins[$pluginFile]['Name']));
    }

    /**
     * Deactivate a plugin
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function deactivatePlugin(array $params): array {
        if (empty($params['plugin_file'])) {
            return $this->error(__('Plugin file is required', 'wp-autoplugin'), 'missing_plugin_file');
        }

        if (!current_user_can('deactivate_plugins')) {
            return $this->error(__('You do not have permission to deactivate plugins', 'wp-autoplugin'), 'permission_denied');
        }

        $pluginFile = $this->sanitizeString($params['plugin_file']);

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = get_plugins();
        if (!isset($allPlugins[$pluginFile])) {
            return $this->error(
                sprintf(__('Plugin not found: %s', 'wp-autoplugin'), $pluginFile),
                'plugin_not_found'
            );
        }

        // Check if already inactive
        if (!is_plugin_active($pluginFile)) {
            return $this->success([
                'plugin_file' => $pluginFile,
                'already_inactive' => true
            ], __('Plugin is already inactive', 'wp-autoplugin'));
        }

        // Deactivate the plugin
        deactivate_plugins($pluginFile);

        $this->logExecution('deactivate', $params, ['success' => true]);

        return $this->success([
            'plugin_file' => $pluginFile,
            'plugin_name' => $allPlugins[$pluginFile]['Name']
        ], sprintf(__('Deactivated plugin: %s', 'wp-autoplugin'), $allPlugins[$pluginFile]['Name']));
    }

    /**
     * Recommend plugins based on category or use case
     * Always verifies plugins exist in WP.org directory
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function recommendPlugins(array $params): array {
        $category = $this->sanitizeString($params['category'] ?? $params['search_term'] ?? '');
        
        if (empty($category)) {
            return $this->error(__('Category or search term is required for recommendations', 'wp-autoplugin'), 'missing_category');
        }

        // Map common categories to search terms
        $categoryMappings = [
            'seo' => 'seo',
            'ecommerce' => 'woocommerce',
            'forms' => 'contact form',
            'security' => 'security',
            'backup' => 'backup',
            'cache' => 'cache performance',
            'social' => 'social media sharing',
            'analytics' => 'analytics',
            'real_estate' => 'real estate property',
            'hotel' => 'hotel booking',
            'restaurant' => 'restaurant menu',
            'portfolio' => 'portfolio gallery',
            'membership' => 'membership subscription',
            'learning' => 'lms course',
            'events' => 'events calendar'
        ];

        $searchTerm = $categoryMappings[strtolower($category)] ?? $category;
        $limit = min(absint($params['limit'] ?? 5), 10);

        // Search WordPress.org API
        $searchResult = $this->searchPlugins([
            'search_term' => $searchTerm,
            'limit' => $limit
        ]);

        if (!$searchResult['success']) {
            return $searchResult;
        }

        // Get installed plugins to mark which are already installed
        $installedResult = $this->listInstalledPlugins();
        $installedSlugs = [];
        
        if ($installedResult['success']) {
            foreach ($installedResult['data']['plugins'] as $plugin) {
                // Extract slug from file path
                $parts = explode('/', $plugin['file']);
                $installedSlugs[] = $parts[0];
            }
        }

        // Mark installed status
        $recommendations = [];
        foreach ($searchResult['data']['plugins'] as $plugin) {
            $plugin['is_installed'] = in_array($plugin['slug'], $installedSlugs, true);
            $recommendations[] = $plugin;
        }

        return $this->success([
            'category' => $category,
            'recommendations' => $recommendations,
            'note' => __('All recommended plugins are verified to exist in the WordPress.org plugin directory.', 'wp-autoplugin')
        ]);
    }

    /**
     * Format large numbers for display
     *
     * @param int $number Number to format
     * @return string Formatted number
     */
    private function formatNumber(int $number): string {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M+';
        }
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K+';
        }
        return (string) $number;
    }

    /**
     * Perform dry run
     *
     * @param array $params Parameters
     * @return string Description of what would happen
     */
    public function dryRun(array $params): string {
        $action = $params['action'] ?? 'unknown';

        switch ($action) {
            case 'search':
                $term = $params['search_term'] ?? __('unspecified', 'wp-autoplugin');
                return sprintf(__('Will search WordPress.org for plugins matching: %s', 'wp-autoplugin'), $term);

            case 'info':
                $slug = $params['plugin_slug'] ?? __('unspecified', 'wp-autoplugin');
                return sprintf(__('Will fetch information about plugin: %s from WordPress.org', 'wp-autoplugin'), $slug);

            case 'list_installed':
                return __('Will list all installed plugins (read-only operation)', 'wp-autoplugin');

            case 'activate':
                $file = $params['plugin_file'] ?? __('unspecified', 'wp-autoplugin');
                return sprintf(__('Will activate plugin: %s', 'wp-autoplugin'), $file);

            case 'deactivate':
                $file = $params['plugin_file'] ?? __('unspecified', 'wp-autoplugin');
                return sprintf(__('Will deactivate plugin: %s', 'wp-autoplugin'), $file);

            case 'recommend':
                $category = $params['category'] ?? $params['search_term'] ?? __('unspecified', 'wp-autoplugin');
                return sprintf(__('Will search WordPress.org and recommend plugins for: %s', 'wp-autoplugin'), $category);

            default:
                return sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action);
        }
    }
}
