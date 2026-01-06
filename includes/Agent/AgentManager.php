<?php
/**
 * Agent Manager - Main orchestrator for the autonomous agent
 *
 * @package WP_Autoplugin\Agent
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent;

use WP_Autoplugin\Agent\Tools\ContentTool;
use WP_Autoplugin\Agent\Tools\OptionsTool;
use WP_Autoplugin\Agent\Tools\PluginTool;
use WP_Autoplugin\Agent\Tools\BlockTool;
use WP_Autoplugin\Agent\Tools\IntegrationTool;
use WP_Autoplugin\Agent\Tools\ThemeTool;
use WP_Autoplugin\API\ApiManager;
use WP_Autoplugin\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Manager Class
 *
 * The main autonomous agent that can execute actions on WordPress.
 * Reuses existing LLMConnector classes and implements function calling.
 */
class AgentManager {
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private ApiManager $apiManager;

    /**
     * Tool Registry instance
     *
     * @var ToolRegistry
     */
    private ToolRegistry $toolRegistry;

    /**
     * Context Builder instance
     *
     * @var ContextBuilder
     */
    private ContextBuilder $contextBuilder;

    /**
     * Conversation Manager instance
     *
     * @var ConversationManager
     */
    private ConversationManager $conversationManager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Dry run mode
     *
     * @var bool
     */
    private bool $dryRunMode = false;

    /**
     * Pending actions awaiting confirmation
     *
     * @var array
     */
    private array $pendingActions = [];

    /**
     * System prompt for the agent
     *
     * @var string
     */
    private string $systemPrompt;

    /**
     * Constructor
     *
     * @param ApiManager $apiManager API Manager instance
     */
    public function __construct(ApiManager $apiManager) {
        $this->apiManager = $apiManager;
        $this->logger = new Logger();
        $this->toolRegistry = new ToolRegistry();
        $this->contextBuilder = new ContextBuilder();
        $this->conversationManager = new ConversationManager();
        
        $this->registerDefaultTools();
        $this->buildSystemPrompt();
    }

    /**
     * Initialize the agent
     *
     * @return void
     */
    public function initialize(): void {
        // Allow extensions to register additional tools
        do_action('wp_autoplugin_agent_init', $this);
        
        $this->logger->info('Agent Manager initialized');
    }

    /**
     * Register default tools
     *
     * @return void
     */
    private function registerDefaultTools(): void {
        $this->toolRegistry->register(new ContentTool());
        $this->toolRegistry->register(new OptionsTool());
        $this->toolRegistry->register(new PluginTool());
        $this->toolRegistry->register(new BlockTool());
        $this->toolRegistry->register(new IntegrationTool());
        $this->toolRegistry->register(new ThemeTool());
        
        // Allow adding custom tools
        $this->toolRegistry = apply_filters('wp_autoplugin_agent_tools', $this->toolRegistry);
    }

    /**
     * Build the system prompt for the agent
     *
     * @return void
     */
    private function buildSystemPrompt(): void {
        $toolsList = $this->toolRegistry->getToolsList();
        $toolsDescription = '';
        
        foreach ($toolsList as $name => $info) {
            $toolsDescription .= "- {$name}: {$info['description']}\n";
        }
        
        $this->systemPrompt = <<<PROMPT
You are an intelligent WordPress site assistant agent. Your role is to help users manage and build their WordPress website through conversation.

You have access to the following tools to execute actions on the WordPress site:
{$toolsDescription}

IMPORTANT GUIDELINES:
1. Always be helpful and explain what you're doing
2. When the user asks to perform an action, use the appropriate tool
3. For destructive actions (delete, significant changes), always confirm with the user first
4. Never hallucinate plugin names - always verify through the WordPress.org API using the plugins tool
5. When building a site, create a plan first and execute step by step
6. Provide feedback after each action so the user knows what happened
7. If an action fails, explain why and suggest alternatives

PLUGIN COMPATIBILITY:
- Use the 'integrations' tool to detect popular plugins like WooCommerce, Contact Form 7, Yoast SEO, Elementor, and ACF
- When recommending plugins, always use the 'plugins' tool with action 'recommend' to verify they exist
- Be aware of installed plugins and suggest compatible solutions
- For ecommerce sites, check if WooCommerce is active before suggesting product-related actions

THEME AWARENESS:
- Use the 'theme' tool to get current theme information and capabilities
- Work with any theme including block themes, classic themes, and popular themes like Astra, GeneratePress, Kadence
- Manage navigation menus and widget areas through the theme tool

RESPONSE FORMAT:
When you need to execute an action, respond with a JSON object containing your tool calls:
```json
{
    "thinking": "Your reasoning about what to do",
    "tool_calls": [
        {
            "tool": "tool_name",
            "params": { "param1": "value1" }
        }
    ],
    "message": "A human-readable message for the user"
}
```

For regular conversation without tool calls, just respond naturally.

When creating site plans, structure them clearly:
1. List pages to create
2. List content/blocks needed
3. List plugins to recommend (verify they exist first)
4. Create navigation menus as needed
5. Execute step by step with user confirmation
PROMPT;
    }

