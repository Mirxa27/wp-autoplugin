<?php
/**
 * Context Builder - Builds context for AI model
 *
 * @package WP_Autoplugin\Agent
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Context Builder Class
 *
 * Builds the Model Context Protocol (MCP) context that provides
 * the AI with awareness of the current site state.
 */
class ContextBuilder {
    /**
     * Build complete site context
     *
     * @return array Site context
     */
    public function buildContext(): array {
        return [
            'site' => $this->getSiteInfo(),
            'content' => $this->getContentSummary(),
            'plugins' => $this->getPluginsSummary(),
            'theme' => $this->getThemeInfo(),
            'capabilities' => $this->getCurrentUserCapabilities(),
            'timestamp' => current_time('c')
        ];
    }

    /**
     * Build context as formatted string for prompt
     *
     * @return string Formatted context
     */
    public function buildContextString(): string {
        $context = $this->buildContext();
        
        $output = "=== CURRENT SITE CONTEXT ===\n\n";
        
        // Site Info
        $output .= "SITE INFORMATION:\n";
        $output .= "- Name: {$context['site']['name']}\n";
        $output .= "- Tagline: {$context['site']['tagline']}\n";
        $output .= "- URL: {$context['site']['url']}\n";
        $output .= "- WordPress Version: {$context['site']['wp_version']}\n";
        $output .= "- PHP Version: {$context['site']['php_version']}\n";
        $output .= "- Permalink Structure: {$context['site']['permalink_structure']}\n";
        $output .= "\n";
        
        // Content Summary
        $output .= "CONTENT SUMMARY:\n";
        $output .= "- Posts: {$context['content']['posts']['published']} published, {$context['content']['posts']['draft']} drafts\n";
        $output .= "- Pages: {$context['content']['pages']['published']} published, {$context['content']['pages']['draft']} drafts\n";
        if (!empty($context['content']['custom_post_types'])) {
            $output .= "- Custom Post Types: " . implode(', ', array_keys($context['content']['custom_post_types'])) . "\n";
        }
        $output .= "\n";
        
        // Pages List
        if (!empty($context['content']['page_list'])) {
            $output .= "EXISTING PAGES:\n";
            foreach ($context['content']['page_list'] as $page) {
                $output .= "- [{$page['id']}] {$page['title']} ({$page['status']})\n";
            }
            $output .= "\n";
        }
        
        // Active Plugins
        $output .= "ACTIVE PLUGINS ({$context['plugins']['active_count']}):\n";
        foreach ($context['plugins']['active'] as $plugin) {
            $output .= "- {$plugin['name']} v{$plugin['version']}\n";
        }
        $output .= "\n";
        
        // Theme
        $output .= "THEME:\n";
        $output .= "- Active: {$context['theme']['name']} v{$context['theme']['version']}\n";
        if ($context['theme']['is_child']) {
            $output .= "- Parent: {$context['theme']['parent']}\n";
        }
        $output .= "- Block Theme: " . ($context['theme']['is_block_theme'] ? 'Yes' : 'No') . "\n";
        $output .= "\n";
        
        // Capabilities
        $output .= "YOUR PERMISSIONS:\n";
        foreach ($context['capabilities'] as $cap => $has) {
            if ($has) {
                $output .= "- Can: " . str_replace('_', ' ', $cap) . "\n";
            }
        }
        
        $output .= "\n=== END CONTEXT ===\n";
        
        return $output;
    }

    /**
     * Get basic site information
     *
     * @return array Site info
     */
    private function getSiteInfo(): array {
        return [
            'name' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'url' => home_url(),
            'admin_url' => admin_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'language' => get_bloginfo('language'),
            'timezone' => wp_timezone_string(),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'permalink_structure' => get_option('permalink_structure') ?: 'Plain',
            'show_on_front' => get_option('show_on_front'),
            'page_on_front' => get_option('page_on_front'),
            'page_for_posts' => get_option('page_for_posts'),
            'multisite' => is_multisite()
        ];
    }

