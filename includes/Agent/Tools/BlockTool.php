<?php
/**
 * Block Tool - Generates Gutenberg block structures
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
 * Block Tool Class
 *
 * Generates Gutenberg block HTML/JSON structures for layouts.
 */
class BlockTool extends AbstractTool {
    /**
     * Tool name
     *
     * @var string
     */
    protected string $name = 'blocks';

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
        return __('Generate Gutenberg block structures for page layouts. Can create hero sections, call-to-action blocks, columns, galleries, and other common layout patterns.', 'wp-autoplugin');
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
                    'enum' => ['generate', 'list_patterns'],
                    'description' => __('Action to perform', 'wp-autoplugin')
                ],
                'pattern' => [
                    'type' => 'string',
                    'enum' => ['hero', 'cta', 'features', 'columns', 'testimonial', 'gallery', 'pricing', 'team', 'contact', 'custom'],
                    'description' => __('Block pattern to generate', 'wp-autoplugin')
                ],
                'content' => [
                    'type' => 'object',
                    'description' => __('Content for the block pattern', 'wp-autoplugin'),
                    'properties' => [
                        'heading' => ['type' => 'string'],
                        'subheading' => ['type' => 'string'],
                        'text' => ['type' => 'string'],
                        'button_text' => ['type' => 'string'],
                        'button_url' => ['type' => 'string'],
                        'image_url' => ['type' => 'string'],
                        'items' => ['type' => 'array']
                    ]
                ],
                'style' => [
                    'type' => 'object',
                    'description' => __('Style options for the block', 'wp-autoplugin'),
                    'properties' => [
                        'background_color' => ['type' => 'string'],
                        'text_color' => ['type' => 'string'],
                        'alignment' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                        'padding' => ['type' => 'string']
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
            case 'generate':
                return $this->generateBlock($params);
            case 'list_patterns':
                return $this->listPatterns();
            default:
                return $this->error(
                    sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action),
                    'invalid_action'
                );
        }
    }

    /**
     * Generate a block pattern
     *
     * @param array $params Parameters
     * @return array Result
     */
    private function generateBlock(array $params): array {
        $pattern = $params['pattern'] ?? 'custom';
        $content = $params['content'] ?? [];
        $style = $params['style'] ?? [];

        $blockHtml = '';

        switch ($pattern) {
            case 'hero':
                $blockHtml = $this->generateHeroBlock($content, $style);
                break;
            case 'cta':
                $blockHtml = $this->generateCtaBlock($content, $style);
                break;
            case 'features':
                $blockHtml = $this->generateFeaturesBlock($content, $style);
                break;
            case 'columns':
                $blockHtml = $this->generateColumnsBlock($content, $style);
                break;
            case 'testimonial':
                $blockHtml = $this->generateTestimonialBlock($content, $style);
                break;
            case 'gallery':
                $blockHtml = $this->generateGalleryBlock($content, $style);
                break;
            case 'pricing':
                $blockHtml = $this->generatePricingBlock($content, $style);
                break;
            case 'team':
                $blockHtml = $this->generateTeamBlock($content, $style);
                break;
            case 'contact':
                $blockHtml = $this->generateContactBlock($content, $style);
                break;
            default:
                $blockHtml = $this->generateCustomBlock($content, $style);
        }

        return $this->success([
            'pattern' => $pattern,
            'block_html' => $blockHtml,
            'usage_note' => __('Copy this block HTML into the post/page content field or use the content tool to create content with this block.', 'wp-autoplugin')
        ]);
    }

    /**
     * Generate hero section block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateHeroBlock(array $content, array $style): string {
        $heading = esc_html($content['heading'] ?? 'Welcome to Our Site');
        $subheading = esc_html($content['subheading'] ?? 'We help you achieve your goals');
        $buttonText = esc_html($content['button_text'] ?? 'Get Started');
        $buttonUrl = esc_url($content['button_url'] ?? '#');
        $bgColor = $style['background_color'] ?? '#1e1e1e';
        $textColor = $style['text_color'] ?? '#ffffff';
        $alignment = $style['alignment'] ?? 'center';

        return <<<BLOCK
<!-- wp:cover {"overlayColor":"black","minHeight":500,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:500px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"{$alignment}","level":1,"style":{"color":{"text":"{$textColor}"}}} -->
<h1 class="wp-block-heading has-text-align-{$alignment}" style="color:{$textColor}">{$heading}</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"{$alignment}","style":{"color":{"text":"{$textColor}"}}} -->
<p class="has-text-align-{$alignment}" style="color:{$textColor}">{$subheading}</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"{$alignment}"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"vivid-cyan-blue"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-vivid-cyan-blue-background-color has-background wp-element-button" href="{$buttonUrl}">{$buttonText}</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->
BLOCK;
    }

    /**
     * Generate CTA block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateCtaBlock(array $content, array $style): string {
        $heading = esc_html($content['heading'] ?? 'Ready to Get Started?');
        $text = esc_html($content['text'] ?? 'Join thousands of satisfied customers today.');
        $buttonText = esc_html($content['button_text'] ?? 'Sign Up Now');
        $buttonUrl = esc_url($content['button_url'] ?? '#');
        $bgColor = $style['background_color'] ?? '#0073aa';

        return <<<BLOCK
<!-- wp:group {"style":{"color":{"background":"{$bgColor}"},"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:{$bgColor};padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center","style":{"color":{"text":"#ffffff"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="color:#ffffff">{$heading}</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#ffffff"}}} -->
<p class="has-text-align-center" style="color:#ffffff">{$text}</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"white","textColor":"black"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-black-color has-white-background-color has-text-color has-background wp-element-button" href="{$buttonUrl}">{$buttonText}</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->
BLOCK;
    }

    /**
     * Generate features block with columns
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateFeaturesBlock(array $content, array $style): string {
        $heading = esc_html($content['heading'] ?? 'Our Features');
        $items = $content['items'] ?? [
            ['title' => 'Feature 1', 'description' => 'Description of feature 1'],
            ['title' => 'Feature 2', 'description' => 'Description of feature 2'],
            ['title' => 'Feature 3', 'description' => 'Description of feature 3']
        ];

        $columnsHtml = '';
        foreach ($items as $item) {
            $title = esc_html($item['title'] ?? 'Feature');
            $description = esc_html($item['description'] ?? '');
            $columnsHtml .= <<<COLUMN
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">{$title}</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">{$description}</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

COLUMN;
        }

        return <<<BLOCK
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">{$heading}</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">{$columnsHtml}</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
BLOCK;
    }

    /**
     * Generate columns block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateColumnsBlock(array $content, array $style): string {
        $items = $content['items'] ?? [
            ['content' => 'Column 1 content'],
            ['content' => 'Column 2 content']
        ];

        $columnsHtml = '';
        foreach ($items as $item) {
            $columnContent = wp_kses_post($item['content'] ?? '');
            $columnsHtml .= <<<COLUMN
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>{$columnContent}</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

COLUMN;
        }

        return <<<BLOCK
<!-- wp:columns -->
<div class="wp-block-columns">{$columnsHtml}</div>
<!-- /wp:columns -->
BLOCK;
    }

    /**
     * Generate testimonial block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateTestimonialBlock(array $content, array $style): string {
        $quote = esc_html($content['text'] ?? 'This product changed my life! Highly recommended.');
        $author = esc_html($content['author'] ?? 'John Doe');
        $role = esc_html($content['role'] ?? 'Happy Customer');

        return <<<BLOCK
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}},"color":{"background":"#f5f5f5"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#f5f5f5;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:quote {"align":"center"} -->
<blockquote class="wp-block-quote has-text-align-center"><!-- wp:paragraph {"style":{"typography":{"fontSize":"1.2em"}}} -->
<p style="font-size:1.2em">"{$quote}"</p>
<!-- /wp:paragraph --><cite><strong>{$author}</strong><br>{$role}</cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:group -->
BLOCK;
    }

    /**
     * Generate gallery block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateGalleryBlock(array $content, array $style): string {
        $columns = $content['columns'] ?? 3;
        $images = $content['images'] ?? [];

        $imagesHtml = '';
        foreach ($images as $image) {
            $url = esc_url($image['url'] ?? '');
            $alt = esc_attr($image['alt'] ?? '');
            if ($url) {
                $imagesHtml .= <<<IMAGE
<!-- wp:image -->
<figure class="wp-block-image"><img src="{$url}" alt="{$alt}"/></figure>
<!-- /wp:image -->

IMAGE;
            }
        }

        if (empty($imagesHtml)) {
            return <<<BLOCK
<!-- wp:gallery {"columns":{$columns},"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-{$columns} is-cropped"><!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><em>Add your images here</em></p>
<!-- /wp:paragraph --></figure>
<!-- /wp:gallery -->
BLOCK;
        }

        return <<<BLOCK
<!-- wp:gallery {"columns":{$columns},"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-{$columns} is-cropped">{$imagesHtml}</figure>
<!-- /wp:gallery -->
BLOCK;
    }

    /**
     * Generate pricing block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generatePricingBlock(array $content, array $style): string {
        $heading = esc_html($content['heading'] ?? 'Pricing Plans');
        $items = $content['items'] ?? [
            ['name' => 'Basic', 'price' => '$9/mo', 'features' => ['Feature 1', 'Feature 2']],
            ['name' => 'Pro', 'price' => '$29/mo', 'features' => ['All Basic features', 'Feature 3', 'Feature 4']],
            ['name' => 'Enterprise', 'price' => '$99/mo', 'features' => ['All Pro features', 'Feature 5', 'Priority Support']]
        ];

        $columnsHtml = '';
        foreach ($items as $item) {
            $name = esc_html($item['name'] ?? 'Plan');
            $price = esc_html($item['price'] ?? '$0');
            $features = $item['features'] ?? [];
            
            $featuresHtml = '';
            foreach ($features as $feature) {
                $featuresHtml .= '<li>' . esc_html($feature) . '</li>';
            }

            $columnsHtml .= <<<COLUMN
<!-- wp:column {"style":{"border":{"width":"1px","color":"#e0e0e0"},"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
<div class="wp-block-column has-border-color" style="border-color:#e0e0e0;border-width:1px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:heading {"textAlign":"center","level":3} -->
<h3 class="wp-block-heading has-text-align-center">{$name}</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"2em","fontStyle":"normal","fontWeight":"700"}}} -->
<p class="has-text-align-center" style="font-size:2em;font-style:normal;font-weight:700">{$price}</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">{$featuresHtml}</ul>
<!-- /wp:list -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Choose Plan</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

COLUMN;
        }

        return <<<BLOCK
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">{$heading}</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">{$columnsHtml}</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
BLOCK;
    }

    /**
     * Generate team block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateTeamBlock(array $content, array $style): string {
        $heading = esc_html($content['heading'] ?? 'Our Team');
        $items = $content['items'] ?? [
            ['name' => 'Jane Doe', 'role' => 'CEO', 'bio' => 'Leading the company vision'],
            ['name' => 'John Smith', 'role' => 'CTO', 'bio' => 'Technology innovator']
        ];

        $columnsHtml = '';
        foreach ($items as $item) {
            $name = esc_html($item['name'] ?? 'Team Member');
            $role = esc_html($item['role'] ?? 'Role');
            $bio = esc_html($item['bio'] ?? '');

            $columnsHtml .= <<<COLUMN
<!-- wp:column {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","right":"var:preset|spacing|30","bottom":"var:preset|spacing|30","left":"var:preset|spacing|30"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--30)"><!-- wp:heading {"textAlign":"center","level":3} -->
<h3 class="wp-block-heading has-text-align-center">{$name}</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#666666"}}} -->
<p class="has-text-align-center" style="color:#666666"><strong>{$role}</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">{$bio}</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

COLUMN;
        }

        return <<<BLOCK
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">{$heading}</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">{$columnsHtml}</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
BLOCK;
    }

    /**
     * Generate contact block
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateContactBlock(array $content, array $style): string {
        $heading = esc_html($content['heading'] ?? 'Contact Us');
        $text = esc_html($content['text'] ?? 'We\'d love to hear from you. Send us a message!');
        $email = esc_html($content['email'] ?? 'contact@example.com');
        $phone = esc_html($content['phone'] ?? '');
        $address = esc_html($content['address'] ?? '');

        $contactInfo = '';
        if ($email) {
            $contactInfo .= "<p><strong>Email:</strong> {$email}</p>\n";
        }
        if ($phone) {
            $contactInfo .= "<p><strong>Phone:</strong> {$phone}</p>\n";
        }
        if ($address) {
            $contactInfo .= "<p><strong>Address:</strong> {$address}</p>\n";
        }

        return <<<BLOCK
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-light-gray-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">{$heading}</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">{$text}</p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"400px"}} -->
<div class="wp-block-group">{$contactInfo}</div>
<!-- /wp:group --></div>
<!-- /wp:group -->
BLOCK;
    }

    /**
     * Generate custom block from content
     *
     * @param array $content Content data
     * @param array $style Style options
     * @return string Block HTML
     */
    private function generateCustomBlock(array $content, array $style): string {
        $heading = isset($content['heading']) ? esc_html($content['heading']) : '';
        $text = isset($content['text']) ? wp_kses_post($content['text']) : '';
        $alignment = $style['alignment'] ?? 'left';

        $html = '';

        if ($heading) {
            $html .= <<<HEADING
<!-- wp:heading {"textAlign":"{$alignment}"} -->
<h2 class="wp-block-heading has-text-align-{$alignment}">{$heading}</h2>
<!-- /wp:heading -->

HEADING;
        }

        if ($text) {
            $html .= <<<PARAGRAPH
<!-- wp:paragraph {"align":"{$alignment}"} -->
<p class="has-text-align-{$alignment}">{$text}</p>
<!-- /wp:paragraph -->

PARAGRAPH;
        }

        if (isset($content['button_text'])) {
            $buttonText = esc_html($content['button_text']);
            $buttonUrl = esc_url($content['button_url'] ?? '#');
            $html .= <<<BUTTON
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"{$alignment}"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$buttonUrl}">{$buttonText}</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

BUTTON;
        }

        return $html ?: '<!-- wp:paragraph --><p>Custom content block</p><!-- /wp:paragraph -->';
    }

    /**
     * List available block patterns
     *
     * @return array Result
     */
    private function listPatterns(): array {
        return $this->success([
            'patterns' => [
                [
                    'name' => 'hero',
                    'description' => __('Full-width hero section with heading, subheading, and CTA button', 'wp-autoplugin'),
                    'content_fields' => ['heading', 'subheading', 'button_text', 'button_url']
                ],
                [
                    'name' => 'cta',
                    'description' => __('Call-to-action section with colored background', 'wp-autoplugin'),
                    'content_fields' => ['heading', 'text', 'button_text', 'button_url']
                ],
                [
                    'name' => 'features',
                    'description' => __('Feature grid with icons and descriptions', 'wp-autoplugin'),
                    'content_fields' => ['heading', 'items (array with title and description)']
                ],
                [
                    'name' => 'columns',
                    'description' => __('Multi-column layout', 'wp-autoplugin'),
                    'content_fields' => ['items (array with content)']
                ],
                [
                    'name' => 'testimonial',
                    'description' => __('Customer testimonial quote block', 'wp-autoplugin'),
                    'content_fields' => ['text', 'author', 'role']
                ],
                [
                    'name' => 'gallery',
                    'description' => __('Image gallery grid', 'wp-autoplugin'),
                    'content_fields' => ['columns', 'images (array with url and alt)']
                ],
                [
                    'name' => 'pricing',
                    'description' => __('Pricing table with plans', 'wp-autoplugin'),
                    'content_fields' => ['heading', 'items (array with name, price, features)']
                ],
                [
                    'name' => 'team',
                    'description' => __('Team members grid', 'wp-autoplugin'),
                    'content_fields' => ['heading', 'items (array with name, role, bio)']
                ],
                [
                    'name' => 'contact',
                    'description' => __('Contact information section', 'wp-autoplugin'),
                    'content_fields' => ['heading', 'text', 'email', 'phone', 'address']
                ]
            ]
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
            case 'generate':
                $pattern = $params['pattern'] ?? 'custom';
                return sprintf(
                    __('Will generate a "%s" Gutenberg block pattern. This is a read-only operation that returns block HTML.', 'wp-autoplugin'),
                    $pattern
                );

            case 'list_patterns':
                return __('Will list all available block patterns (read-only operation)', 'wp-autoplugin');

            default:
                return sprintf(__('Unknown action: %s', 'wp-autoplugin'), $action);
        }
    }
}