    /**
     * Process a user message and generate a response
     *
     * @param string $message User message
     * @param string|null $sessionId Conversation session ID
     * @return array Response with message and any action results
     */
    public function chat(string $message, ?string $sessionId = null): array {
        // Get or create session
        $sessionId = $this->conversationManager->getSession($sessionId);
        
        // Add user message to history
        $this->conversationManager->addMessage('user', $message);
        
        // Build context
        $context = $this->contextBuilder->buildContextString();
        
        // Get conversation history
        $history = $this->conversationManager->getMessagesForLLM();
        
        // Build the full prompt
        $fullPrompt = $this->buildPrompt($context, $history, $message);
        
        try {
            // Send to LLM
            $response = $this->sendToLLM($fullPrompt);
            
            // Parse the response
            $parsed = $this->parseResponse($response);
            
            // Execute tool calls if any
            $results = [];
            if (!empty($parsed['tool_calls'])) {
                $results = $this->executeToolCalls($parsed['tool_calls']);
            }
            
            // Build assistant response
            $assistantMessage = $parsed['message'] ?? $response;
            if (!empty($results)) {
                $assistantMessage .= "\n\n" . $this->formatResults($results);
            }
            
            // Add assistant message to history
            $this->conversationManager->addMessage('assistant', $assistantMessage, [
                'tool_calls' => $parsed['tool_calls'] ?? [],
                'results' => $results
            ]);
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'message' => $assistantMessage,
                'thinking' => $parsed['thinking'] ?? null,
                'tool_calls' => $parsed['tool_calls'] ?? [],
                'results' => $results,
                'dry_run' => $this->dryRunMode
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Agent chat error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'session_id' => $sessionId,
                'message' => sprintf(
                    __('I encountered an error: %s. Please try again or rephrase your request.', 'wp-autoplugin'),
                    $e->getMessage()
                ),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build the full prompt for the LLM
     *
     * @param string $context Site context
     * @param array $history Conversation history
     * @param string $message Current message
     * @return string Full prompt
     */
    private function buildPrompt(string $context, array $history, string $message): string {
        $prompt = $this->systemPrompt . "\n\n";
        $prompt .= $context . "\n\n";
        
        // Add conversation history (last 10 messages)
        if (!empty($history)) {
            $prompt .= "=== CONVERSATION HISTORY ===\n";
            $recentHistory = array_slice($history, -10);
            foreach ($recentHistory as $msg) {
                $role = ucfirst($msg['role']);
                $prompt .= "{$role}: {$msg['content']}\n\n";
            }
        }
        
        $prompt .= "User: {$message}\n\nAssistant:";
        
        return $prompt;
    }

    /**
     * Send prompt to LLM using existing API Manager
     *
     * @param string $prompt Full prompt
     * @return string LLM response
     */
    private function sendToLLM(string $prompt): string {
        // Use the existing generatePlan method structure but with our prompt
        // This reuses the existing LLM connector infrastructure
        
        $model = get_option('wp_autoplugin_model', 'gpt-4o');
        $options = [
            'model' => $model,
            'temperature' => 0.7,
            'max_tokens' => 4000
        ];
        
        // Get the current API provider
        $provider = get_option('wp_autoplugin_api_provider', 'openai');
        
        // Build a custom request to the provider
        return $this->makeApiRequest($prompt, $options);
    }

    /**
     * Make API request to the configured provider
     *
     * @param string $prompt Prompt to send
     * @param array $options Request options
     * @return string Response
     */
    private function makeApiRequest(string $prompt, array $options): string {
        $provider = get_option('wp_autoplugin_api_provider', 'openai');
        $model = $options['model'] ?? 'gpt-4o';
        
        $apiKey = '';
        $endpoint = '';
        
        switch ($provider) {
            case 'openai':
                $apiKey = get_option('wp_autoplugin_openai_api_key', '');
                $endpoint = 'https://api.openai.com/v1/chat/completions';
                break;
            case 'anthropic':
                $apiKey = get_option('wp_autoplugin_anthropic_api_key', '');
                $endpoint = 'https://api.anthropic.com/v1/messages';
                break;
            default:
                throw new \Exception(__('Unsupported API provider', 'wp-autoplugin'));
        }
        
        if (empty($apiKey)) {
            throw new \Exception(__('API key not configured', 'wp-autoplugin'));
        }
        
        // Build request for OpenAI-compatible API
        if ($provider === 'openai') {
            $requestData = [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 4000
            ];
            
            $response = wp_remote_post($endpoint, [
                'timeout' => 120,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($requestData)
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                throw new \Exception($body['error']['message'] ?? 'API error');
            }
            
            return $body['choices'][0]['message']['content'] ?? '';
        }
        
        // Build request for Anthropic
        if ($provider === 'anthropic') {
            $requestData = [
                'model' => $model,
                'max_tokens' => $options['max_tokens'] ?? 4000,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ];
            
            $response = wp_remote_post($endpoint, [
                'timeout' => 120,
                'headers' => [
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01'
                ],
                'body' => json_encode($requestData)
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                throw new \Exception($body['error']['message'] ?? 'API error');
            }
            
            return $body['content'][0]['text'] ?? '';
        }
        
        throw new \Exception(__('Unsupported API provider', 'wp-autoplugin'));
    }

    /**
     * Parse LLM response to extract tool calls
     *
     * @param string $response LLM response
     * @return array Parsed response
     */
    private function parseResponse(string $response): array {
        // Try to extract JSON from the response
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $json = json_decode($matches[1], true);
            if ($json !== null) {
                return $json;
            }
        }
        
        // Try direct JSON parsing
        $json = json_decode($response, true);
        if ($json !== null && (isset($json['tool_calls']) || isset($json['message']))) {
            return $json;
        }
        
        // Return as plain message
        return [
            'message' => $response,
            'tool_calls' => []
        ];
    }

    /**
     * Execute tool calls
     *
     * @param array $toolCalls Tool calls to execute
     * @return array Results
     */
    private function executeToolCalls(array $toolCalls): array {
        $results = [];
        
        foreach ($toolCalls as $call) {
            $toolName = $call['tool'] ?? '';
            $params = $call['params'] ?? [];
            
            if (empty($toolName)) {
                continue;
            }
            
            // Check if in dry run mode
            if ($this->dryRunMode) {
                $results[] = $this->toolRegistry->execute($toolName, $params, true);
            } else {
                $results[] = $this->toolRegistry->execute($toolName, $params);
            }
        }
        
        return $results;
    }

    /**
     * Format results for display
     *
     * @param array $results Execution results
     * @return string Formatted results
     */
    private function formatResults(array $results): string {
        $output = '';
        
        foreach ($results as $result) {
            if ($result['success'] ?? false) {
                $message = $result['message'] ?? __('Action completed', 'wp-autoplugin');
                $output .= "✅ {$message}\n";
                
                if (isset($result['data'])) {
                    if (isset($result['data']['post_id'])) {
                        $output .= "   Post ID: {$result['data']['post_id']}\n";
                    }
                    if (isset($result['data']['edit_url'])) {
                        $output .= "   Edit: {$result['data']['edit_url']}\n";
                    }
                }
            } else {
                $error = $result['error'] ?? __('Unknown error', 'wp-autoplugin');
                $output .= "❌ {$error}\n";
            }
        }
        
        return $output;
    }

    /**
     * Enable dry run mode
     *
     * @param bool $enable Enable or disable
     * @return self
     */
    public function setDryRunMode(bool $enable): self {
        $this->dryRunMode = $enable;
        return $this;
    }

    /**
     * Check if dry run mode is enabled
     *
     * @return bool
     */
    public function isDryRunMode(): bool {
        return $this->dryRunMode;
    }

    /**
     * Plan site creation based on high-level description
     *
     * @param string $description Site description (e.g., "Build a Real Estate site")
     * @return array Plan with pages, content, and plugin recommendations
     */
    public function planSite(string $description): array {
        $planPrompt = <<<PROMPT
Based on this site description, create a detailed implementation plan:

Description: {$description}

Create a JSON response with:
1. "pages": Array of pages to create with title, slug, and content description
2. "plugins": Array of plugins to recommend (I will verify these exist)
3. "settings": Any WordPress settings to configure
4. "content_blocks": Suggested Gutenberg block patterns for each page
5. "execution_steps": Ordered steps to build the site

Format your response as valid JSON only.
PROMPT;

        try {
            $response = $this->sendToLLM($planPrompt);
            $plan = json_decode($response, true);
            
            if (!$plan) {
                // Try to extract JSON from response
                if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
                    $plan = json_decode($matches[1], true);
                }
            }
            
            if (!$plan) {
                throw new \Exception(__('Failed to parse plan', 'wp-autoplugin'));
            }
            
            // Verify plugins exist
            if (!empty($plan['plugins'])) {
                $pluginTool = new PluginTool();
                $verifiedPlugins = [];
                
                foreach ($plan['plugins'] as $plugin) {
                    $slug = is_array($plugin) ? ($plugin['slug'] ?? $plugin['name'] ?? '') : $plugin;
                    $result = $pluginTool->execute([
                        'action' => 'info',
                        'plugin_slug' => sanitize_title($slug)
                    ]);
                    
                    if ($result['success']) {
                        $verifiedPlugins[] = $result['data'];
                    }
                }
                
                $plan['verified_plugins'] = $verifiedPlugins;
            }
            
            return [
                'success' => true,
                'plan' => $plan
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a site plan
     *
     * @param array $plan Site plan
     * @param bool $confirmed User has confirmed execution
     * @return array Execution results
     */
    public function executePlan(array $plan, bool $confirmed = false): array {
        if (!$confirmed) {
            // Return dry run preview
            $this->setDryRunMode(true);
        }
        
        $results = [
            'pages_created' => [],
            'plugins_recommended' => [],
            'settings_updated' => [],
            'errors' => []
        ];
        
        // Create pages
        if (!empty($plan['pages'])) {
            $contentTool = new ContentTool();
            
            foreach ($plan['pages'] as $page) {
                $params = [
                    'action' => 'create',
                    'post_type' => 'page',
                    'title' => $page['title'] ?? 'Untitled',
                    'content' => $page['content'] ?? '',
                    'status' => $confirmed ? 'publish' : 'draft'
                ];
                
                if ($this->dryRunMode) {
                    $results['pages_created'][] = [
                        'dry_run' => true,
                        'description' => $contentTool->dryRun($params)
                    ];
                } else {
                    $result = $contentTool->execute($params);
                    if ($result['success']) {
                        $results['pages_created'][] = $result['data'];
                    } else {
                        $results['errors'][] = $result['error'];
                    }
                }
            }
        }
        
        // Plugin recommendations
        if (!empty($plan['verified_plugins'])) {
            $results['plugins_recommended'] = $plan['verified_plugins'];
        }
        
        // Update settings
        if (!empty($plan['settings']) && $confirmed) {
            $optionsTool = new OptionsTool();
            
            foreach ($plan['settings'] as $key => $value) {
                $result = $optionsTool->execute([
                    'action' => 'set',
                    'option_name' => $key,
                    'option_value' => $value
                ]);
                
                if ($result['success']) {
                    $results['settings_updated'][] = $key;
                } else {
                    $results['errors'][] = $result['error'];
                }
            }
        }
        
        $results['success'] = empty($results['errors']);
        $results['dry_run'] = $this->dryRunMode;
        
        return $results;
    }

    /**
     * Get tool registry
     *
     * @return ToolRegistry
     */
    public function getToolRegistry(): ToolRegistry {
        return $this->toolRegistry;
    }

    /**
     * Get conversation manager
     *
     * @return ConversationManager
     */
    public function getConversationManager(): ConversationManager {
        return $this->conversationManager;
    }

    /**
     * Get context builder
     *
     * @return ContextBuilder
     */
    public function getContextBuilder(): ContextBuilder {
        return $this->contextBuilder;
    }

    /**
     * Register a custom tool
     *
     * @param ToolInterface $tool Tool to register
     * @return self
     */
    public function registerTool(ToolInterface $tool): self {
        $this->toolRegistry->register($tool);
        return $this;
    }
}
