<?php
/**
 * Logger Tests
 */

namespace WP_Autoplugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WP_Autoplugin\Utils\Logger;

class LoggerTest extends TestCase {
    
    private Logger $logger;
    private string $testLogFile;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Define constants if not already defined
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', sys_get_temp_dir());
        }
        
        $this->logger = new Logger();
        $this->testLogFile = $this->logger->getLogFile();
    }
    
    protected function tearDown(): void {
        // Clean up test log file
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
        
        parent::tearDown();
    }
    
    public function testLogCreatesFile() {
        $this->logger->info('Test message');
        
        $this->assertFileExists($this->testLogFile);
    }
    
    public function testLogWritesCorrectFormat() {
        $message = 'Test log message';
        $this->logger->info($message);
        
        $logContent = file_get_contents($this->testLogFile);
        
        $this->assertStringContainsString('INFO:', $logContent);
        $this->assertStringContainsString($message, $logContent);
    }
    
    public function testLogLevels() {
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        $this->logger->critical('Critical message');
        
        $logContent = file_get_contents($this->testLogFile);
        
        // Default log level is 'info', so debug shouldn't be logged
        $this->assertStringNotContainsString('DEBUG:', $logContent);
        $this->assertStringContainsString('INFO:', $logContent);
        $this->assertStringContainsString('WARNING:', $logContent);
        $this->assertStringContainsString('ERROR:', $logContent);
        $this->assertStringContainsString('CRITICAL:', $logContent);
    }
    
    public function testLogWithContext() {
        $context = ['user_id' => 123, 'action' => 'test'];
        $this->logger->info('Test with context', $context);
        
        $logContent = file_get_contents($this->testLogFile);
        
        $this->assertStringContainsString(json_encode($context), $logContent);
    }
    
    public function testGetRecentErrors() {
        // Mock the transient function
        if (!function_exists('get_transient')) {
            function get_transient($key) {
                return [];
            }
        }
        
        $errors = $this->logger->getRecentErrors();
        $this->assertIsArray($errors);
    }
}