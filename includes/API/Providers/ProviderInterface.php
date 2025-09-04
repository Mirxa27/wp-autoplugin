<?php
/**
 * Provider Interface
 *
 * @package WP_Autoplugin\API\Providers
 * @since 2.0.0
 */

namespace WP_Autoplugin\API\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for AI API providers
 */
interface ProviderInterface {
    /**
     * Initialize the provider
     *
     * @return void
     */
    public function initialize(): void;

    /**
     * Check if provider has valid API key
     *
     * @return bool
     */
    public function hasValidApiKey(): bool;

    /**
     * Complete a prompt
     *
     * @param string $prompt The prompt to complete
     * @param array $options Additional options
     * @return string The completion response
     * @throws \Exception On API errors
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * Stream a completion
     *
     * @param string $prompt The prompt to complete
     * @param callable $onChunk Callback for each chunk
     * @param array $options Additional options
     * @return void
     * @throws \Exception On API errors
     */
    public function streamComplete(string $prompt, callable $onChunk, array $options = []): void;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get supported models
     *
     * @return array
     */
    public function getSupportedModels(): array;
}