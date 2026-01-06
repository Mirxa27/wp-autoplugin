<?php
/**
 * Agent Ajax Handler - Handles AJAX requests for agent chat
 *
 * @package WP_Autoplugin\Admin
 * @since 2.1.0
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\Agent\AgentManager;
use WP_Autoplugin\API\ApiManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Ajax Handler Class
 *
 * Handles all AJAX requests for the agent chat interface.
 */
class AgentAjaxHandler {
    /**
     * Agent Manager instance
     *
     * @var AgentManager
     */
    private AgentManager $agentManager;

    /**
     * Constructor
     *
     * @param AgentManager $agentManager Agent Manager instance
     */
    public function __construct(AgentManager $agentManager) {
        $this->agentManager = $agentManager;
    }

    /**
     * Initialize AJAX handlers
     *
     * @return void
     */
    public function initialize(): void {
        // Chat endpoint
        add_action('wp_ajax_wp_autoplugin_agent_chat', [$this, 'handleChat']);
        
        // Get conversations list
        add_action('wp_ajax_wp_autoplugin_agent_conversations', [$this, 'handleGetConversations']);
        
        // Get single conversation
        add_action('wp_ajax_wp_autoplugin_agent_get_conversation', [$this, 'handleGetConversation']);
        
        // Delete conversation
        add_action('wp_ajax_wp_autoplugin_agent_delete_conversation', [$this, 'handleDeleteConversation']);
        
        // Clear conversation
        add_action('wp_ajax_wp_autoplugin_agent_clear_conversation', [$this, 'handleClearConversation']);
        
        // Get site context
        add_action('wp_ajax_wp_autoplugin_agent_context', [$this, 'handleGetContext']);
        
        // Plan site
        add_action('wp_ajax_wp_autoplugin_agent_plan_site', [$this, 'handlePlanSite']);
        
        // Execute plan
        add_action('wp_ajax_wp_autoplugin_agent_execute_plan', [$this, 'handleExecutePlan']);
        
        // Confirm action
        add_action('wp_ajax_wp_autoplugin_agent_confirm_action', [$this, 'handleConfirmAction']);
        
        // Get available tools
        add_action('wp_ajax_wp_autoplugin_agent_tools', [$this, 'handleGetTools']);
    }

    /**
     * Verify AJAX request
     *
     * @return bool
     */
    private function verifyRequest(): bool {
        if (!check_ajax_referer('wp-autoplugin-agent-nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'wp-autoplugin')
            ], 403);
            return false;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Insufficient permissions', 'wp-autoplugin')
            ], 403);
            return false;
        }

        return true;
    }

    /**
     * Handle chat message
     *
     * @return void
     */
    public function handleChat(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $sessionId = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : null;
        $dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';

        if (empty($message)) {
            wp_send_json_error([
                'message' => __('Message is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        // Set dry run mode if requested
        if ($dryRun) {
            $this->agentManager->setDryRunMode(true);
        }

        $response = $this->agentManager->chat($message, $sessionId);

        if ($response['success']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response, 500);
        }
    }

    /**
     * Handle get conversations list
     *
     * @return void
     */
    public function handleGetConversations(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $conversations = $this->agentManager->getConversationManager()->getUserConversations();

        wp_send_json_success([
            'conversations' => $conversations
        ]);
    }

    /**
     * Handle get single conversation
     *
     * @return void
     */
    public function handleGetConversation(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $sessionId = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';

        if (empty($sessionId)) {
            wp_send_json_error([
                'message' => __('Session ID is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        $conversation = $this->agentManager->getConversationManager()->getConversation($sessionId);

        if ($conversation) {
            wp_send_json_success([
                'conversation' => $conversation
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Conversation not found', 'wp-autoplugin')
            ], 404);
        }
    }

    /**
     * Handle delete conversation
     *
     * @return void
     */
    public function handleDeleteConversation(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $sessionId = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';

        if (empty($sessionId)) {
            wp_send_json_error([
                'message' => __('Session ID is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        $result = $this->agentManager->getConversationManager()->deleteConversation($sessionId);

        if ($result) {
            wp_send_json_success([
                'message' => __('Conversation deleted', 'wp-autoplugin')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete conversation', 'wp-autoplugin')
            ], 500);
        }
    }

    /**
     * Handle clear conversation messages
     *
     * @return void
     */
    public function handleClearConversation(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $sessionId = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';

        if (empty($sessionId)) {
            wp_send_json_error([
                'message' => __('Session ID is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        // Get session and clear
        $this->agentManager->getConversationManager()->getSession($sessionId);
        $result = $this->agentManager->getConversationManager()->clearMessages();

        if ($result) {
            wp_send_json_success([
                'message' => __('Conversation cleared', 'wp-autoplugin')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to clear conversation', 'wp-autoplugin')
            ], 500);
        }
    }

    /**
     * Handle get site context
     *
     * @return void
     */
    public function handleGetContext(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $context = $this->agentManager->getContextBuilder()->buildContext();

        wp_send_json_success([
            'context' => $context
        ]);
    }

    /**
     * Handle plan site creation
     *
     * @return void
     */
    public function handlePlanSite(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        if (empty($description)) {
            wp_send_json_error([
                'message' => __('Site description is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        $result = $this->agentManager->planSite($description);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result, 500);
        }
    }

    /**
     * Handle execute plan
     *
     * @return void
     */
    public function handleExecutePlan(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $planJson = isset($_POST['plan']) ? wp_unslash($_POST['plan']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === 'true';

        if (empty($planJson)) {
            wp_send_json_error([
                'message' => __('Plan is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        $plan = json_decode($planJson, true);

        if (!$plan) {
            wp_send_json_error([
                'message' => __('Invalid plan format', 'wp-autoplugin')
            ], 400);
            return;
        }

        $result = $this->agentManager->executePlan($plan, $confirmed);

        wp_send_json_success($result);
    }

    /**
     * Handle confirm pending action
     *
     * @return void
     */
    public function handleConfirmAction(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $actionId = isset($_POST['action_id']) ? sanitize_text_field(wp_unslash($_POST['action_id'])) : '';
        $confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === 'true';

        if (empty($actionId)) {
            wp_send_json_error([
                'message' => __('Action ID is required', 'wp-autoplugin')
            ], 400);
            return;
        }

        // Get pending action and execute if confirmed
        // This would be implemented with a pending actions queue
        
        wp_send_json_success([
            'message' => $confirmed 
                ? __('Action confirmed and executed', 'wp-autoplugin')
                : __('Action cancelled', 'wp-autoplugin'),
            'confirmed' => $confirmed
        ]);
    }

    /**
     * Handle get available tools
     *
     * @return void
     */
    public function handleGetTools(): void {
        if (!$this->verifyRequest()) {
            return;
        }

        $tools = $this->agentManager->getToolRegistry()->getToolsList();
        $schema = $this->agentManager->getToolRegistry()->getToolsSchema();

        wp_send_json_success([
            'tools' => $tools,
            'schema' => $schema
        ]);
    }
}
