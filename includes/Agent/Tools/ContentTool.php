<?php
/**
 * Content Tool - Manages posts, pages, and custom post types
 *
 * @package WP_Autoplugin\Agent\Tools
 * @since 2.1.0
 */

namespace WP_Autoplugin\Agent\Tools;

use WP_Autoplugin\Agent\AbstractTool;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Tool Class
 *
 * Provides tools for creating and managing WordPress content.
 */
class ContentTool extends AbstractTool {
    /**
     * Tool name
     *
     * @var string
     */
    protected string $name = 'content';

    /**
     * Required capability
     *
     * @var string
     */
    protected string $requiredCapability = 'edit_posts';

    /**
     * Get tool name
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Get tool description
     *
     * @return string
     */
    public function getDescription(): string {
        return __('Create, update, and manage WordPress posts, pages, and custom post types. Can create content with titles, body text, excerpts, and metadata.', 'wp-autoplugin');
    }

    /**
     * Get parameter schema
     *
     * @return array
     */
    public function getParameterSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['create', 'update', 'delete', 'get', 'list'],
                    'description' => __('Action to perform on content', 'wp-autoplugin')
                ],
                'post_type' => [
                    'type' => 'string',
                    'description' => __('Post type (post, page, or custom post type)', 'wp-autoplugin'),
                    'default' => 'post'
                ],
                'post_id' => [
                    'type' => 'integer',
                    'description' => __('Post ID for update/delete/get actions', 'wp-autoplugin')
                ],
                'title' => [
                    'type' => 'string',
                    'description' => __('Content title', 'wp-autoplugin')
                ],
                'content' => [
                    'type' => 'string',
                    'description' => __('Content body (supports HTML and Gutenberg blocks)', 'wp-autoplugin')
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => __('Content excerpt/summary', 'wp-autoplugin')
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish', 'pending', 'private'],
                    'description' => __('Post status', 'wp-autoplugin'),
                    'default' => 'draft'
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => __('Post meta key-value pairs', 'wp-autoplugin')
                ],
                'categories' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => __('Category names or IDs', 'wp-autoplugin')
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => __('Tag names', 'wp-autoplugin')
                ],
                'parent' => [
                    'type' => 'integer',
                    'description' => __('Parent post ID (for hierarchical post types)', 'wp-autoplugin')
                ],
                'menu_order' => [
                    'type' => 'integer',
                    'description' => __('Menu order for pages', 'wp-autoplugin')
                ],
                'template' => [
                    'type' => 'string',
                    'description' => __('Page template file', 'wp-autoplugin')
                ]
            ],
            'required' => ['action']
        ];
    }

    /**
     * Execute the tool
     *
     * @param array $params Parameters
     * @return array Result
     */
    public function execute(array $params): array {
        // Validate parameters
        $validation = $this->validateParams($params);
        if (!$validation['valid']) {
            return $this->error(implode(', ', $validation['errors']), 'validation_error');
        }

        $action = $params['action'];

        switch ($action) {
            case 'create':
                return $this->createContent($params);
            case 'update':
                return $this->updateContent($params);
            case 'delete':
                return $this->deleteContent($params);
            case 'get':
                return $this->getContent($params);
            case 'list':
                return $this->listContent($params);
            default:
                return $this->error(
                    sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action),
                    'invalid_action'
                );
        }
    }

    /**
     * Create content
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function createContent(array $params): array {
        $postType = $this->sanitizeString($params['post_type'] ?? 'post');

        // Check if post type exists
        if (!post_type_exists($postType)) {
            return $this->error(
                sprintf(__('Post type does not exist: %s', 'wp-autoplugin'), $postType),
                'invalid_post_type'
            );
        }

        // Check capability for this post type
        $postTypeObj = get_post_type_object($postType);
        if (!current_user_can($postTypeObj->cap->edit_posts)) {
            return $this->error(
                __('You do not have permission to create this content type', 'wp-autoplugin'),
                'permission_denied'
            );
        }

        // Prepare post data
        $postData = [
            'post_type' => $postType,
            'post_title' => $this->sanitizeString($params['title'] ?? ''),
            'post_content' => $this->sanitizeContent($params['content'] ?? ''),
            'post_excerpt' => $this->sanitizeContent($params['excerpt'] ?? ''),
            'post_status' => $this->sanitizeString($params['status'] ?? 'draft')
        ];

        // Optional fields
        if (isset($params['parent'])) {
            $postData['post_parent'] = absint($params['parent']);
        }
        if (isset($params['menu_order'])) {
            $postData['menu_order'] = absint($params['menu_order']);
        }

        // Insert post
        $postId = wp_insert_post($postData, true);

        if (is_wp_error($postId)) {
            return $this->error($postId->get_error_message(), 'create_failed');
        }

        // Handle categories
        if (!empty($params['categories']) && $postType === 'post') {
            $this->setCategories($postId, $params['categories']);
        }

        // Handle tags
        if (!empty($params['tags']) && $postType === 'post') {
            wp_set_post_tags($postId, $params['tags']);
        }

        // Handle meta
        if (!empty($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                update_post_meta($postId, $this->sanitizeString($key), $value);
            }
        }

        // Handle page template
        if (!empty($params['template']) && $postType === 'page') {
            update_post_meta($postId, '_wp_page_template', $this->sanitizeString($params['template']));
        }

        $this->logExecution('create', $params, ['success' => true, 'post_id' => $postId]);

        return $this->success(
            [
                'post_id' => $postId,
                'post_type' => $postType,
                'title' => $postData['post_title'],
                'status' => $postData['post_status'],
                'edit_url' => get_edit_post_link($postId, 'raw'),
                'view_url' => get_permalink($postId)
            ],
            sprintf(__('Created %s: %s', 'wp-autoplugin'), $postType, $postData['post_title'])
        );
    }

    /**
     * Update content
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function updateContent(array $params): array {
        if (empty($params['post_id'])) {
            return $this->error(__('Post ID is required for update', 'wp-autoplugin'), 'missing_post_id');
        }

        $postId = absint($params['post_id']);
        $post = get_post($postId);

        if (!$post) {
            return $this->error(__('Post not found', 'wp-autoplugin'), 'post_not_found');
        }

        // Check capability
        if (!current_user_can('edit_post', $postId)) {
            return $this->error(
                __('You do not have permission to edit this content', 'wp-autoplugin'),
                'permission_denied'
            );
        }

        // Prepare update data
        $postData = ['ID' => $postId];

        if (isset($params['title'])) {
            $postData['post_title'] = $this->sanitizeString($params['title']);
        }
        if (isset($params['content'])) {
            $postData['post_content'] = $this->sanitizeContent($params['content']);
        }
        if (isset($params['excerpt'])) {
            $postData['post_excerpt'] = $this->sanitizeContent($params['excerpt']);
        }
        if (isset($params['status'])) {
            $postData['post_status'] = $this->sanitizeString($params['status']);
        }
        if (isset($params['parent'])) {
            $postData['post_parent'] = absint($params['parent']);
        }
        if (isset($params['menu_order'])) {
            $postData['menu_order'] = absint($params['menu_order']);
        }

        // Update post
        $result = wp_update_post($postData, true);

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 'update_failed');
        }

        // Handle categories
        if (!empty($params['categories']) && $post->post_type === 'post') {
            $this->setCategories($postId, $params['categories']);
        }

        // Handle tags
        if (!empty($params['tags']) && $post->post_type === 'post') {
            wp_set_post_tags($postId, $params['tags']);
        }

        // Handle meta
        if (!empty($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                update_post_meta($postId, $this->sanitizeString($key), $value);
            }
        }

        $this->logExecution('update', $params, ['success' => true, 'post_id' => $postId]);

        return $this->success(
            [
                'post_id' => $postId,
                'updated_fields' => array_keys(array_diff_key($postData, ['ID' => 1]))
            ],
            sprintf(__('Updated %s: %s', 'wp-autoplugin'), $post->post_type, get_the_title($postId))
        );
    }

    /**
     * Delete content
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function deleteContent(array $params): array {
        if (empty($params['post_id'])) {
            return $this->error(__('Post ID is required for delete', 'wp-autoplugin'), 'missing_post_id');
        }

        $postId = absint($params['post_id']);
        $post = get_post($postId);

        if (!$post) {
            return $this->error(__('Post not found', 'wp-autoplugin'), 'post_not_found');
        }

        // Check capability
        if (!current_user_can('delete_post', $postId)) {
            return $this->error(
                __('You do not have permission to delete this content', 'wp-autoplugin'),
                'permission_denied'
            );
        }

        $title = get_the_title($postId);
        $postType = $post->post_type;

        // Move to trash (not permanent delete for safety)
        $result = wp_trash_post($postId);

        if (!$result) {
            return $this->error(__('Failed to delete post', 'wp-autoplugin'), 'delete_failed');
        }

        $this->logExecution('delete', $params, ['success' => true, 'post_id' => $postId]);

        return $this->success(
            [
                'post_id' => $postId,
                'post_type' => $postType,
                'title' => $title
            ],
            sprintf(__('Moved to trash: %s', 'wp-autoplugin'), $title)
        );
    }

    /**
     * Get single content
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function getContent(array $params): array {
        if (empty($params['post_id'])) {
            return $this->error(__('Post ID is required', 'wp-autoplugin'), 'missing_post_id');
        }

        $postId = absint($params['post_id']);
        $post = get_post($postId);

        if (!$post) {
            return $this->error(__('Post not found', 'wp-autoplugin'), 'post_not_found');
        }

        return $this->success([
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'categories' => wp_get_post_categories($postId, ['fields' => 'names']),
            'tags' => wp_get_post_tags($postId, ['fields' => 'names']),
            'edit_url' => get_edit_post_link($postId, 'raw'),
            'view_url' => get_permalink($postId)
        ]);
    }

    /**
     * List content
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function listContent(array $params): array {
        $postType = $this->sanitizeString($params['post_type'] ?? 'post');

        $args = [
            'post_type' => $postType,
            'posts_per_page' => min(absint($params['limit'] ?? 10), 50),
            'post_status' => $params['status'] ?? 'any',
            'orderby' => $params['orderby'] ?? 'date',
            'order' => $params['order'] ?? 'DESC'
        ];

        $query = new \WP_Query($args);
        $posts = [];

        foreach ($query->posts as $post) {
            $posts[] = [
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20)
            ];
        }

        return $this->success([
            'posts' => $posts,
            'total' => $query->found_posts,
            'post_type' => $postType
        ]);
    }

    /**
     * Set categories by name or ID
     *
     * @param int $postId Post ID
     * @param array $categories Category names or IDs
     */
    private function setCategories(int $postId, array $categories): void {
        $categoryIds = [];

        foreach ($categories as $category) {
            if (is_numeric($category)) {
                $categoryIds[] = absint($category);
            } else {
                $term = get_term_by('name', $category, 'category');
                if ($term) {
                    $categoryIds[] = $term->term_id;
                } else {
                    // Create category if it doesn't exist
                    $newTerm = wp_insert_term($category, 'category');
                    if (!is_wp_error($newTerm)) {
                        $categoryIds[] = $newTerm['term_id'];
                    }
                }
            }
        }

        if (!empty($categoryIds)) {
            wp_set_post_categories($postId, $categoryIds);
        }
    }

    /**
     * Perform dry run
     *
     * @param array $params Parameters
     * @return string Description of what would happen
     */
    public function dryRun(array $params): string {
        $action = $params['action'] ?? 'unknown';
        $postType = $params['post_type'] ?? 'post';

        switch ($action) {
            case 'create':
                $title = $params['title'] ?? __('Untitled', 'wp-autoplugin');
                $status = $params['status'] ?? 'draft';
                return sprintf(
                    __('Will create a new %1$s titled "%2$s" with status "%3$s"', 'wp-autoplugin'),
                    $postType,
                    $title,
                    $status
                );

            case 'update':
                $postId = $params['post_id'] ?? 0;
                $fields = array_intersect_key($params, array_flip(['title', 'content', 'excerpt', 'status']));
                return sprintf(
                    __('Will update %1$s #%2$d. Fields to update: %3$s', 'wp-autoplugin'),
                    $postType,
                    $postId,
                    implode(', ', array_keys($fields))
                );

            case 'delete':
                $postId = $params['post_id'] ?? 0;
                $post = get_post($postId);
                $title = $post ? $post->post_title : __('Unknown', 'wp-autoplugin');
                return sprintf(
                    __('Will move %1$s #%2$d ("%3$s") to trash', 'wp-autoplugin'),
                    $postType,
                    $postId,
                    $title
                );

            case 'get':
            case 'list':
                return __('This action is read-only and will not modify any content', 'wp-autoplugin');

            default:
                return sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action);
        }
    }
}
