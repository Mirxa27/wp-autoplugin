<?php
/**
 * Admin view for the AI Agent Chat page.
 *
 * @package WP-Autoplugin
 * @since 2.1.0
 */

namespace WP_Autoplugin;

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wp-autoplugin-agent-wrap">
    <div class="wp-autoplugin-agent-container">
        <!-- Sidebar -->
        <div class="agent-sidebar">
            <div class="sidebar-header">
                <h2><?php esc_html_e('AI Agent', 'wp-autoplugin'); ?></h2>
                <button type="button" id="new-chat-btn" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e('New Chat', 'wp-autoplugin'); ?>
                </button>
            </div>
            
            <div class="conversations-list" id="conversations-list">
                <div class="loading-conversations">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading conversations...', 'wp-autoplugin'); ?>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="dry-run-toggle">
                    <label for="dry-run-mode">
                        <input type="checkbox" id="dry-run-mode" />
                        <?php esc_html_e('Dry Run Mode', 'wp-autoplugin'); ?>
                    </label>
                    <span class="dashicons dashicons-info" title="<?php esc_attr_e('When enabled, actions will be described but not executed', 'wp-autoplugin'); ?>"></span>
                </div>
                
                <div class="site-context-summary" id="site-context-summary">
                    <strong><?php esc_html_e('Site:', 'wp-autoplugin'); ?></strong>
                    <span id="site-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Main Chat Area -->
        <div class="agent-main">
            <div class="chat-header">
                <div class="chat-title">
                    <h1 id="chat-title"><?php esc_html_e('Chat with AI Agent', 'wp-autoplugin'); ?></h1>
                    <span class="chat-subtitle" id="chat-subtitle"><?php esc_html_e('Your intelligent WordPress assistant', 'wp-autoplugin'); ?></span>
                </div>
                <div class="chat-actions">
                    <button type="button" id="clear-chat-btn" class="button" title="<?php esc_attr_e('Clear conversation', 'wp-autoplugin'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <button type="button" id="view-context-btn" class="button" title="<?php esc_attr_e('View site context', 'wp-autoplugin'); ?>">
                        <span class="dashicons dashicons-info-outline"></span>
                    </button>
                </div>
            </div>
            
            <div class="chat-messages" id="chat-messages">
                <!-- Welcome message -->
                <div class="message assistant-message welcome-message">
                    <div class="message-avatar">
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </div>
                    <div class="message-content">
                        <div class="message-text" id="welcome-text">
                            <?php esc_html_e('Hello! I\'m your AI assistant. I can help you:', 'wp-autoplugin'); ?>
                            <ul>
                                <li><?php esc_html_e('Create and manage posts, pages, and content', 'wp-autoplugin'); ?></li>
                                <li><?php esc_html_e('Generate Gutenberg blocks and layouts', 'wp-autoplugin'); ?></li>
                                <li><?php esc_html_e('Search and recommend plugins from WordPress.org', 'wp-autoplugin'); ?></li>
                                <li><?php esc_html_e('Update site settings and options', 'wp-autoplugin'); ?></li>
                                <li><?php esc_html_e('Plan and build entire sites from a description', 'wp-autoplugin'); ?></li>
                            </ul>
                            <p><?php esc_html_e('Try one of these examples:', 'wp-autoplugin'); ?></p>
                        </div>
                        <div class="example-prompts" id="example-prompts">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="chat-input-area">
                <div class="chat-input-container">
                    <textarea 
                        id="chat-input" 
                        placeholder="<?php esc_attr_e('Ask me anything about your site, or tell me what to build...', 'wp-autoplugin'); ?>"
                        rows="1"
                    ></textarea>
                    <button type="button" id="send-btn" class="button button-primary">
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                        <span class="button-text"><?php esc_html_e('Send', 'wp-autoplugin'); ?></span>
                    </button>
                </div>
                <div class="chat-input-footer">
                    <span class="model-info">
                        <?php
                        printf(
                            /* translators: %s: model name */
                            esc_html__('Using: %s', 'wp-autoplugin'),
                            '<strong>' . esc_html(get_option('wp_autoplugin_model', 'gpt-4o')) . '</strong>'
                        );
                        ?>
                    </span>
                    <span class="keyboard-hint">
                        <?php esc_html_e('Press Enter to send, Shift+Enter for new line', 'wp-autoplugin'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Context Modal -->
<div id="context-modal" class="agent-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php esc_html_e('Site Context', 'wp-autoplugin'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="context-content"><?php esc_html_e('Loading...', 'wp-autoplugin'); ?></pre>
        </div>
    </div>
</div>

<!-- Confirm Action Modal -->
<div id="confirm-modal" class="agent-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php esc_html_e('Confirm Action', 'wp-autoplugin'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p id="confirm-message"></p>
            <div class="confirm-details" id="confirm-details"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" id="confirm-cancel"><?php esc_html_e('Cancel', 'wp-autoplugin'); ?></button>
            <button type="button" class="button button-primary" id="confirm-execute"><?php esc_html_e('Execute', 'wp-autoplugin'); ?></button>
        </div>
    </div>
</div>

<style>
/* Agent Container */
.wp-autoplugin-agent-wrap {
    margin: 0;
    padding: 0;
    max-width: none;
}

.wp-autoplugin-agent-container {
    display: flex;
    height: calc(100vh - 32px);
    background: #f0f0f1;
}

/* Sidebar */
.agent-sidebar {
    width: 280px;
    background: #1d2327;
    color: #fff;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h2 {
    color: #fff;
    margin: 0 0 15px 0;
    font-size: 18px;
}

.sidebar-header .button {
    width: 100%;
    text-align: center;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.conversation-item {
    padding: 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-bottom: 5px;
    transition: background 0.2s;
}

.conversation-item:hover,
.conversation-item.active {
    background: rgba(255,255,255,0.1);
}

.conversation-item .title {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-item .meta {
    font-size: 11px;
    color: rgba(255,255,255,0.6);
    margin-top: 4px;
}

.sidebar-footer {
    padding: 15px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.dry-run-toggle {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 10px;
}

.dry-run-toggle label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.site-context-summary {
    font-size: 12px;
    color: rgba(255,255,255,0.7);
}

/* Main Chat Area */
.agent-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.chat-header {
    padding: 20px 30px;
    border-bottom: 1px solid #dcdcde;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-title h1 {
    margin: 0;
    font-size: 20px;
}

.chat-subtitle {
    color: #646970;
    font-size: 13px;
}

.chat-actions {
    display: flex;
    gap: 5px;
}

/* Messages */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
}

.message {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    max-width: 900px;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f0f0f1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.message-avatar .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.assistant-message .message-avatar {
    background: #2271b1;
    color: #fff;
}

.user-message .message-avatar {
    background: #50575e;
    color: #fff;
}

.message-content {
    flex: 1;
}

.message-text {
    background: #f6f7f7;
    padding: 15px 20px;
    border-radius: 8px;
    line-height: 1.6;
}

.user-message .message-text {
    background: #e7f3ff;
}

.message-text ul {
    margin: 10px 0;
    padding-left: 20px;
}

.message-text pre {
    background: #1d2327;
    color: #fff;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 13px;
}

.message-meta {
    font-size: 11px;
    color: #646970;
    margin-top: 5px;
}

/* Results */
.action-results {
    margin-top: 10px;
    padding: 10px 15px;
    background: #f0f6fc;
    border-radius: 4px;
    border-left: 3px solid #2271b1;
}

.action-results.success {
    background: #edfaef;
    border-color: #00a32a;
}

.action-results.error {
    background: #fcf0f1;
    border-color: #d63638;
}

/* Example Prompts */
.example-prompts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}

.example-prompt {
    background: #fff;
    border: 1px solid #dcdcde;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.example-prompt:hover {
    background: #f0f6fc;
    border-color: #2271b1;
}

/* Input Area */
.chat-input-area {
    padding: 20px 30px;
    border-top: 1px solid #dcdcde;
    background: #f6f7f7;
}

.chat-input-container {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

#chat-input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
    resize: none;
    max-height: 150px;
    min-height: 44px;
}

#chat-input:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

#send-btn {
    height: 44px;
    padding: 0 20px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.chat-input-footer {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    font-size: 12px;
    color: #646970;
}

/* Loading State */
.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 15px 20px;
    background: #f6f7f7;
    border-radius: 8px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #646970;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

/* Modal */
.agent-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    width: 600px;
    max-width: 90%;
    max-height: 80%;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #dcdcde;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.modal-body pre {
    background: #1d2327;
    color: #fff;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
    white-space: pre-wrap;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #dcdcde;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Loading Conversations */
.loading-conversations {
    text-align: center;
    padding: 20px;
    color: rgba(255,255,255,0.6);
}

.loading-conversations .spinner {
    float: none;
    margin: 0 5px 0 0;
}

/* Responsive */
@media (max-width: 782px) {
    .wp-autoplugin-agent-container {
        flex-direction: column;
        height: auto;
        min-height: calc(100vh - 46px);
    }
    
    .agent-sidebar {
        width: 100%;
        max-height: 200px;
    }
    
    .conversations-list {
        display: flex;
        overflow-x: auto;
        padding: 10px;
    }
    
    .conversation-item {
        white-space: nowrap;
        margin-right: 10px;
        margin-bottom: 0;
    }
}
</style>
