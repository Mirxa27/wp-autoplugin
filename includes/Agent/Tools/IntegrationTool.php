<?php
/**
 * Integration Tool - Works with popular WordPress plugins
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
 * Integration Tool Class
 *
 * Provides integration support for popular WordPress plugins like
 * WooCommerce, Contact Form 7, Yoast SEO, Elementor, etc.
 */
class IntegrationTool extends AbstractTool {
    /**
     * Tool name
     *
     * @var string
     */
    protected string $name = 'integrations';

    /**
     * Required capability
     *
     * @var string
     */
    protected string $requiredCapability = 'manage_options';

    /**
     * Supported plugins and their detection methods
     *
     * @var array
     */
    private array $supportedPlugins = [
        'woocommerce' => [
            'name' => 'WooCommerce',
            'check' => 'class_exists',
            'class' => 'WooCommerce',
            'file' => 'woocommerce/woocommerce.php'
        ],
        'contact-form-7' => [
            'name' => 'Contact Form 7',
            'check' => 'defined',
            'constant' => 'WPCF7_VERSION',
            'file' => 'contact-form-7/wp-contact-form-7.php'
        ],
        'yoast-seo' => [
            'name' => 'Yoast SEO',
            'check' => 'defined',
            'constant' => 'WPSEO_VERSION',
            'file' => 'wordpress-seo/wp-seo.php'
        ],
        'elementor' => [
            'name' => 'Elementor',
            'check' => 'defined',
            'constant' => 'ELEMENTOR_VERSION',
            'file' => 'elementor/elementor.php'
        ],
        'wpforms' => [
            'name' => 'WPForms',
            'check' => 'defined',
            'constant' => 'WPFORMS_VERSION',
            'file' => 'wpforms-lite/wpforms.php'
        ],
        'acf' => [
            'name' => 'Advanced Custom Fields',
            'check' => 'class_exists',
            'class' => 'ACF',
            'file' => 'advanced-custom-fields/acf.php'
        ],
        'jetpack' => [
            'name' => 'Jetpack',
            'check' => 'defined',
            'constant' => 'JETPACK__VERSION',
            'file' => 'jetpack/jetpack.php'
        ],
        'wordfence' => [
            'name' => 'Wordfence Security',
            'check' => 'defined',
            'constant' => 'WORDFENCE_VERSION',
            'file' => 'wordfence/wordfence.php'
        ],
        'rank-math' => [
            'name' => 'Rank Math SEO',
            'check' => 'class_exists',
            'class' => 'RankMath',
            'file' => 'seo-by-rank-math/rank-math.php'
        ],
        'classic-editor' => [
            'name' => 'Classic Editor',
            'check' => 'function_exists',
            'function' => 'classic_editor_init_actions',
            'file' => 'classic-editor/classic-editor.php'
        ]
    ];

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
        return __('Detect and interact with popular WordPress plugins like WooCommerce, Contact Form 7, Yoast SEO, Elementor, ACF, and more. Can check compatibility and provide plugin-specific recommendations.', 'wp-autoplugin');
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
                    'enum' => ['detect', 'check_compatibility', 'get_recommendations', 'get_woocommerce_info', 'get_seo_info'],
                    'description' => __('Action to perform', 'wp-autoplugin')
                ],
                'plugin_slug' => [
                    'type' => 'string',
                    'description' => __('Specific plugin slug to check', 'wp-autoplugin')
                ],
                'site_type' => [
                    'type' => 'string',
                    'enum' => ['blog', 'ecommerce', 'portfolio', 'business', 'membership', 'learning'],
                    'description' => __('Type of site for recommendations', 'wp-autoplugin')
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
            case 'detect':
                return $this->detectPlugins();
            case 'check_compatibility':
                return $this->checkCompatibility($params);
            case 'get_recommendations':
                return $this->getRecommendations($params);
            case 'get_woocommerce_info':
                return $this->getWooCommerceInfo();
            case 'get_seo_info':
                return $this->getSeoInfo();
            default:
                return $this->error(
                    sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action),
                    'invalid_action'
                );
        }
    }

    /**
     * Detect installed popular plugins
     *
     * @return array Result
     */
    private function detectPlugins(): array {
        $detected = [];
        $notInstalled = [];

        foreach ($this->supportedPlugins as $slug => $plugin) {
            $isActive = $this->isPluginActive($plugin);
            $isInstalled = $this->isPluginInstalled($plugin['file']);

            if ($isActive) {
                $detected[] = [
                    'slug' => $slug,
                    'name' => $plugin['name'],
                    'status' => 'active',
                    'version' => $this->getPluginVersion($plugin)
                ];
            } elseif ($isInstalled) {
                $detected[] = [
                    'slug' => $slug,
                    'name' => $plugin['name'],
                    'status' => 'installed_inactive'
                ];
            } else {
                $notInstalled[] = [
                    'slug' => $slug,
                    'name' => $plugin['name']
                ];
            }
        }

        return $this->success([
            'detected_plugins' => $detected,
            'available_integrations' => $notInstalled,
            'total_active' => count(array_filter($detected, fn($p) => $p['status'] === 'active')),
            'total_installed' => count($detected)
        ]);
    }

    /**
     * Check if a plugin is active
     *
     * @param array $plugin Plugin config
     * @return bool
     */
    private function isPluginActive(array $plugin): bool {
        switch ($plugin['check']) {
            case 'class_exists':
                return class_exists($plugin['class']);
            case 'defined':
                return defined($plugin['constant']);
            case 'function_exists':
                return function_exists($plugin['function']);
            default:
                return false;
        }
    }

    /**
     * Check if a plugin is installed
     *
     * @param string $file Plugin file path
     * @return bool
     */
    private function isPluginInstalled(string $file): bool {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        return isset($plugins[$file]);
    }

    /**
     * Get plugin version
     *
     * @param array $plugin Plugin config
     * @return string|null
     */
    private function getPluginVersion(array $plugin): ?string {
        if ($plugin['check'] === 'defined' && isset($plugin['constant'])) {
            return defined($plugin['constant']) ? constant($plugin['constant']) : null;
        }
        return null;
    }

    /**
     * Check compatibility with specific plugin
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function checkCompatibility(array $params): array {
        $slug = $params['plugin_slug'] ?? '';

        if (empty($slug)) {
            return $this->error(__('Plugin slug is required', 'wp-autoplugin'), 'missing_slug');
        }

        if (!isset($this->supportedPlugins[$slug])) {
            return $this->success([
                'plugin' => $slug,
                'supported' => false,
                'message' => __('This plugin is not in our integration list, but it may still work.', 'wp-autoplugin')
            ]);
        }

        $plugin = $this->supportedPlugins[$slug];
        $isActive = $this->isPluginActive($plugin);

        return $this->success([
            'plugin' => $slug,
            'name' => $plugin['name'],
            'supported' => true,
            'is_active' => $isActive,
            'compatibility' => $isActive ? 'full' : 'available',
            'features' => $this->getPluginFeatures($slug, $isActive)
        ]);
    }

    /**
     * Get plugin-specific features
     *
     * @param string $slug Plugin slug
     * @param bool $isActive Whether plugin is active
     * @return array Features
     */
    private function getPluginFeatures(string $slug, bool $isActive): array {
        $features = [
            'woocommerce' => [
                'Create products',
                'Manage orders',
                'Configure shipping',
                'Set up payments',
                'Generate product blocks'
            ],
            'contact-form-7' => [
                'Create contact forms',
                'Embed forms in pages',
                'Configure form settings'
            ],
            'yoast-seo' => [
                'Optimize meta titles',
                'Set meta descriptions',
                'Configure SEO settings'
            ],
            'elementor' => [
                'Create Elementor pages',
                'Use Elementor templates',
                'Generate Elementor widgets'
            ],
            'acf' => [
                'Create custom fields',
                'Manage field groups',
                'Configure field settings'
            ]
        ];

        return $features[$slug] ?? ['Basic integration available'];
    }

    /**
     * Get plugin recommendations based on site type
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function getRecommendations(array $params): array {
        $siteType = $params['site_type'] ?? 'blog';

        $recommendations = [
            'blog' => [
                ['slug' => 'wordpress-seo', 'name' => 'Yoast SEO', 'reason' => 'Essential for blog SEO'],
                ['slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'reason' => 'For reader contact forms'],
                ['slug' => 'jetpack', 'name' => 'Jetpack', 'reason' => 'Site stats and security'],
                ['slug' => 'akismet', 'name' => 'Akismet', 'reason' => 'Spam protection for comments']
            ],
            'ecommerce' => [
                ['slug' => 'woocommerce', 'name' => 'WooCommerce', 'reason' => 'Full ecommerce functionality'],
                ['slug' => 'wordpress-seo', 'name' => 'Yoast SEO', 'reason' => 'Product SEO optimization'],
                ['slug' => 'wordfence', 'name' => 'Wordfence', 'reason' => 'Security for transactions'],
                ['slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'reason' => 'Customer support forms']
            ],
            'portfolio' => [
                ['slug' => 'elementor', 'name' => 'Elementor', 'reason' => 'Visual page builder for portfolios'],
                ['slug' => 'wordpress-seo', 'name' => 'Yoast SEO', 'reason' => 'SEO for discoverability'],
                ['slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'reason' => 'Client inquiry forms']
            ],
            'business' => [
                ['slug' => 'elementor', 'name' => 'Elementor', 'reason' => 'Professional page designs'],
                ['slug' => 'wordpress-seo', 'name' => 'Yoast SEO', 'reason' => 'Local SEO optimization'],
                ['slug' => 'wpforms-lite', 'name' => 'WPForms', 'reason' => 'Business contact forms'],
                ['slug' => 'wordfence', 'name' => 'Wordfence', 'reason' => 'Business-grade security']
            ],
            'membership' => [
                ['slug' => 'woocommerce', 'name' => 'WooCommerce', 'reason' => 'Payment processing'],
                ['slug' => 'memberpress', 'name' => 'MemberPress', 'reason' => 'Membership management'],
                ['slug' => 'wordpress-seo', 'name' => 'Yoast SEO', 'reason' => 'SEO for member content']
            ],
            'learning' => [
                ['slug' => 'learnpress', 'name' => 'LearnPress', 'reason' => 'Course management'],
                ['slug' => 'woocommerce', 'name' => 'WooCommerce', 'reason' => 'Course payments'],
                ['slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'reason' => 'Student inquiries']
            ]
        ];

        $siteRecommendations = $recommendations[$siteType] ?? $recommendations['blog'];

        // Check which are already installed
        $detected = $this->detectPlugins();
        $activePlugins = [];
        if ($detected['success']) {
            foreach ($detected['data']['detected_plugins'] as $plugin) {
                if ($plugin['status'] === 'active') {
                    $activePlugins[] = $plugin['slug'];
                }
            }
        }

        // Mark which recommendations are already installed
        foreach ($siteRecommendations as &$rec) {
            $rec['already_installed'] = in_array($rec['slug'], $activePlugins, true);
        }

        return $this->success([
            'site_type' => $siteType,
            'recommendations' => $siteRecommendations,
            'note' => __('All recommendations are popular, well-maintained plugins from the WordPress.org directory.', 'wp-autoplugin')
        ]);
    }

    /**
     * Get WooCommerce-specific information
     *
     * @return array Result
     */
    private function getWooCommerceInfo(): array {
        if (!class_exists('WooCommerce')) {
            return $this->error(__('WooCommerce is not active', 'wp-autoplugin'), 'woocommerce_not_active');
        }

        $productCount = wp_count_posts('product');
        $orderCount = wp_count_posts('shop_order');

        $info = [
            'version' => defined('WC_VERSION') ? WC_VERSION : 'Unknown',
            'products' => [
                'published' => (int) $productCount->publish,
                'draft' => (int) $productCount->draft,
                'total' => (int) $productCount->publish + (int) $productCount->draft
            ],
            'orders' => [
                'total' => array_sum((array) $orderCount)
            ],
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'store_address' => [
                'country' => get_option('woocommerce_default_country', ''),
                'city' => get_option('woocommerce_store_city', ''),
                'postcode' => get_option('woocommerce_store_postcode', '')
            ]
        ];

        return $this->success($info);
    }

    /**
     * Get SEO plugin information
     *
     * @return array Result
     */
    private function getSeoInfo(): array {
        $seoPlugins = [];

        // Check Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $seoPlugins['yoast'] = [
                'name' => 'Yoast SEO',
                'version' => WPSEO_VERSION,
                'active' => true
            ];
        }

        // Check Rank Math
        if (class_exists('RankMath')) {
            $seoPlugins['rank_math'] = [
                'name' => 'Rank Math',
                'active' => true
            ];
        }

        // Check All in One SEO
        if (defined('AIOSEO_VERSION')) {
            $seoPlugins['aioseo'] = [
                'name' => 'All in One SEO',
                'version' => AIOSEO_VERSION,
                'active' => true
            ];
        }

        if (empty($seoPlugins)) {
            return $this->success([
                'has_seo_plugin' => false,
                'recommendation' => __('Consider installing Yoast SEO or Rank Math for better search engine optimization.', 'wp-autoplugin')
            ]);
        }

        return $this->success([
            'has_seo_plugin' => true,
            'active_seo_plugins' => $seoPlugins,
            'site_title' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description')
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
            case 'detect':
                return __('Will scan for popular plugins and report their status (read-only operation)', 'wp-autoplugin');
            case 'check_compatibility':
                return __('Will check compatibility with a specific plugin (read-only operation)', 'wp-autoplugin');
            case 'get_recommendations':
                $siteType = $params['site_type'] ?? 'blog';
                return sprintf(__('Will get plugin recommendations for a %s site (read-only operation)', 'wp-autoplugin'), $siteType);
            case 'get_woocommerce_info':
                return __('Will retrieve WooCommerce store information (read-only operation)', 'wp-autoplugin');
            case 'get_seo_info':
                return __('Will retrieve SEO plugin information (read-only operation)', 'wp-autoplugin');
            default:
                return sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action);
        }
    }
}
