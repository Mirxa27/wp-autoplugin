/**
 * WP-Autoplugin Agent Chat JavaScript
 *
 * @package WP-Autoplugin
 * @since 2.1.0
 */

(function($) {
    'use strict';

    // Agent Chat Application
    const AgentChat = {
        // State
        sessionId: null,
        isLoading: false,
        dryRunMode: false,

        // DOM Elements
        elements: {
            chatMessages: null,
            chatInput: null,
            sendBtn: null,
            newChatBtn: null,
            clearChatBtn: null,
            viewContextBtn: null,
            conversationsList: null,
            dryRunToggle: null,
            contextModal: null,
            confirmModal: null
        },

        /**
         * Initialize the chat application
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.loadConversations();
            this.renderExamplePrompts();
            this.autoResizeTextarea();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.chatMessages = $('#chat-messages');
            this.elements.chatInput = $('#chat-input');
            this.elements.sendBtn = $('#send-btn');
            this.elements.newChatBtn = $('#new-chat-btn');
            this.elements.clearChatBtn = $('#clear-chat-btn');
            this.elements.viewContextBtn = $('#view-context-btn');
            this.elements.conversationsList = $('#conversations-list');
            this.elements.dryRunToggle = $('#dry-run-mode');
            this.elements.contextModal = $('#context-modal');
            this.elements.confirmModal = $('#confirm-modal');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Send message
            this.elements.sendBtn.on('click', function() {
                self.sendMessage();
            });

            // Input keypress
            this.elements.chatInput.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // New chat
            this.elements.newChatBtn.on('click', function() {
                self.startNewChat();
            });

            // Clear chat
            this.elements.clearChatBtn.on('click', function() {
                if (confirm(wpAutoPluginAgent.strings.confirmClear)) {
                    self.clearChat();
                }
            });

            // View context
            this.elements.viewContextBtn.on('click', function() {
                self.showContextModal();
            });

            // Dry run toggle
            this.elements.dryRunToggle.on('change', function() {
                self.dryRunMode = $(this).is(':checked');
            });

            // Modal close
            $('.modal-close').on('click', function() {
                $(this).closest('.agent-modal').hide();
            });

            // Click outside modal
            $('.agent-modal').on('click', function(e) {
                if ($(e.target).hasClass('agent-modal')) {
                    $(this).hide();
                }
            });

            // Example prompts click
            $(document).on('click', '.example-prompt', function() {
                self.elements.chatInput.val($(this).text());
                self.sendMessage();
            });

            // Conversation item click
            $(document).on('click', '.conversation-item', function() {
                const sessionId = $(this).data('session-id');
                self.loadConversation(sessionId);
            });

            // Auto-resize textarea
            this.elements.chatInput.on('input', function() {
                self.autoResizeTextarea();
            });
        },

        /**
         * Auto-resize textarea based on content
         */
        autoResizeTextarea: function() {
            const textarea = this.elements.chatInput[0];
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
        },

        /**
         * Send a chat message
         */
        sendMessage: function() {
            const message = this.elements.chatInput.val().trim();

            if (!message || this.isLoading) {
                return;
            }

            // Add user message to chat
            this.addMessage('user', message);
            this.elements.chatInput.val('');
            this.autoResizeTextarea();

            // Show typing indicator
            this.showTypingIndicator();

            // Set loading state
            this.setLoading(true);

            // Send to server
            $.ajax({
                url: wpAutoPluginAgent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_autoplugin_agent_chat',
                    nonce: wpAutoPluginAgent.nonce,
                    message: message,
                    session_id: this.sessionId,
                    dry_run: this.dryRunMode ? 'true' : 'false'
                },
                success: (response) => {
                    this.hideTypingIndicator();

                    if (response.success) {
                        this.sessionId = response.data.session_id;
                        this.addMessage('assistant', response.data.message, response.data);
                        this.loadConversations();
                    } else {
                        this.addMessage('assistant', response.data.message || wpAutoPluginAgent.strings.error, {
                            isError: true
                        });
                    }
                },
                error: (xhr, status, error) => {
                    this.hideTypingIndicator();
                    this.addMessage('assistant', wpAutoPluginAgent.strings.error + ': ' + error, {
                        isError: true
                    });
                },
                complete: () => {
                    this.setLoading(false);
                }
            });
        },

        /**
         * Add a message to the chat
         */
        addMessage: function(role, content, data = {}) {
            const isUser = role === 'user';
            const avatarIcon = isUser ? 'dashicons-admin-users' : 'dashicons-admin-plugins';
            
            let messageHtml = `
                <div class="message ${role}-message">
                    <div class="message-avatar">
                        <span class="dashicons ${avatarIcon}"></span>
                    </div>
                    <div class="message-content">
                        <div class="message-text ${data.isError ? 'error' : ''}">${this.formatMessage(content)}</div>
            `;

            // Add results if present
            if (data.results && data.results.length > 0) {
                messageHtml += this.formatResults(data.results);
            }

            // Add tool calls info if in dry run mode
            if (data.dry_run && data.tool_calls && data.tool_calls.length > 0) {
                messageHtml += '<div class="dry-run-notice"><span class="dashicons dashicons-info"></span> ' + 
                    wpAutoPluginAgent.strings.dryRunEnabled + '</div>';
            }

            messageHtml += `
                        <div class="message-meta">${this.formatTime(new Date())}</div>
                    </div>
                </div>
            `;

            // Remove welcome message if this is first real message
            if (this.elements.chatMessages.find('.message:not(.welcome-message)').length === 0) {
                // Keep welcome for reference
            }

            this.elements.chatMessages.append(messageHtml);
            this.scrollToBottom();
        },

        /**
         * Format message content
         */
        formatMessage: function(content) {
            // Convert markdown-like formatting
            let formatted = this.escapeHtml(content);

            // Code blocks
            formatted = formatted.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
            
            // Inline code
            formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');

            // Bold
            formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

            // Line breaks
            formatted = formatted.replace(/\n/g, '<br>');

            // Checkmarks and crosses
            formatted = formatted.replace(/✅/g, '<span class="success-icon">✅</span>');
            formatted = formatted.replace(/❌/g, '<span class="error-icon">❌</span>');

            return formatted;
        },

        /**
         * Format action results
         */
        formatResults: function(results) {
            let html = '<div class="action-results-container">';
            
            results.forEach(result => {
                const statusClass = result.success ? 'success' : 'error';
                const icon = result.success ? 'yes' : 'no';
                
                html += `
                    <div class="action-results ${statusClass}">
                        <span class="dashicons dashicons-${icon}"></span>
                        <span class="result-message">${this.escapeHtml(result.message || result.error || 'Action completed')}</span>
                `;

                if (result.data) {
                    if (result.data.edit_url) {
                        html += ` <a href="${result.data.edit_url}" target="_blank">Edit</a>`;
                    }
                    if (result.data.view_url) {
                        html += ` <a href="${result.data.view_url}" target="_blank">View</a>`;
                    }
                }

                html += '</div>';
            });

            html += '</div>';
            return html;
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Format time
         */
        formatTime: function(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        /**
         * Show typing indicator
         */
        showTypingIndicator: function() {
            const indicator = `
                <div class="message assistant-message typing-message">
                    <div class="message-avatar">
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </div>
                    <div class="message-content">
                        <div class="typing-indicator">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            `;
            this.elements.chatMessages.append(indicator);
            this.scrollToBottom();
        },

        /**
         * Hide typing indicator
         */
        hideTypingIndicator: function() {
            this.elements.chatMessages.find('.typing-message').remove();
        },

        /**
         * Scroll chat to bottom
         */
        scrollToBottom: function() {
            const container = this.elements.chatMessages[0];
            container.scrollTop = container.scrollHeight;
        },

        /**
         * Set loading state
         */
        setLoading: function(loading) {
            this.isLoading = loading;
            this.elements.sendBtn.prop('disabled', loading);
            this.elements.chatInput.prop('disabled', loading);
            
            if (loading) {
                this.elements.sendBtn.find('.button-text').text(wpAutoPluginAgent.strings.sending);
            } else {
                this.elements.sendBtn.find('.button-text').text(wpAutoPluginAgent.strings.send);
            }
        },

        /**
         * Load conversations list
         */
        loadConversations: function() {
            $.ajax({
                url: wpAutoPluginAgent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_autoplugin_agent_conversations',
                    nonce: wpAutoPluginAgent.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderConversations(response.data.conversations);
                    }
                }
            });
        },

        /**
         * Render conversations list
         */
        renderConversations: function(conversations) {
            const list = this.elements.conversationsList;
            list.empty();

            if (conversations.length === 0) {
                list.html('<p class="no-conversations">' + wpAutoPluginAgent.strings.newChat + '</p>');
                return;
            }

            conversations.forEach(conv => {
                const isActive = conv.id === this.sessionId ? 'active' : '';
                const date = new Date(conv.updated_at * 1000);
                const timeAgo = this.timeAgo(date);

                list.append(`
                    <div class="conversation-item ${isActive}" data-session-id="${conv.id}">
                        <div class="title">${this.escapeHtml(conv.title)}</div>
                        <div class="meta">${conv.message_count} messages · ${timeAgo}</div>
                    </div>
                `);
            });
        },

        /**
         * Load a specific conversation
         */
        loadConversation: function(sessionId) {
            $.ajax({
                url: wpAutoPluginAgent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_autoplugin_agent_get_conversation',
                    nonce: wpAutoPluginAgent.nonce,
                    session_id: sessionId
                },
                success: (response) => {
                    if (response.success) {
                        this.sessionId = sessionId;
                        this.renderConversation(response.data.conversation);
                        this.loadConversations();
                    }
                }
            });
        },

        /**
         * Render a conversation
         */
        renderConversation: function(conversation) {
            // Clear current messages except welcome
            this.elements.chatMessages.find('.message:not(.welcome-message)').remove();
            
            // Hide welcome message
            this.elements.chatMessages.find('.welcome-message').hide();

            // Add messages
            conversation.messages.forEach(msg => {
                this.addMessage(msg.role, msg.content, msg.metadata || {});
            });

            // Update title
            $('#chat-title').text(conversation.title);
        },

        /**
         * Start a new chat
         */
        startNewChat: function() {
            this.sessionId = null;
            this.elements.chatMessages.find('.message:not(.welcome-message)').remove();
            this.elements.chatMessages.find('.welcome-message').show();
            $('#chat-title').text(wpAutoPluginAgent.strings.newChat);
            this.loadConversations();
        },

        /**
         * Clear current chat
         */
        clearChat: function() {
            if (!this.sessionId) {
                return;
            }

            $.ajax({
                url: wpAutoPluginAgent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_autoplugin_agent_clear_conversation',
                    nonce: wpAutoPluginAgent.nonce,
                    session_id: this.sessionId
                },
                success: (response) => {
                    if (response.success) {
                        this.elements.chatMessages.find('.message:not(.welcome-message)').remove();
                        this.elements.chatMessages.find('.welcome-message').show();
                    }
                }
            });
        },

        /**
         * Show context modal
         */
        showContextModal: function() {
            const modal = this.elements.contextModal;
            const content = $('#context-content');
            
            content.text('Loading...');
            modal.show();

            $.ajax({
                url: wpAutoPluginAgent.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_autoplugin_agent_context',
                    nonce: wpAutoPluginAgent.nonce
                },
                success: (response) => {
                    if (response.success) {
                        content.text(JSON.stringify(response.data.context, null, 2));
                    }
                }
            });
        },

        /**
         * Render example prompts
         */
        renderExamplePrompts: function() {
            const container = $('#example-prompts');
            const examples = wpAutoPluginAgent.strings.examples || [];

            examples.forEach(example => {
                container.append(`<button type="button" class="example-prompt">${this.escapeHtml(example)}</button>`);
            });
        },

        /**
         * Format time ago
         */
        timeAgo: function(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            
            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            return Math.floor(seconds / 86400) + 'd ago';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.wp-autoplugin-agent-wrap').length) {
            AgentChat.init();
        }
    });

})(jQuery);
