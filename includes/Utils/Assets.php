<?php
/**
 * Assets Manager
 *
 * @package WP_Autoplugin\Utils
 * @since 2.0.0
 */

namespace WP_Autoplugin\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets Class
 */
class Assets {
    /**
     * Asset manifest
     *
     * @var array
     */
    private array $manifest = [];

    /**
     * Asset version
     *
     * @var string
     */
    private string $version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->version = WP_AUTOPLUGIN_VERSION;
        $this->loadManifest();
    }

    /**
     * Load asset manifest
     */
    private function loadManifest(): void {
        $manifestFile = WP_AUTOPLUGIN_DIR . 'assets/dist/manifest.json';
        
        if (file_exists($manifestFile)) {
            $this->manifest = json_decode(file_get_contents($manifestFile), true) ?: [];
        }
    }

    /**
     * Enqueue script
     */
    public function enqueueScript(
        string $handle,
        string $src,
        array $deps = [],
        bool $inFooter = true,
        array $localize = []
    ): void {
        $src = $this->getAssetUrl($src);
        $version = $this->getAssetVersion($src);
        
        wp_enqueue_script($handle, $src, $deps, $version, $inFooter);
        
        if (!empty($localize)) {
            wp_localize_script($handle, $localize['object'], $localize['data']);
        }
    }

    /**
     * Enqueue style
     */
    public function enqueueStyle(
        string $handle,
        string $src,
        array $deps = [],
        string $media = 'all'
    ): void {
        $src = $this->getAssetUrl($src);
        $version = $this->getAssetVersion($src);
        
        wp_enqueue_style($handle, $src, $deps, $version, $media);
    }

    /**
     * Register script
     */
    public function registerScript(
        string $handle,
        string $src,
        array $deps = [],
        bool $inFooter = true
    ): void {
        $src = $this->getAssetUrl($src);
        $version = $this->getAssetVersion($src);
        
        wp_register_script($handle, $src, $deps, $version, $inFooter);
    }

    /**
     * Register style
     */
    public function registerStyle(
        string $handle,
        string $src,
        array $deps = [],
        string $media = 'all'
    ): void {
        $src = $this->getAssetUrl($src);
        $version = $this->getAssetVersion($src);
        
        wp_register_style($handle, $src, $deps, $version, $media);
    }

    /**
     * Get asset URL
     */
    private function getAssetUrl(string $path): string {
        // Check if it's already a full URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }
        
        // Check manifest for hashed filename
        if (isset($this->manifest[$path])) {
            $path = $this->manifest[$path];
        }
        
        // Build full URL
        return WP_AUTOPLUGIN_URL . 'assets/' . ltrim($path, '/');
    }

    /**
     * Get asset version
     */
    private function getAssetVersion(string $src): string {
        // Use file modification time in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $filePath = str_replace(WP_AUTOPLUGIN_URL, WP_AUTOPLUGIN_DIR, $src);
            if (file_exists($filePath)) {
                return (string) filemtime($filePath);
            }
        }
        
        return $this->version;
    }

    /**
     * Add inline script
     */
    public function addInlineScript(string $handle, string $data, string $position = 'after'): bool {
        return wp_add_inline_script($handle, $data, $position);
    }

    /**
     * Add inline style
     */
    public function addInlineStyle(string $handle, string $data): bool {
        return wp_add_inline_style($handle, $data);
    }

    /**
     * Preload asset
     */
    public function preloadAsset(string $href, string $as = 'script'): void {
        add_action('wp_head', function() use ($href, $as) {
            printf(
                '<link rel="preload" href="%s" as="%s"%s>',
                esc_url($this->getAssetUrl($href)),
                esc_attr($as),
                $as === 'font' ? ' crossorigin' : ''
            );
        });
    }

    /**
     * Get inline SVG
     */
    public function getInlineSvg(string $filename): string {
        $path = WP_AUTOPLUGIN_DIR . 'assets/images/' . $filename;
        
        if (!file_exists($path)) {
            return '';
        }
        
        $svg = file_get_contents($path);
        
        // Remove XML declaration
        $svg = preg_replace('/<\?xml.*\?>/', '', $svg);
        
        // Add class for styling
        $svg = str_replace('<svg', '<svg class="wp-autoplugin-svg"', $svg);
        
        return $svg;
    }

    /**
     * Get asset dependencies
     */
    public function getAssetDependencies(string $handle): array {
        $depsFile = WP_AUTOPLUGIN_DIR . 'assets/dist/' . $handle . '.asset.php';
        
        if (file_exists($depsFile)) {
            $asset = require $depsFile;
            return $asset['dependencies'] ?? [];
        }
        
        return [];
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueueBlockEditorAssets(): void {
        add_action('enqueue_block_editor_assets', function() {
            $this->enqueueScript(
                'wp-autoplugin-block-editor',
                'dist/js/block-editor.bundle.js',
                ['wp-blocks', 'wp-element', 'wp-editor']
            );
            
            $this->enqueueStyle(
                'wp-autoplugin-block-editor',
                'dist/css/block-editor.css'
            );
        });
    }

    /**
     * Get critical CSS
     */
    public function getCriticalCss(): string {
        $criticalFile = WP_AUTOPLUGIN_DIR . 'assets/dist/css/critical.css';
        
        if (file_exists($criticalFile)) {
            return file_get_contents($criticalFile);
        }
        
        return '';
    }

    /**
     * Load critical CSS inline
     */
    public function loadCriticalCss(): void {
        $critical = $this->getCriticalCss();
        
        if (!empty($critical)) {
            add_action('wp_head', function() use ($critical) {
                echo '<style id="wp-autoplugin-critical">' . wp_strip_all_tags( $critical ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS content is stripped of tags
            }, 1);
        }
    }

    /**
     * Defer script loading
     */
    public function deferScript(string $handle): void {
        add_filter('script_loader_tag', function($tag, $scriptHandle) use ($handle) {
            if ($scriptHandle === $handle && !str_contains($tag, 'defer')) {
                return str_replace(' src', ' defer src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Async script loading
     */
    public function asyncScript(string $handle): void {
        add_filter('script_loader_tag', function($tag, $scriptHandle) use ($handle) {
            if ($scriptHandle === $handle && !str_contains($tag, 'async')) {
                return str_replace(' src', ' async src', $tag);
            }
            return $tag;
        }, 10, 2);
    }
}