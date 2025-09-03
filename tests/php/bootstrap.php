<?php
/**
 * PHPUnit bootstrap file
 */

// Load composer autoloader
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

// Define test constants
define('WP_AUTOPLUGIN_TESTS', true);
define('ABSPATH', '/tmp/wordpress/');

// Mock WordPress functions if not in WordPress test environment
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        // Mock implementation for testing
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true, 'data' => []])
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Define WP_Error class if not exists
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->add($code, $message, $data);
            }
        }
        
        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
        }
        
        public function get_error_message() {
            $codes = array_keys($this->errors);
            return $this->errors[$codes[0]][0] ?? '';
        }
    }
}