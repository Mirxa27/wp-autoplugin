<?php
/**
 * API Manager - Handles AI API integrations
 *
 * @package WP_Autoplugin\API
 * @since 2.0.0
 */

namespace WP_Autoplugin\API;

use WP_Autoplugin\API\Providers\OpenAIProvider;
use WP_Autoplugin\API\Providers\AnthropicProvider;
use WP_Autoplugin\API\Providers\ProviderInterface;
use WP_Autoplugin\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Manager Class
 */
class ApiManager {
    /**
     * Available providers
     *
     * @var array
     */
    private array $providers = [];

    /**
     * Current provider
     *
     * @var ProviderInterface|null
     */
    private ?ProviderInterface $currentProvider = null;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Available models
     *
     * @var array
     */
    private array $models = [
        'openai' => [
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'o1' => 'o1',
            'o1-preview' => 'o1 Preview'
        ],
        'anthropic' => [
            'claude-3-opus' => 'Claude 3 Opus',
            'claude-3-sonnet' => 'Claude 3 Sonnet',
            'claude-3-haiku' => 'Claude 3 Haiku',
            'claude-2.1' => 'Claude 2.1'
        ]
    ];

    /**
     * Initialize API Manager
     */
    public function initialize(): void {
        $this->logger = new Logger();
        $this->registerProviders();
        $this->setCurrentProvider();
    }

    /**
     * Register available providers
     */
    private function registerProviders(): void {
        $this->providers['openai'] = new OpenAIProvider();
        $this->providers['anthropic'] = new AnthropicProvider();

        // Allow third-party providers
        $this->providers = apply_filters('wp_autoplugin_api_providers', $this->providers);
    }

    /**
     * Set current provider based on settings
     */
    private function setCurrentProvider(): void {
        $providerName = get_option('wp_autoplugin_api_provider', 'openai');
        
        if (isset($this->providers[$providerName])) {
            $this->currentProvider = $this->providers[$providerName];
            $this->currentProvider->initialize();
        } else {
            $this->logger->error("Invalid API provider: {$providerName}");
        }
    }

    /**
     * Check if valid API key exists
     */
    public function hasValidApiKey(): bool {
        return $this->currentProvider && $this->currentProvider->hasValidApiKey();
    }

    /**
     * Get available models
     */
    public function getAvailableModels(): array {
        $provider = get_option('wp_autoplugin_api_provider', 'openai');
        return $this->models[$provider] ?? [];
    }

    /**
     * Generate plugin plan
     */
    public function generatePlan(string $description, array $options = []): array {
        $this->validateProvider();

        try {
            $prompt = $this->buildPlanPrompt($description);
            $response = $this->currentProvider->complete($prompt, $options);
            
            return $this->parsePlanResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate plan: ' . $e->getMessage());
            throw new \Exception(__('Failed to generate plugin plan. Please try again.', 'wp-autoplugin'));
        }
    }

