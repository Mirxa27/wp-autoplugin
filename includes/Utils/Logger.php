<?php
/**
 * Logger Utility
 *
 * @package WP_Autoplugin\Utils
 * @since 2.0.0
 */

namespace WP_Autoplugin\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Class
 */
class Logger {
    /**
     * Log levels
     *
     * @var array
     */
    private array $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];

    /**
     * Current log level
     *
     * @var string
     */
    private string $logLevel;

    /**
     * Log file path
     *
     * @var string
     */
    private string $logFile;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logLevel = get_option('wp_autoplugin_log_level', 'info');
        $this->logFile = WP_CONTENT_DIR . '/wp-autoplugin-logs/' . date('Y-m-d') . '.log';
        $this->ensureLogDirectory();
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void {
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);
            
            // Add .htaccess to prevent direct access
            $htaccess = $logDir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
    }

    /**
     * Log a message
     *
     * @param string $message
     * @param string $level
     * @param array $context
     */
    public function log(string $message, string $level = 'info', array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        $entry = $this->formatLogEntry($message, $level, $context);
        
        // Write to file
        error_log($entry, 3, $this->logFile);
        
        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP-Autoplugin] ' . $entry);
        }

        // Trigger action for external logging
        do_action('wp_autoplugin_log', $message, $level, $context);
    }

    /**
     * Check if should log based on level
     */
    private function shouldLog(string $level): bool {
        $currentLevelValue = $this->levels[$this->logLevel] ?? 1;
        $messageLevelValue = $this->levels[$level] ?? 1;
        
        return $messageLevelValue >= $currentLevelValue;
    }

    /**
     * Format log entry
     */
    private function formatLogEntry(string $message, string $level, array $context): string {
        $timestamp = current_time('Y-m-d H:i:s');
        $level = strtoupper($level);
        
        $entry = "[{$timestamp}] {$level}: {$message}";
        
        if (!empty($context)) {
            $entry .= ' ' . json_encode($context);
        }
        
        $entry .= PHP_EOL;
        
        return $entry;
    }

    /**
     * Debug log
     */
    public function debug(string $message, array $context = []): void {
        $this->log($message, 'debug', $context);
    }

    /**
     * Info log
     */
    public function info(string $message, array $context = []): void {
        $this->log($message, 'info', $context);
    }

    /**
     * Warning log
     */
    public function warning(string $message, array $context = []): void {
        $this->log($message, 'warning', $context);
    }

    /**
     * Error log
     */
    public function error(string $message, array $context = []): void {
        $this->log($message, 'error', $context);
        
        // Store error for admin notice
        $errors = get_transient('wp_autoplugin_errors') ?: [];
        $errors[] = [
            'message' => $message,
            'time' => current_time('timestamp'),
            'context' => $context
        ];
        
        // Keep only last 10 errors
        $errors = array_slice($errors, -10);
        set_transient('wp_autoplugin_errors', $errors, HOUR_IN_SECONDS);
    }

    /**
     * Critical log
     */
    public function critical(string $message, array $context = []): void {
        $this->log($message, 'critical', $context);
        
        // Send email notification for critical errors
        if (get_option('wp_autoplugin_email_critical_errors', false)) {
            $this->sendCriticalErrorEmail($message, $context);
        }
    }

    /**
     * Send critical error email
     */
    private function sendCriticalErrorEmail(string $message, array $context): void {
        $to = get_option('admin_email');
        $subject = sprintf('[%s] WP-Autoplugin Critical Error', get_bloginfo('name'));
        
        $body = "A critical error occurred in WP-Autoplugin:\n\n";
        $body .= "Message: {$message}\n";
        $body .= "Time: " . current_time('Y-m-d H:i:s') . "\n";
        $body .= "Site: " . home_url() . "\n";
        
        if (!empty($context)) {
            $body .= "\nContext:\n" . print_r($context, true);
        }
        
        wp_mail($to, $subject, $body);
    }

    /**
     * Get recent errors
     */
    public function getRecentErrors(int $limit = 10): array {
        return get_transient('wp_autoplugin_errors') ?: [];
    }

    /**
     * Clear error log
     */
    public function clearErrors(): void {
        delete_transient('wp_autoplugin_errors');
    }

    /**
     * Get log file path
     */
    public function getLogFile(): string {
        return $this->logFile;
    }

    /**
     * Get all log files
     */
    public function getLogFiles(): array {
        $logDir = dirname($this->logFile);
        $files = glob($logDir . '/*.log');
        
        // Sort by date (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files;
    }

    /**
     * Clean old log files
     */
    public function cleanOldLogs(int $daysToKeep = 30): void {
        $files = $this->getLogFiles();
        $cutoffTime = time() - ($daysToKeep * DAY_IN_SECONDS);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
}