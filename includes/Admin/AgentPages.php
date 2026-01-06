<?php
/**
 * Agent Pages - Admin pages for the agent interface
 *
 * @package WP_Autoplugin\Admin
 * @since 2.1.0
 */

namespace WP_Autoplugin\Admin;

use WP_Autoplugin\Agent\AgentManager;
use WP_Autoplugin\Utils\Assets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agent Pages Class
 *
 * Registers and renders the agent chat interface pages.
 */
class AgentPages {
    /**
     * Agent Manager instance
     *
     * @var AgentManager
     */
    private AgentManager $agentManager;

    /**
     * Assets Manager instance
     *
     * @var Assets
     */
    private Assets $assets;

    /**
     * Constructor
     *
     * @param AgentManager $agentManager Agent Manager instance
     * @param Assets $assets Assets Manager instance
     */
    public function __construct(AgentManager $agentManager, Assets $assets) {
        $this->agentManager = $agentManager;
        $this->assets = $assets;
    }

    /**
     * Initialize pages
     *
     * @return void
     */
    public function initialize(): void {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function registerMenu(): void {
        add_submenu_page(
            'wp-autoplugin',
            __('AI Agent', 'wp-autoplugin'),
            __('AI Agent', 'wp-autoplugin'),
            'manage_options',
            'wp-autoplugin-agent',
            [$this, 'renderAgentPage']
        );
    }

    /**
     * Enqueue assets for agent page
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hook): void {
        if ($hook !== 'wp-autoplugin_page_wp-autoplugin-agent') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'wp-autoplugin-agent',
            WP_AUTOPLUGIN_URL . 'assets/admin/css/agent.css',
            [],
            WP_AUTOPLUGIN_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'wp-autoplugin-agent',
            WP_AUTOPLUGIN_URL . 'assets/admin/js/agent.js',
            ['jquery', 'wp-util'],
            WP_AUTOPLUGIN_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wp-autoplugin-agent', 'wpAutoPluginAgent', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp-autoplugin-agent-nonce'),
            'strings' => [
                'send' => __('Send', 'wp-autoplugin'),
                'sending' => __('Sending...', 'wp-autoplugin'),
                'thinking' => __('Thinking...', 'wp-autoplugin'),
                'error' => __('Error', 'wp-autoplugin'),
                'newChat' => __('New Chat', 'wp-autoplugin'),
                'confirmDelete' => __('Are you sure you want to delete this conversation?', 'wp-autoplugin'),
                'confirmClear' => __('Are you sure you want to clear this conversation?', 'wp-autoplugin'),
                'confirmAction' => __('Are you sure you want to execute this action?', 'wp-autoplugin'),
                'dryRunEnabled' => __('Dry Run Mode: Actions will be described but not executed', 'wp-autoplugin'),
                'placeholder' => __('Ask me anything about your site, or tell me what to build...', 'wp-autoplugin'),
                'welcome' => __('Hello! I\'m your AI assistant. I can help you manage your WordPress site, create content, recommend plugins, and build pages. What would you like to do?', 'wp-autoplugin'),
                'examples' => [
                    __('Build a portfolio site with About, Work, and Contact pages', 'wp-autoplugin'),
                    __('What plugins would you recommend for an ecommerce site?', 'wp-autoplugin'),
                    __('Create a hero section for my homepage', 'wp-autoplugin'),
                    __('Detect what popular plugins I have installed', 'wp-autoplugin'),
                    __('Create a main navigation menu with Home, About, and Contact', 'wp-autoplugin'),
                    __('What SEO plugin am I using?', 'wp-autoplugin')
                ]
            ],
            'context' => $this->agentManager->getContextBuilder()->buildMinimalContext()
        ]);
    }

    /**
     * Render agent page
     *
     * @return void
     */
    public function renderAgentPage(): void {
        include WP_AUTOPLUGIN_DIR . 'views/page-agent-chat.php';
    }
}
