<?php
/**
 * Anthropic Provider Implementation
 *
 * @package WP_Autoplugin\API\Providers
 * @since 2.0.0
 */

namespace WP_Autoplugin\API\Providers;

use WP_Autoplugin\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Anthropic API Provider
 */
class AnthropicProvider implements ProviderInterface {
    /**
     * API endpoint
     *
     * @var string
     */
    private string $apiEndpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * API key
     *
     * @var string
     */
    private string $apiKey = '';

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Supported models
     *
     * @var array
     */
    private array $supportedModels = [
        'claude-3-opus-20240229' => ['name' => 'Claude 3 Opus', 'max_tokens' => 200000],
        'claude-3-sonnet-20240229' => ['name' => 'Claude 3 Sonnet', 'max_tokens' => 200000],
        'claude-3-haiku-20240307' => ['name' => 'Claude 3 Haiku', 'max_tokens' => 200000],
        'claude-3-5-sonnet-latest' => ['name' => 'Claude 3.5 Sonnet', 'max_tokens' => 200000],
        'claude-2.1' => ['name' => 'Claude 2.1', 'max_tokens' => 100000]
    ];

    /**
     * Initialize provider
     */
    public function initialize(): void {
        $this->logger = new Logger();
        $this->apiKey = get_option('wp_autoplugin_anthropic_api_key', '');
    }

    /**
     * Check if has valid API key
     */
    public function hasValidApiKey(): bool {
        return !empty($this->apiKey);
    }

    /**
     * Complete a prompt
     */
    public function complete(string $prompt, array $options = []): string {
        if (!$this->hasValidApiKey()) {
            throw new \Exception(__('Anthropic API key not configured', 'wp-autoplugin'));
        }

        $model = $options['model'] ?? get_option('wp_autoplugin_model', 'claude-3-5-sonnet-latest');
        $maxTokens = $options['max_tokens'] ?? 4000;

        $requestData = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        // Add system message if provided
        if (!empty($options['system'])) {
            $requestData['system'] = $options['system'];
        }

        $response = wp_remote_post($this->apiEndpoint, [
            'timeout' => 120,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($requestData)
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Anthropic API request failed: ' . $response->get_error_message());
            throw new \Exception(__('Failed to connect to Anthropic API', 'wp-autoplugin'));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode !== 200) {
            $errorData = json_decode($body, true);
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
            $this->logger->error("Anthropic API error ({$statusCode}): {$errorMessage}");
            
            if ($statusCode === 401) {
                throw new \Exception(__('Invalid Anthropic API key', 'wp-autoplugin'));
            } elseif ($statusCode === 429) {
                throw new \Exception(__('Anthropic API rate limit exceeded. Please try again later.', 'wp-autoplugin'));
            } else {
                throw new \Exception(sprintf(__('Anthropic API error: %s', 'wp-autoplugin'), $errorMessage));
            }
        }

        $data = json_decode($body, true);
        if (!isset($data['content'][0]['text'])) {
            throw new \Exception(__('Invalid response from Anthropic API', 'wp-autoplugin'));
        }

        return $data['content'][0]['text'];
    }

    /**
     * Stream a completion
     */
    public function streamComplete(string $prompt, callable $onChunk, array $options = []): void {
        if (!$this->hasValidApiKey()) {
            throw new \Exception(__('Anthropic API key not configured', 'wp-autoplugin'));
        }

        $model = $options['model'] ?? get_option('wp_autoplugin_model', 'claude-3-5-sonnet-latest');

        $requestData = [
            'model' => $model,
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'stream' => true
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $this->apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($onChunk) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        continue;
                    }
                    $decoded = json_decode($json, true);
                    if (isset($decoded['delta']['text'])) {
                        $onChunk($decoded['delta']['text']);
                    }
                }
            }
            return strlen($data);
        });

        curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception(sprintf(__('Stream request failed: %s', 'wp-autoplugin'), $error));
        }
        
        curl_close($ch);
    }

    /**
     * Get provider name
     */
    public function getName(): string {
        return 'Anthropic';
    }

    /**
     * Get supported models
     */
    public function getSupportedModels(): array {
        return array_map(function($model) {
            return $model['name'];
        }, $this->supportedModels);
    }
}
