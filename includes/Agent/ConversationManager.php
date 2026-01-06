<?php
/**
 * Conversation Manager - Manages chat history and sessions
 *
 * @package WP_Autoplugin\Agent
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conversation Manager Class
 *
 * Manages persistent chat sessions and conversation history.
 */
class ConversationManager {
    /**
     * Option key for storing conversations
     *
     * @var string
     */
    private const OPTION_KEY = 'wp_autoplugin_agent_conversations';

    /**
     * Maximum messages to keep in history
     *
     * @var int
     */
    private const MAX_MESSAGES = 50;

    /**
     * Maximum conversations to keep
     *
     * @var int
     */
    private const MAX_CONVERSATIONS = 10;

    /**
     * Current session ID
     *
     * @var string|null
     */
    private ?string $sessionId = null;

    /**
     * Get or create a session
     *
     * @param string|null $sessionId Optional existing session ID
     * @return string Session ID
     */
    public function getSession(?string $sessionId = null): string {
        if ($sessionId && $this->sessionExists($sessionId)) {
            $this->sessionId = $sessionId;
            return $sessionId;
        }

        // Create new session
        $this->sessionId = $this->createSession();
        return $this->sessionId;
    }

    /**
     * Create a new conversation session
     *
     * @return string Session ID
     */
    public function createSession(): string {
        $sessionId = wp_generate_uuid4();
        $userId = get_current_user_id();
        
        $conversations = $this->getAllConversations();
        
        // Clean up old conversations for this user
        $userConversations = array_filter($conversations, fn($c) => $c['user_id'] === $userId);
        if (count($userConversations) >= self::MAX_CONVERSATIONS) {
            // Remove oldest conversation
            uasort($userConversations, fn($a, $b) => $a['created_at'] <=> $b['created_at']);
            $oldestId = array_key_first($userConversations);
            unset($conversations[$oldestId]);
        }
        
        $conversations[$sessionId] = [
            'id' => $sessionId,
            'user_id' => $userId,
            'created_at' => current_time('timestamp'),
            'updated_at' => current_time('timestamp'),
            'messages' => [],
            'context' => [],
            'title' => __('New Conversation', 'wp-autoplugin')
        ];
        
        $this->saveConversations($conversations);
        
        return $sessionId;
    }

    /**
     * Check if session exists
     *
     * @param string $sessionId Session ID
     * @return bool
     */
    public function sessionExists(string $sessionId): bool {
        $conversations = $this->getAllConversations();
        return isset($conversations[$sessionId]);
    }

    /**
     * Add a message to the conversation
     *
     * @param string $role Message role (user, assistant, system)
     * @param string $content Message content
     * @param array $metadata Additional metadata
     * @return array The added message
     */
    public function addMessage(string $role, string $content, array $metadata = []): array {
        if (!$this->sessionId) {
            $this->getSession();
        }
        
        $conversations = $this->getAllConversations();
        
        if (!isset($conversations[$this->sessionId])) {
            $this->getSession();
            $conversations = $this->getAllConversations();
        }
        
        $message = [
            'id' => wp_generate_uuid4(),
            'role' => $role,
            'content' => $content,
            'timestamp' => current_time('timestamp'),
            'metadata' => $metadata
        ];
        
        $conversations[$this->sessionId]['messages'][] = $message;
        $conversations[$this->sessionId]['updated_at'] = current_time('timestamp');
        
        // Trim messages if exceeding limit
        if (count($conversations[$this->sessionId]['messages']) > self::MAX_MESSAGES) {
            // Keep system messages and trim oldest non-system messages
            $messages = $conversations[$this->sessionId]['messages'];
            $systemMessages = array_filter($messages, fn($m) => $m['role'] === 'system');
            $otherMessages = array_filter($messages, fn($m) => $m['role'] !== 'system');
            
            // Keep last (MAX_MESSAGES - system count) messages
            $keepCount = self::MAX_MESSAGES - count($systemMessages);
            $otherMessages = array_slice($otherMessages, -$keepCount);
            
            $conversations[$this->sessionId]['messages'] = array_merge(
                array_values($systemMessages),
                array_values($otherMessages)
            );
        }
        
        // Auto-generate title from first user message
        if ($role === 'user' && $conversations[$this->sessionId]['title'] === __('New Conversation', 'wp-autoplugin')) {
            $conversations[$this->sessionId]['title'] = wp_trim_words($content, 6);
        }
        
        $this->saveConversations($conversations);
        
        return $message;
    }

    /**
     * Get conversation messages
     *
     * @param string|null $sessionId Session ID (uses current if null)
     * @return array Messages
     */
    public function getMessages(?string $sessionId = null): array {
        $sessionId = $sessionId ?? $this->sessionId;
        
        if (!$sessionId) {
            return [];
        }
        
        $conversations = $this->getAllConversations();
        
        return $conversations[$sessionId]['messages'] ?? [];
    }

