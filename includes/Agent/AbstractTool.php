<?php
/**
 * Abstract Tool Base Class
 *
 * @package WP_Autoplugin\Agent
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent;

use WP_Autoplugin\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for Agent Tools
 *
 * Provides common functionality for all tools.
 */
abstract class AbstractTool implements ToolInterface {
    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Default required capability
     *
     * @var string
     */
    protected string $requiredCapability = 'manage_options';

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Check if current user has permission to use this tool
     *
     * @return bool
     */
    public function userCanExecute(): bool {
        return current_user_can($this->getRequiredCapability());
    }

    /**
     * Get required WordPress capability
     *
     * @return string
     */
    public function getRequiredCapability(): string {
        return $this->requiredCapability;
    }

    /**
     * Validate parameters against schema
     *
     * @param array $params Parameters to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    protected function validateParams(array $params): array {
        $schema = $this->getParameterSchema();
        $errors = [];
        $required = $schema['required'] ?? [];

        // Check required parameters
        foreach ($required as $requiredParam) {
            if (!isset($params[$requiredParam]) || $params[$requiredParam] === '') {
                $errors[] = sprintf(
                    __('Missing required parameter: %s', 'wp-autoplugin'),
                    $requiredParam
                );
            }
        }

        // Validate parameter types if properties are defined
        if (isset($schema['properties'])) {
            foreach ($params as $key => $value) {
                if (!isset($schema['properties'][$key])) {
                    continue; // Allow additional properties
                }

                $propSchema = $schema['properties'][$key];
                $typeError = $this->validateType($value, $propSchema, $key);
                if ($typeError) {
                    $errors[] = $typeError;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate a value against its type schema
     *
     * @param mixed $value Value to validate
     * @param array $schema Type schema
     * @param string $key Parameter key
     * @return string|null Error message or null if valid
     */
    protected function validateType($value, array $schema, string $key): ?string {
        $type = $schema['type'] ?? 'string';

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    return sprintf(__('Parameter %s must be a string', 'wp-autoplugin'), $key);
                }
                if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
                    return sprintf(
                        __('Parameter %s must be one of: %s', 'wp-autoplugin'),
                        $key,
                        implode(', ', $schema['enum'])
                    );
                }
                break;

            case 'integer':
            case 'number':
                if (!is_numeric($value)) {
                    return sprintf(__('Parameter %s must be a number', 'wp-autoplugin'), $key);
                }
                break;

            case 'boolean':
                if (!is_bool($value) && $value !== 'true' && $value !== 'false' && $value !== 0 && $value !== 1) {
                    return sprintf(__('Parameter %s must be a boolean', 'wp-autoplugin'), $key);
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    return sprintf(__('Parameter %s must be an array', 'wp-autoplugin'), $key);
                }
                break;

            case 'object':
                if (!is_array($value) && !is_object($value)) {
                    return sprintf(__('Parameter %s must be an object', 'wp-autoplugin'), $key);
                }
                break;
        }

        return null;
    }

    /**
     * Sanitize string input
     *
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    protected function sanitizeString(string $value): string {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize HTML content
     *
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    protected function sanitizeContent(string $content): string {
        return wp_kses_post($content);
    }

    /**
     * Log tool execution
     *
     * @param string $action Action performed
     * @param array $params Parameters used
     * @param array $result Result of execution
     */
    protected function logExecution(string $action, array $params, array $result): void {
        $this->logger->info(
            sprintf('Agent tool executed: %s', $action),
            [
                'tool' => $this->getName(),
                'params' => $this->sanitizeParamsForLog($params),
                'success' => $result['success'] ?? false,
                'user_id' => get_current_user_id()
            ]
        );
    }

    /**
     * Sanitize parameters for logging (remove sensitive data)
     *
     * @param array $params Parameters to sanitize
     * @return array Sanitized parameters
     */
    protected function sanitizeParamsForLog(array $params): array {
        $sensitiveKeys = ['password', 'api_key', 'secret', 'token'];
        $sanitized = [];

        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParamsForLog($value);
            } elseif (is_string($value) && strlen($value) > 500) {
                $sanitized[$key] = substr($value, 0, 500) . '...[truncated]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Create success response
     *
     * @param mixed $data Response data
     * @param string $message Optional success message
     * @return array
     */
    protected function success($data, string $message = ''): array {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message ?: __('Action completed successfully', 'wp-autoplugin')
        ];
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @param string $code Error code
     * @return array
     */
    protected function error(string $message, string $code = 'error'): array {
        return [
            'success' => false,
            'error' => $message,
            'code' => $code
        ];
    }
}
