<?php
/**
 * Feature Manager - Manages plugin features
 *
 * @package WP_Autoplugin\Features
 * @since 2.0.0
 */

namespace WP_Autoplugin\Features;

use WP_Autoplugin\API\ApiManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature Manager Class
 *
 * Manages which features are enabled and available.
 */
class FeatureManager {
    /**
     * API Manager instance
     *
     * @var ApiManager
     */
    private ApiManager $apiManager;

    /**
     * Available features
     *
     * @var array
     */
    private array $features = [
        'generate' => [
            'name' => 'Generate Plugin',
            'description' => 'Generate new plugins from descriptions',
            'enabled' => true
        ],
        'fix' => [
            'name' => 'Fix Plugin',
            'description' => 'Fix issues in existing plugins',
            'enabled' => true
        ],
        'extend' => [
            'name' => 'Extend Plugin',
            'description' => 'Extend plugins with new features',
            'enabled' => true
        ],
        'explain' => [
            'name' => 'Explain Plugin',
            'description' => 'Get explanations of plugin code',
            'enabled' => true
        ],
        'agent' => [
            'name' => 'AI Agent',
            'description' => 'Chat-based AI assistant for site management',
            'enabled' => true
        ]
    ];

    /**
     * Constructor
     *
     * @param ApiManager $apiManager API Manager instance
     */
    public function __construct(ApiManager $apiManager) {
        $this->apiManager = $apiManager;
    }

    /**
     * Initialize feature manager
     *
     * @return void
     */
    public function initialize(): void {
        // Load feature settings from options
        $savedFeatures = get_option('wp_autoplugin_features', []);
        
        foreach ($savedFeatures as $key => $enabled) {
            if (isset($this->features[$key])) {
                $this->features[$key]['enabled'] = (bool) $enabled;
            }
        }

        // Allow filtering
        $this->features = apply_filters('wp_autoplugin_features', $this->features);
    }

    /**
     * Check if a feature is enabled
     *
     * @param string $feature Feature key
     * @return bool
     */
    public function isEnabled(string $feature): bool {
        return isset($this->features[$feature]) && $this->features[$feature]['enabled'];
    }

    /**
     * Get all features
     *
     * @return array
     */
    public function getFeatures(): array {
        return $this->features;
    }

    /**
     * Get enabled features
     *
     * @return array
     */
    public function getEnabledFeatures(): array {
        return array_filter($this->features, fn($f) => $f['enabled']);
    }

    /**
     * Enable a feature
     *
     * @param string $feature Feature key
     * @return bool
     */
    public function enable(string $feature): bool {
        if (isset($this->features[$feature])) {
            $this->features[$feature]['enabled'] = true;
            return $this->saveFeatures();
        }
        return false;
    }

    /**
     * Disable a feature
     *
     * @param string $feature Feature key
     * @return bool
     */
    public function disable(string $feature): bool {
        if (isset($this->features[$feature])) {
            $this->features[$feature]['enabled'] = false;
            return $this->saveFeatures();
        }
        return false;
    }

    /**
     * Save features to database
     *
     * @return bool
     */
    private function saveFeatures(): bool {
        $toSave = [];
        foreach ($this->features as $key => $feature) {
            $toSave[$key] = $feature['enabled'];
        }
        return update_option('wp_autoplugin_features', $toSave);
    }

    /**
     * Register a new feature
     *
     * @param string $key Feature key
     * @param array $feature Feature data
     * @return self
     */
    public function registerFeature(string $key, array $feature): self {
        $this->features[$key] = array_merge([
            'name' => $key,
            'description' => '',
            'enabled' => true
        ], $feature);
        
        return $this;
    }
}