    /**
     * Get messages formatted for LLM API
     *
     * @param string|null $sessionId Session ID
     * @return array Formatted messages
     */
    public function getMessagesForLLM(?string $sessionId = null): array {
        $messages = $this->getMessages($sessionId);
        
        return array_map(function($message) {
            return [
                'role' => $message['role'],
                'content' => $message['content']
            ];
        }, $messages);
    }

    /**
     * Get conversation details
     *
     * @param string|null $sessionId Session ID
     * @return array|null Conversation data
     */
    public function getConversation(?string $sessionId = null): ?array {
        $sessionId = $sessionId ?? $this->sessionId;
        
        if (!$sessionId) {
            return null;
        }
        
        $conversations = $this->getAllConversations();
        
        return $conversations[$sessionId] ?? null;
    }

    /**
     * Get all conversations for current user
     *
     * @return array User's conversations
     */
    public function getUserConversations(): array {
        $userId = get_current_user_id();
        $conversations = $this->getAllConversations();
        
        $userConversations = array_filter($conversations, fn($c) => $c['user_id'] === $userId);
        
        // Sort by updated_at descending
        uasort($userConversations, fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);
        
        // Return summary without full messages
        return array_map(function($conv) {
            return [
                'id' => $conv['id'],
                'title' => $conv['title'],
                'created_at' => $conv['created_at'],
                'updated_at' => $conv['updated_at'],
                'message_count' => count($conv['messages'])
            ];
        }, $userConversations);
    }

    /**
     * Delete a conversation
     *
     * @param string $sessionId Session ID
     * @return bool Success
     */
    public function deleteConversation(string $sessionId): bool {
        $conversations = $this->getAllConversations();
        
        // Only allow deleting own conversations
        if (!isset($conversations[$sessionId]) || $conversations[$sessionId]['user_id'] !== get_current_user_id()) {
            return false;
        }
        
        unset($conversations[$sessionId]);
        
        if ($this->sessionId === $sessionId) {
            $this->sessionId = null;
        }
        
        return $this->saveConversations($conversations);
    }

    /**
     * Clear all messages in current conversation
     *
     * @return bool Success
     */
    public function clearMessages(): bool {
        if (!$this->sessionId) {
            return false;
        }
        
        $conversations = $this->getAllConversations();
        
        if (!isset($conversations[$this->sessionId])) {
            return false;
        }
        
        $conversations[$this->sessionId]['messages'] = [];
        $conversations[$this->sessionId]['updated_at'] = current_time('timestamp');
        
        return $this->saveConversations($conversations);
    }

    /**
     * Update conversation title
     *
     * @param string $title New title
     * @param string|null $sessionId Session ID
     * @return bool Success
     */
    public function updateTitle(string $title, ?string $sessionId = null): bool {
        $sessionId = $sessionId ?? $this->sessionId;
        
        if (!$sessionId) {
            return false;
        }
        
        $conversations = $this->getAllConversations();
        
        if (!isset($conversations[$sessionId])) {
            return false;
        }
        
        $conversations[$sessionId]['title'] = sanitize_text_field($title);
        $conversations[$sessionId]['updated_at'] = current_time('timestamp');
        
        return $this->saveConversations($conversations);
    }

    /**
     * Store context data for the conversation
     *
     * @param array $context Context data
     * @return bool Success
     */
    public function storeContext(array $context): bool {
        if (!$this->sessionId) {
            return false;
        }
        
        $conversations = $this->getAllConversations();
        
        if (!isset($conversations[$this->sessionId])) {
            return false;
        }
        
        $conversations[$this->sessionId]['context'] = $context;
        $conversations[$this->sessionId]['updated_at'] = current_time('timestamp');
        
        return $this->saveConversations($conversations);
    }

    /**
     * Get stored context for conversation
     *
     * @param string|null $sessionId Session ID
     * @return array Context data
     */
    public function getContext(?string $sessionId = null): array {
        $sessionId = $sessionId ?? $this->sessionId;
        
        if (!$sessionId) {
            return [];
        }
        
        $conversations = $this->getAllConversations();
        
        return $conversations[$sessionId]['context'] ?? [];
    }

    /**
     * Get all conversations (internal)
     *
     * @return array All conversations
     */
    private function getAllConversations(): array {
        return get_option(self::OPTION_KEY, []);
    }

    /**
     * Save conversations to database
     *
     * @param array $conversations Conversations data
     * @return bool Success
     */
    private function saveConversations(array $conversations): bool {
        return update_option(self::OPTION_KEY, $conversations);
    }

    /**
     * Get current session ID
     *
     * @return string|null
     */
    public function getCurrentSessionId(): ?string {
        return $this->sessionId;
    }

    /**
     * Export conversation as JSON
     *
     * @param string|null $sessionId Session ID
     * @return string JSON string
     */
    public function exportConversation(?string $sessionId = null): string {
        $conversation = $this->getConversation($sessionId);
        
        if (!$conversation) {
            return '{}';
        }
        
        return json_encode($conversation, JSON_PRETTY_PRINT);
    }
}