    /**
     * Generate plugin code
     */
    public function generateCode(array $plan, array $options = []): string {
        $this->validateProvider();

        try {
            $prompt = $this->buildCodePrompt($plan);
            $response = $this->currentProvider->complete($prompt, $options);
            
            return $this->sanitizeCode($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate code: ' . $e->getMessage());
            throw new \Exception(__('Failed to generate plugin code. Please try again.', 'wp-autoplugin'));
        }
    }

    /**
     * Fix plugin issues
     */
    public function fixPlugin(string $code, array $errors, array $options = []): string {
        $this->validateProvider();

        try {
            $prompt = $this->buildFixPrompt($code, $errors);
            $response = $this->currentProvider->complete($prompt, $options);
            
            return $this->sanitizeCode($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fix plugin: ' . $e->getMessage());
            throw new \Exception(__('Failed to fix plugin. Please try again.', 'wp-autoplugin'));
        }
    }

    /**
     * Extend plugin functionality
     */
    public function extendPlugin(string $code, string $requirements, array $options = []): string {
        $this->validateProvider();

        try {
            $prompt = $this->buildExtendPrompt($code, $requirements);
            $response = $this->currentProvider->complete($prompt, $options);
            
            return $this->sanitizeCode($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to extend plugin: ' . $e->getMessage());
            throw new \Exception(__('Failed to extend plugin. Please try again.', 'wp-autoplugin'));
        }
    }

    /**
     * Explain plugin code
     */
    public function explainPlugin(string $code, array $options = []): array {
        $this->validateProvider();

        try {
            $prompt = $this->buildExplainPrompt($code, $options['detail_level'] ?? 'medium');
            $response = $this->currentProvider->complete($prompt, $options);
            
            return $this->parseExplanationResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to explain plugin: ' . $e->getMessage());
            throw new \Exception(__('Failed to explain plugin. Please try again.', 'wp-autoplugin'));
        }
    }

    /**
     * Validate provider is set
     */
    private function validateProvider(): void {
        if (!$this->currentProvider) {
            throw new \Exception(__('No API provider configured', 'wp-autoplugin'));
        }

        if (!$this->currentProvider->hasValidApiKey()) {
            throw new \Exception(__('Invalid or missing API key', 'wp-autoplugin'));
        }
    }

    /**
     * Build plan generation prompt
     */
    private function buildPlanPrompt(string $description): string {
        return <<<PROMPT
You are an expert WordPress plugin developer. Create a detailed plan for a WordPress plugin based on the following description:

{$description}

Generate a comprehensive plan in JSON format with the following structure:
{
    "plugin_name": "Human-readable plugin name",
    "plugin_slug": "plugin-slug",
    "description": "Brief plugin description",
    "features": ["Feature 1", "Feature 2", ...],
    "technical_requirements": {
        "php_version": "7.4",
        "wp_version": "5.0",
        "dependencies": []
    },
    "architecture": {
        "main_classes": ["Class names and purposes"],
        "database_tables": ["Table descriptions if needed"],
        "hooks_used": ["WordPress hooks to implement"]
    },
    "admin_interface": "Description of admin pages/settings",
    "frontend_output": "Description of frontend features if any",
    "security_considerations": ["Security measures to implement"]
}
PROMPT;
    }

    /**
     * Build code generation prompt
     */
    private function buildCodePrompt(array $plan): string {
        $planJson = json_encode($plan, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
You are an expert WordPress plugin developer. Generate complete, production-ready WordPress plugin code based on this plan:

{$planJson}

Requirements:
1. Follow WordPress coding standards and best practices
2. Include proper security measures (nonces, capability checks, data sanitization)
3. Add inline documentation and PHPDoc blocks
4. Make the code modular and maintainable
5. Include error handling and validation
6. Add necessary hooks and filters
7. Implement internationalization (i18n)
8. Include a proper plugin header

Generate ONLY the PHP code, starting with <?php and the plugin header.
PROMPT;
    }

    /**
     * Build fix prompt
     */
    private function buildFixPrompt(string $code, array $errors): string {
        $errorList = implode("\n", array_map(function($error) {
            return "- {$error['message']} (Line: {$error['line']})";
        }, $errors));

        return <<<PROMPT
You are an expert WordPress plugin developer. Fix the following issues in this WordPress plugin code:

Errors found:
{$errorList}

Current code:
{$code}

Fix all the errors while maintaining the plugin's functionality. Return the complete fixed code.
PROMPT;
    }

    /**
     * Build extend prompt
     */
    private function buildExtendPrompt(string $code, string $requirements): string {
        return <<<PROMPT
You are an expert WordPress plugin developer. Extend this WordPress plugin with the following new features:

New requirements:
{$requirements}

Current code:
{$code}

Add the requested features while:
1. Maintaining backward compatibility
2. Following the existing code structure and style
3. Adding proper documentation for new features
4. Implementing security best practices
5. Keeping the code modular

Return the complete extended plugin code.
PROMPT;
    }

    /**
     * Build explanation prompt
     */
    private function buildExplainPrompt(string $code, string $detailLevel): string {
        $detailInstructions = match($detailLevel) {
            'basic' => 'Provide a high-level overview suitable for non-developers',
            'detailed' => 'Provide detailed technical explanation with code examples',
            default => 'Provide a balanced explanation for developers'
        };

        return <<<PROMPT
You are an expert WordPress plugin developer. Analyze and explain this WordPress plugin code:

{$code}

{$detailInstructions}

Structure your response as JSON:
{
    "summary": "Brief overview of what the plugin does",
    "main_functionality": ["Key features"],
    "technical_details": {
        "hooks_used": ["WordPress hooks and their purposes"],
        "classes": ["Class names and responsibilities"],
        "database": "Database usage if any",
        "ajax": "AJAX functionality if any"
    },
    "security_analysis": ["Security measures implemented"],
    "potential_improvements": ["Suggestions for improvement"],
    "dependencies": ["External dependencies"]
}
PROMPT;
    }

    /**
     * Parse plan response
     */
    private function parsePlanResponse(string $response): array {
        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = $matches[0];
            $plan = json_decode($json, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $plan;
            }
        }

        throw new \Exception(__('Failed to parse plan response', 'wp-autoplugin'));
    }

    /**
     * Parse explanation response
     */
    private function parseExplanationResponse(string $response): array {
        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = $matches[0];
            $explanation = json_decode($json, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $explanation;
            }
        }

        // Fallback to text response
        return [
            'summary' => $response,
            'main_functionality' => [],
            'technical_details' => [],
            'security_analysis' => [],
            'potential_improvements' => []
        ];
    }

    /**
     * Sanitize generated code
     */
    private function sanitizeCode(string $code): string {
        // Remove any markdown code blocks
        $code = preg_replace('/```php\s*\n?/', '', $code);
        $code = preg_replace('/```\s*$/', '', $code);
        
        // Ensure code starts with <?php
        if (!str_starts_with(trim($code), '<?php')) {
            $code = "<?php\n" . $code;
        }

        // Basic validation
        if (strlen($code) < 100) {
            throw new \Exception(__('Generated code appears to be incomplete', 'wp-autoplugin'));
        }

        return trim($code);
    }
}