    /**
     * Get content summary
     *
     * @return array Content summary
     */
    private function getContentSummary(): array {
        // Posts count
        $postCounts = wp_count_posts('post');
        $posts = [
            'published' => (int) $postCounts->publish,
            'draft' => (int) $postCounts->draft,
            'pending' => (int) $postCounts->pending,
            'private' => (int) $postCounts->private
        ];
        
        // Pages count
        $pageCounts = wp_count_posts('page');
        $pages = [
            'published' => (int) $pageCounts->publish,
            'draft' => (int) $pageCounts->draft,
            'pending' => (int) $pageCounts->pending,
            'private' => (int) $pageCounts->private
        ];
        
        // Get page list (first 20)
        $pageList = [];
        $pagesQuery = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 20,
            'post_status' => ['publish', 'draft', 'pending'],
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
        
        foreach ($pagesQuery as $page) {
            $pageList[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'status' => $page->post_status,
                'slug' => $page->post_name,
                'parent' => $page->post_parent
            ];
        }
        
        // Custom post types
        $customPostTypes = [];
        $publicPostTypes = get_post_types(['public' => true, '_builtin' => false], 'objects');
        
        foreach ($publicPostTypes as $postType) {
            $counts = wp_count_posts($postType->name);
            $customPostTypes[$postType->name] = [
                'label' => $postType->label,
                'published' => (int) $counts->publish,
                'draft' => (int) $counts->draft
            ];
        }
        
        // Categories
        $categories = get_categories(['hide_empty' => false]);
        $categoryList = array_map(function($cat) {
            return ['id' => $cat->term_id, 'name' => $cat->name, 'count' => $cat->count];
        }, $categories);
        
        // Recent posts
        $recentPosts = [];
        $recent = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 5,
            'post_status' => 'publish'
        ]);
        
        foreach ($recent as $post) {
            $recentPosts[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => $post->post_date
            ];
        }
        
        return [
            'posts' => $posts,
            'pages' => $pages,
            'page_list' => $pageList,
            'custom_post_types' => $customPostTypes,
            'categories' => $categoryList,
            'recent_posts' => $recentPosts
        ];
    }

    /**
     * Get plugins summary
     *
     * @return array Plugins summary
     */
    private function getPluginsSummary(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $allPlugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        
        $active = [];
        $inactive = [];
        
        foreach ($allPlugins as $file => $data) {
            $pluginInfo = [
                'name' => $data['Name'],
                'version' => $data['Version'],
                'file' => $file
            ];
            
            if (in_array($file, $activePlugins, true)) {
                $active[] = $pluginInfo;
            } else {
                $inactive[] = $pluginInfo;
            }
        }
        
        return [
            'total' => count($allPlugins),
            'active_count' => count($active),
            'inactive_count' => count($inactive),
            'active' => $active,
            'inactive' => $inactive
        ];
    }

    /**
     * Get theme information
     *
     * @return array Theme info
     */
    private function getThemeInfo(): array {
        $theme = wp_get_theme();
        
        return [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
            'is_child' => $theme->parent() !== false,
            'parent' => $theme->parent() ? $theme->parent()->get('Name') : null,
            'is_block_theme' => function_exists('wp_is_block_theme') && wp_is_block_theme(),
            'text_domain' => $theme->get('TextDomain')
        ];
    }

    /**
     * Get current user capabilities relevant to agent actions
     *
     * @return array Capabilities
     */
    private function getCurrentUserCapabilities(): array {
        return [
            'edit_posts' => current_user_can('edit_posts'),
            'publish_posts' => current_user_can('publish_posts'),
            'edit_pages' => current_user_can('edit_pages'),
            'publish_pages' => current_user_can('publish_pages'),
            'edit_others_posts' => current_user_can('edit_others_posts'),
            'delete_posts' => current_user_can('delete_posts'),
            'manage_options' => current_user_can('manage_options'),
            'activate_plugins' => current_user_can('activate_plugins'),
            'install_plugins' => current_user_can('install_plugins'),
            'switch_themes' => current_user_can('switch_themes'),
            'edit_theme_options' => current_user_can('edit_theme_options'),
            'manage_categories' => current_user_can('manage_categories'),
            'upload_files' => current_user_can('upload_files')
        ];
    }

    /**
     * Get minimal context for quick operations
     *
     * @return array Minimal context
     */
    public function buildMinimalContext(): array {
        return [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'wp_version' => get_bloginfo('version'),
            'active_plugins_count' => count(get_option('active_plugins', [])),
            'can_manage_options' => current_user_can('manage_options'),
            'can_edit_posts' => current_user_can('edit_posts')
        ];
    }
}
