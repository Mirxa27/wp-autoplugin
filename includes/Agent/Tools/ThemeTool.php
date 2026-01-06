<?php
/**
 * Theme Tool - Works with WordPress themes
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
 * Theme Tool Class
 *
 * Provides tools for working with WordPress themes,
 * including theme info, customizer settings, and menu management.
 */
class ThemeTool extends AbstractTool {
    /**
     * Tool name
     *
     * @var string
     */
    protected string $name = 'theme';

    /**
     * Required capability
     *
     * @var string
     */
    protected string $requiredCapability = 'edit_theme_options';

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
        return __('Manage WordPress theme settings, navigation menus, widgets, and customizer options. Works with any theme including popular themes like Astra, GeneratePress, Kadence, and block themes.', 'wp-autoplugin');
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
                    'enum' => ['get_info', 'list_menus', 'create_menu', 'add_menu_item', 'list_widget_areas', 'get_customizer_settings'],
                    'description' => __('Action to perform', 'wp-autoplugin')
                ],
                'menu_name' => [
                    'type' => 'string',
                    'description' => __('Name for the menu', 'wp-autoplugin')
                ],
                'menu_id' => [
                    'type' => 'integer',
                    'description' => __('Menu ID for adding items', 'wp-autoplugin')
                ],
                'menu_location' => [
                    'type' => 'string',
                    'description' => __('Theme menu location (e.g., primary, footer)', 'wp-autoplugin')
                ],
                'menu_item' => [
                    'type' => 'object',
                    'description' => __('Menu item data', 'wp-autoplugin'),
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'object_id' => ['type' => 'integer'],
                        'parent' => ['type' => 'integer']
                    ]
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
        $validation = $this->validateParams($params);
        if (!$validation['valid']) {
            return $this->error(implode(', ', $validation['errors']), 'validation_error');
        }

        $action = $params['action'];

        switch ($action) {
            case 'get_info':
                return $this->getThemeInfo();
            case 'list_menus':
                return $this->listMenus();
            case 'create_menu':
                return $this->createMenu($params);
            case 'add_menu_item':
                return $this->addMenuItem($params);
            case 'list_widget_areas':
                return $this->listWidgetAreas();
            case 'get_customizer_settings':
                return $this->getCustomizerSettings();
            default:
                return $this->error(
                    sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action),
                    'invalid_action'
                );
        }
    }

    /**
     * Get current theme information
     *
     * @return array Result
     */
    private function getThemeInfo(): array {
        $theme = wp_get_theme();
        $parentTheme = $theme->parent();

        $info = [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'description' => $theme->get('Description'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
            'is_child_theme' => $parentTheme !== false,
            'parent_theme' => $parentTheme ? $parentTheme->get('Name') : null,
            'is_block_theme' => function_exists('wp_is_block_theme') && wp_is_block_theme(),
            'text_domain' => $theme->get('TextDomain'),
            'tags' => $theme->get('Tags'),
            'menu_locations' => get_registered_nav_menus(),
            'supports' => $this->getThemeSupports()
        ];

        return $this->success($info);
    }

    /**
     * Get theme support features
     *
     * @return array
     */
    private function getThemeSupports(): array {
        $features = [
            'title-tag',
            'post-thumbnails',
            'custom-logo',
            'custom-header',
            'custom-background',
            'menus',
            'widgets',
            'editor-styles',
            'wp-block-styles',
            'responsive-embeds',
            'align-wide',
            'woocommerce'
        ];

        $supports = [];
        foreach ($features as $feature) {
            $supports[$feature] = current_theme_supports($feature);
        }

        return $supports;
    }

    /**
     * List navigation menus
     *
     * @return array Result
     */
    private function listMenus(): array {
        $menus = wp_get_nav_menus();
        $locations = get_registered_nav_menus();
        $assignedLocations = get_nav_menu_locations();

        $menuList = [];
        foreach ($menus as $menu) {
            $menuLocations = [];
            foreach ($assignedLocations as $location => $menuId) {
                if ($menuId === $menu->term_id) {
                    $menuLocations[] = $location;
                }
            }

            $menuList[] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'count' => $menu->count,
                'assigned_locations' => $menuLocations
            ];
        }

        return $this->success([
            'menus' => $menuList,
            'available_locations' => $locations,
            'total' => count($menuList)
        ]);
    }

    /**
     * Create a navigation menu
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function createMenu(array $params): array {
        if (empty($params['menu_name'])) {
            return $this->error(__('Menu name is required', 'wp-autoplugin'), 'missing_menu_name');
        }

        $menuName = sanitize_text_field($params['menu_name']);
        $menuId = wp_create_nav_menu($menuName);

        if (is_wp_error($menuId)) {
            return $this->error($menuId->get_error_message(), 'create_menu_failed');
        }

        // Assign to location if provided
        if (!empty($params['menu_location'])) {
            $locations = get_nav_menu_locations();
            $locations[$params['menu_location']] = $menuId;
            set_theme_mod('nav_menu_locations', $locations);
        }

        $this->logExecution('create_menu', $params, ['success' => true, 'menu_id' => $menuId]);

        return $this->success([
            'menu_id' => $menuId,
            'menu_name' => $menuName,
            'location' => $params['menu_location'] ?? null
        ], sprintf(__('Created menu: %s', 'wp-autoplugin'), $menuName));
    }

    /**
     * Add item to navigation menu
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function addMenuItem(array $params): array {
        if (empty($params['menu_id'])) {
            return $this->error(__('Menu ID is required', 'wp-autoplugin'), 'missing_menu_id');
        }

        if (empty($params['menu_item'])) {
            return $this->error(__('Menu item data is required', 'wp-autoplugin'), 'missing_menu_item');
        }

        $menuId = absint($params['menu_id']);
        $item = $params['menu_item'];

        $itemData = [
            'menu-item-title' => sanitize_text_field($item['title'] ?? ''),
            'menu-item-status' => 'publish'
        ];

        // Handle different item types
        $type = $item['type'] ?? 'custom';
        switch ($type) {
            case 'page':
                $itemData['menu-item-type'] = 'post_type';
                $itemData['menu-item-object'] = 'page';
                $itemData['menu-item-object-id'] = absint($item['object_id'] ?? 0);
                break;
            case 'post':
                $itemData['menu-item-type'] = 'post_type';
                $itemData['menu-item-object'] = 'post';
                $itemData['menu-item-object-id'] = absint($item['object_id'] ?? 0);
                break;
            case 'category':
                $itemData['menu-item-type'] = 'taxonomy';
                $itemData['menu-item-object'] = 'category';
                $itemData['menu-item-object-id'] = absint($item['object_id'] ?? 0);
                break;
            default:
                $itemData['menu-item-type'] = 'custom';
                $itemData['menu-item-url'] = esc_url_raw($item['url'] ?? '#');
        }

        // Parent item
        if (!empty($item['parent'])) {
            $itemData['menu-item-parent-id'] = absint($item['parent']);
        }

        $itemId = wp_update_nav_menu_item($menuId, 0, $itemData);

        if (is_wp_error($itemId)) {
            return $this->error($itemId->get_error_message(), 'add_menu_item_failed');
        }

        $this->logExecution('add_menu_item', $params, ['success' => true, 'item_id' => $itemId]);

        return $this->success([
            'item_id' => $itemId,
            'menu_id' => $menuId,
            'title' => $itemData['menu-item-title']
        ], sprintf(__('Added menu item: %s', 'wp-autoplugin'), $itemData['menu-item-title']));
    }

    /**
     * List widget areas
     *
     * @return array Result
     */
    private function listWidgetAreas(): array {
        global $wp_registered_sidebars;

        $sidebars = [];
        foreach ($wp_registered_sidebars as $id => $sidebar) {
            $sidebars[] = [
                'id' => $id,
                'name' => $sidebar['name'],
                'description' => $sidebar['description'] ?? '',
                'class' => $sidebar['class'] ?? ''
            ];
        }

        return $this->success([
            'widget_areas' => $sidebars,
            'total' => count($sidebars)
        ]);
    }

    /**
     * Get customizer settings
     *
     * @return array Result
     */
    private function getCustomizerSettings(): array {
        $themeMods = get_theme_mods();
        
        // Filter out internal/serialized data
        $settings = [];
        foreach ($themeMods as $key => $value) {
            if (!is_array($value) && !is_object($value) && strpos($key, 'nav_menu') === false) {
                $settings[$key] = $value;
            }
        }

        return $this->success([
            'settings' => $settings,
            'custom_logo' => get_theme_mod('custom_logo'),
            'header_image' => get_header_image(),
            'background_color' => get_background_color(),
            'background_image' => get_background_image()
        ]);
    }

    /**
     * Perform dry run
     *
     * @param array $params Parameters
     * @return string Description of what would happen
     */
    public function dryRun(array $params): string {
        $action = $params['action'] ?? 'unknown';

        switch ($action) {
            case 'get_info':
                return __('Will retrieve current theme information (read-only operation)', 'wp-autoplugin');
            case 'list_menus':
                return __('Will list all navigation menus (read-only operation)', 'wp-autoplugin');
            case 'create_menu':
                $name = $params['menu_name'] ?? __('unnamed', 'wp-autoplugin');
                return sprintf(__('Will create a new navigation menu named "%s"', 'wp-autoplugin'), $name);
            case 'add_menu_item':
                $title = $params['menu_item']['title'] ?? __('unnamed', 'wp-autoplugin');
                return sprintf(__('Will add menu item "%s" to menu', 'wp-autoplugin'), $title);
            case 'list_widget_areas':
                return __('Will list all widget areas (read-only operation)', 'wp-autoplugin');
            case 'get_customizer_settings':
                return __('Will retrieve customizer settings (read-only operation)', 'wp-autoplugin');
            default:
                return sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action);
        }
    }
}
