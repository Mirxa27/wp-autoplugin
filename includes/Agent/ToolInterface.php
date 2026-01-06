<?php
/**
 * Tool Interface - Defines the contract for all agent tools
 *
 * @package WP_Autoplugin\Agent
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for Agent Tools
 *
 * Tools are the actions that the AI agent can execute on the WordPress site.
 * Each tool maps to one or more WordPress core functions.
 */
interface ToolInterface {
    /**
     * Get the unique name of the tool
     *
     * @return string Tool name (e.g., 'create_post', 'update_option')
     */
    public function getName(): string;

    /**
     * Get a human-readable description of what the tool does
     *
     * @return string Tool description
     */
    public function getDescription(): string;

    /**
     * Get the JSON schema for the tool's parameters
     *
     * This schema is used to validate inputs and is sent to the LLM
     * for function calling.
     *
     * @return array JSON Schema for parameters
     */
    public function getParameterSchema(): array;

    /**
     * Execute the tool with given parameters
     *
     * @param array $params Tool parameters
     * @return array Result with 'success' boolean and 'data' or 'error' keys
     */
    public function execute(array $params): array;

    /**
     * Perform a dry run - describe what would happen without executing
     *
     * @param array $params Tool parameters
     * @return string Human-readable description of what would happen
     */
    public function dryRun(array $params): string;

    /**
     * Check if current user has permission to use this tool
     *
     * @return bool Whether user can use this tool
     */
    public function userCanExecute(): bool;

    /**
     * Get required WordPress capability for this tool
     *
     * @return string WordPress capability required
     */
    public function getRequiredCapability(): string;
}
