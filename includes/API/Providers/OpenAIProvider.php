<?php
/**
 * OpenAI Provider Implementation
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
 * OpenAI API Provider
 */
class OpenAIProvider implements ProviderInterface {
    /**
     * API endpoint
     *
     * @var string
     */
    private string $apiEndpoint = 'https://api.openai.com/v1/chat/completions';

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
        'gpt-4-turbo' => ['name' => 'GPT-4 Turbo', 'max_tokens' => 128000],
        'gpt-4o' => ['name' => 'GPT-4o', 'max_tokens' => 128000],
        'gpt-4o-mini' => ['name' => 'GPT-4o Mini', 'max_tokens' => 128000],
        'gpt-3.5-turbo' => ['name' => 'GPT-3.5 Turbo', 'max_tokens' => 16384],
        'o1' => ['name' => 'o1', 'max_tokens' => 128000],
        'o1-preview' => ['name' => 'o1 Preview', 'max_tokens' => 128000]
    ];

    /**
     * Initialize provider
     */
    public function initialize(): void {
        $this->logger = new Logger();
        $this->apiKey = get_option('wp_autoplugin_openai_api_key', '');
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
            throw new \Exception(__('OpenAI API key not configured', 'wp-autoplugin'));
        }

        $model = $options['model'] ?? get_option('wp_autoplugin_model', 'gpt-4o');
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 4000;

        $requestData = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert WordPress plugin developer with deep knowledge of WordPress best practices, security, and modern PHP development.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        $response = wp_remote_post($this->apiEndpoint, [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($requestData)
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('OpenAI API request failed: ' . $response->get_error_message());
            throw new \Exception(__('Failed to connect to OpenAI API', 'wp-autoplugin'));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode !== 200) {
            $errorData = json_decode($body, true);
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
            $this->logger->error("OpenAI API error ({$statusCode}): {$errorMessage}");
            
            if ($statusCode === 401) {
                throw new \Exception(__('Invalid OpenAI API key', 'wp-autoplugin'));
            } elseif ($statusCode === 429) {
                throw new \Exception(__('OpenAI API rate limit exceeded. Please try again later.', 'wp-autoplugin'));
            } else {
                throw new \Exception(sprintf(__('OpenAI API error: %s', 'wp-autoplugin'), $errorMessage));
            }
        }

        $data = json_decode($body, true);
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception(__('Invalid response from OpenAI API', 'wp-autoplugin'));
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Stream a completion
     */
    public function streamComplete(string $prompt, callable $onChunk, array $options = []): void {
        if (!$this->hasValidApiKey()) {
            throw new \Exception(__('OpenAI API key not configured', 'wp-autoplugin'));
        }

        $model = $options['model'] ?? get_option('wp_autoplugin_model', 'gpt-4o');
        $temperature = $options['temperature'] ?? 0.7;

        $requestData = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert WordPress plugin developer.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'stream' => true
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
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
                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        $onChunk($decoded['choices'][0]['delta']['content']);
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
        return 'OpenAI';
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