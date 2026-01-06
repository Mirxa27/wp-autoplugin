<?php
/**
 * Options Tool - Manages WordPress options
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
 * Options Tool Class
 *
 * Provides tools for managing WordPress options (site settings).
 */
class OptionsTool extends AbstractTool {
    /**
     * Tool name
     *
     * @var string
     */
    protected string $name = 'options';

    /**
     * Required capability
     *
     * @var string
     */
    protected string $requiredCapability = 'manage_options';

    /**
     * Allowed options that can be modified
     *
     * @var array
     */
    private array $allowedOptions = [
        'blogname',
        'blogdescription',
        'admin_email',
        'permalink_structure',
        'posts_per_page',
        'date_format',
        'time_format',
        'start_of_week',
        'timezone_string',
        'blog_public',
        'default_category',
        'default_post_format',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
        'default_ping_status',
        'default_comment_status'
    ];

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
        return __('Read and update WordPress site settings like site title, tagline, permalink structure, and other core options.', 'wp-autoplugin');
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
                    'enum' => ['get', 'set', 'list'],
                    'description' => __('Action to perform', 'wp-autoplugin')
                ],
                'option_name' => [
                    'type' => 'string',
                    'description' => __('Option name to get or set', 'wp-autoplugin')
                ],
                'option_value' => [
                    'type' => 'string',
                    'description' => __('Value to set for the option', 'wp-autoplugin')
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
            case 'get':
                return $this->getOption($params);
            case 'set':
                return $this->setOption($params);
            case 'list':
                return $this->listOptions();
            default:
                return $this->error(
                    sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action),
                    'invalid_action'
                );
        }
    }

    /**
     * Get an option
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function getOption(array $params): array {
        if (empty($params['option_name'])) {
            return $this->error(__('Option name is required', 'wp-autoplugin'), 'missing_option_name');
        }

        $optionName = $this->sanitizeString($params['option_name']);

        // Check if option is in allowed list or is a custom option prefixed with wp_autoplugin_
        if (!$this->isOptionAllowed($optionName)) {
            return $this->error(
                sprintf(__('Option not accessible: %s', 'wp-autoplugin'), $optionName),
                'option_not_allowed'
            );
        }

        $value = get_option($optionName);

        return $this->success([
            'option_name' => $optionName,
            'option_value' => $value
        ]);
    }

    /**
     * Set an option
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function setOption(array $params): array {
        if (empty($params['option_name'])) {
            return $this->error(__('Option name is required', 'wp-autoplugin'), 'missing_option_name');
        }

        if (!isset($params['option_value'])) {
            return $this->error(__('Option value is required', 'wp-autoplugin'), 'missing_option_value');
        }

        $optionName = $this->sanitizeString($params['option_name']);

        // Check if option is in allowed list
        if (!$this->isOptionAllowed($optionName)) {
            return $this->error(
                sprintf(__('Cannot modify option: %s. Only core site settings can be modified.', 'wp-autoplugin'), $optionName),
                'option_not_allowed'
            );
        }

        $oldValue = get_option($optionName);
        $newValue = $this->sanitizeOptionValue($optionName, $params['option_value']);

        // Special handling for permalink structure
        if ($optionName === 'permalink_structure') {
            $newValue = $this->sanitizePermalinkStructure($newValue);
        }

        $result = update_option($optionName, $newValue);

        // Flush rewrite rules if permalink structure changed
        if ($optionName === 'permalink_structure' && $result) {
            flush_rewrite_rules();
        }

        $this->logExecution('set', $params, ['success' => $result]);

        if ($result || $oldValue === $newValue) {
            return $this->success([
                'option_name' => $optionName,
                'old_value' => $oldValue,
                'new_value' => $newValue
            ], sprintf(__('Updated option: %s', 'wp-autoplugin'), $optionName));
        }

        return $this->error(__('Failed to update option', 'wp-autoplugin'), 'update_failed');
    }

    /**
     * List available options and their current values
     *
     * @return array Result
     */
    private function listOptions(): array {
        $options = [];

        foreach ($this->allowedOptions as $optionName) {
            $options[$optionName] = [
                'value' => get_option($optionName),
                'description' => $this->getOptionDescription($optionName)
            ];
        }

        return $this->success([
            'options' => $options,
            'total' => count($options)
        ]);
    }

    /**
     * Check if an option is allowed to be accessed/modified
     *
     * @param string $optionName Option name
     * @return bool
     */
    private function isOptionAllowed(string $optionName): bool {
        // Allow core options
        if (in_array($optionName, $this->allowedOptions, true)) {
            return true;
        }

        // Allow reading any option but restrict writing
        return false;
    }

    /**
     * Sanitize option value based on option name
     *
     * @param string $optionName Option name
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitizeOptionValue(string $optionName, $value) {
        switch ($optionName) {
            case 'blogname':
            case 'blogdescription':
                return sanitize_text_field($value);

            case 'admin_email':
                return sanitize_email($value);

            case 'posts_per_page':
            case 'start_of_week':
            case 'default_category':
            case 'page_on_front':
            case 'page_for_posts':
                return absint($value);

            case 'blog_public':
                return $value ? '1' : '0';

            case 'show_on_front':
                return in_array($value, ['posts', 'page'], true) ? $value : 'posts';

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Sanitize permalink structure
     *
     * @param string $structure Permalink structure
     * @return string Sanitized structure
     */
    private function sanitizePermalinkStructure(string $structure): string {
        // Common permalink structures
        $validStructures = [
            '',                          // Plain
            '/%year%/%monthnum%/%day%/%postname%/', // Day and name
            '/%year%/%monthnum%/%postname%/',       // Month and name
            '/archives/%post_id%',       // Numeric
            '/%postname%/',              // Post name
            '/%category%/%postname%/'    // Custom structure
        ];

        // If it's a valid predefined structure, use it
        if (in_array($structure, $validStructures, true)) {
            return $structure;
        }

        // Otherwise sanitize custom structure
        $allowed = ['%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%postname%', '%category%', '%author%'];
        
        // Keep only allowed tags and slashes
        $sanitized = preg_replace('/[^a-zA-Z0-9\/%_-]/', '', $structure);
        
        return $sanitized;
    }

    /**
     * Get description for an option
     *
     * @param string $optionName Option name
     * @return string Description
     */
    private function getOptionDescription(string $optionName): string {
        $descriptions = [
            'blogname' => __('Site Title', 'wp-autoplugin'),
            'blogdescription' => __('Site Tagline/Description', 'wp-autoplugin'),
            'admin_email' => __('Administration Email Address', 'wp-autoplugin'),
            'permalink_structure' => __('URL structure for permalinks', 'wp-autoplugin'),
            'posts_per_page' => __('Number of posts to show per page', 'wp-autoplugin'),
            'date_format' => __('Date format for display', 'wp-autoplugin'),
            'time_format' => __('Time format for display', 'wp-autoplugin'),
            'start_of_week' => __('Week starts on (0=Sunday, 1=Monday, etc.)', 'wp-autoplugin'),
            'timezone_string' => __('Site timezone', 'wp-autoplugin'),
            'blog_public' => __('Search engine visibility (1=visible, 0=hidden)', 'wp-autoplugin'),
            'default_category' => __('Default post category ID', 'wp-autoplugin'),
            'default_post_format' => __('Default post format', 'wp-autoplugin'),
            'show_on_front' => __('Homepage displays (posts or page)', 'wp-autoplugin'),
            'page_on_front' => __('Page ID for homepage', 'wp-autoplugin'),
            'page_for_posts' => __('Page ID for blog posts', 'wp-autoplugin'),
            'default_ping_status' => __('Default ping status for new posts', 'wp-autoplugin'),
            'default_comment_status' => __('Default comment status for new posts', 'wp-autoplugin')
        ];

        return $descriptions[$optionName] ?? $optionName;
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
            case 'get':
                $optionName = $params['option_name'] ?? __('unspecified', 'wp-autoplugin');
                return sprintf(__('Will read the value of option: %s', 'wp-autoplugin'), $optionName);

            case 'set':
                $optionName = $params['option_name'] ?? __('unspecified', 'wp-autoplugin');
                $optionValue = $params['option_value'] ?? __('unspecified', 'wp-autoplugin');
                $currentValue = get_option($optionName);
                return sprintf(
                    __('Will change option "%1$s" from "%2$s" to "%3$s"', 'wp-autoplugin'),
                    $optionName,
                    is_string($currentValue) ? $currentValue : json_encode($currentValue),
                    is_string($optionValue) ? $optionValue : json_encode($optionValue)
                );

            case 'list':
                return __('Will list all accessible site options (read-only operation)', 'wp-autoplugin');

            default:
                return sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action);
        }
    }
}
