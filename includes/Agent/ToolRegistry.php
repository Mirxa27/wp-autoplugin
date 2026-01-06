<?php
/**
 * Tool Registry - Central registry for all agent tools
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
 * Tool Registry Class
 *
 * Manages registration and execution of agent tools.
 * This is the bridge between LLM JSON output and WordPress core functions.
 */
class ToolRegistry {
    /**
     * Registered tools
     *
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Register a tool
     *
     * @param ToolInterface $tool Tool to register
     * @return self For chaining
     */
    public function register(ToolInterface $tool): self {
        $name = $tool->getName();

        if (isset($this->tools[$name])) {
            $this->logger->warning(
                sprintf('Tool %s is already registered and will be overwritten', $name)
            );
        }

        $this->tools[$name] = $tool;
        $this->logger->debug(sprintf('Registered tool: %s', $name));

        return $this;
    }

    /**
     * Unregister a tool
     *
     * @param string $name Tool name
     * @return bool Whether the tool was unregistered
     */
    public function unregister(string $name): bool {
        if (isset($this->tools[$name])) {
            unset($this->tools[$name]);
            return true;
        }
        return false;
    }

    /**
     * Check if a tool is registered
     *
     * @param string $name Tool name
     * @return bool
     */
    public function has(string $name): bool {
        return isset($this->tools[$name]);
    }

    /**
     * Get a tool by name
     *
     * @param string $name Tool name
     * @return ToolInterface|null
     */
    public function get(string $name): ?ToolInterface {
        return $this->tools[$name] ?? null;
    }

    /**
     * Get all registered tools
     *
     * @return array<string, ToolInterface>
     */
    public function all(): array {
        return $this->tools;
    }

    /**
     * Get tools available to current user
     *
     * @return array<string, ToolInterface>
     */
    public function getAvailableTools(): array {
        return array_filter($this->tools, function (ToolInterface $tool) {
            return $tool->userCanExecute();
        });
    }

    /**
     * Execute a tool by name
     *
     * @param string $name Tool name
     * @param array $params Tool parameters
     * @param bool $dryRun Whether to perform a dry run
     * @return array Execution result
     */
    public function execute(string $name, array $params = [], bool $dryRun = false): array {
        // Check if tool exists
        if (!$this->has($name)) {
            return [
                'success' => false,
                'error' => sprintf(__('Tool not found: %s', 'wp-autoplugin'), $name),
                'code' => 'tool_not_found'
            ];
        }

        $tool = $this->get($name);

        // Check permissions
        if (!$tool->userCanExecute()) {
            $this->logger->warning(
                sprintf('User attempted to execute tool without permission: %s', $name),
                ['user_id' => get_current_user_id()]
            );
            return [
                'success' => false,
                'error' => __('You do not have permission to execute this action', 'wp-autoplugin'),
                'code' => 'permission_denied'
            ];
        }

        // Dry run mode
        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'description' => $tool->dryRun($params),
                'tool' => $name,
                'params' => $params
            ];
        }

        // Execute the tool
        try {
            $result = $tool->execute($params);

            // Log execution
            $this->logger->info(
                sprintf('Tool executed: %s', $name),
                [
                    'success' => $result['success'] ?? false,
                    'user_id' => get_current_user_id()
                ]
            );

            // Allow filtering of results
            return apply_filters('wp_autoplugin_tool_result', $result, $name, $params);

        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Tool execution failed: %s - %s', $name, $e->getMessage()),
                ['params' => $params]
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'execution_error'
            ];
        }
    }

    /**
     * Execute multiple tools in sequence (batch execution)
     *
     * @param array $actions Array of ['tool' => string, 'params' => array]
     * @param bool $dryRun Whether to perform a dry run
     * @param bool $stopOnError Whether to stop execution on first error
     * @return array Results for each action
     */
    public function executeBatch(array $actions, bool $dryRun = false, bool $stopOnError = true): array {
        $results = [];

        foreach ($actions as $index => $action) {
            $toolName = $action['tool'] ?? '';
            $params = $action['params'] ?? [];

            $result = $this->execute($toolName, $params, $dryRun);
            $result['index'] = $index;
            $results[] = $result;

            if ($stopOnError && !($result['success'] ?? false)) {
                break;
            }
        }

        return [
            'success' => empty(array_filter($results, fn($r) => !($r['success'] ?? false))),
            'results' => $results,
            'total' => count($actions),
            'executed' => count($results)
        ];
    }

    /**
     * Get tools schema for LLM function calling
     *
     * Returns schema in OpenAI function calling format.
     *
     * @return array Tools schema
     */
    public function getToolsSchema(): array {
        $tools = [];

        foreach ($this->getAvailableTools() as $name => $tool) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameterSchema()
                ]
            ];
        }

        return $tools;
    }

    /**
     * Get tools list with descriptions (for context)
     *
     * @return array Tool names and descriptions
     */
    public function getToolsList(): array {
        $list = [];

        foreach ($this->getAvailableTools() as $name => $tool) {
            $list[$name] = [
                'description' => $tool->getDescription(),
                'capability' => $tool->getRequiredCapability()
            ];
        }

        return $list;
    }

    /**
     * Parse LLM tool call response and execute
     *
     * @param array $toolCall Tool call from LLM response
     * @param bool $dryRun Whether to perform a dry run
     * @return array Execution result
     */
    public function handleToolCall(array $toolCall, bool $dryRun = false): array {
        $name = $toolCall['name'] ?? $toolCall['function']['name'] ?? '';
        $arguments = $toolCall['arguments'] ?? $toolCall['function']['arguments'] ?? '{}';

        // Parse arguments if JSON string
        if (is_string($arguments)) {
            $arguments = json_decode($arguments, true) ?: [];
        }

        return $this->execute($name, $arguments, $dryRun);
    }
}
