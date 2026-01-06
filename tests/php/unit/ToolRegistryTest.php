<?php
/**
 * Tool Registry Tests
 *
 * @package WP_Autoplugin\Tests\Unit
 */

namespace WP_Autoplugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WP_Autoplugin\Agent\ToolRegistry;
use WP_Autoplugin\Agent\ToolInterface;
use WP_Autoplugin\Agent\AbstractTool;

/**
 * Mock Tool for testing
 */
class MockTool extends AbstractTool {
    private string $name;
    private string $description;
    private bool $canExecute;
    private array $executeResult;

    public function __construct(
        string $name = 'mock_tool',
        string $description = 'A mock tool for testing',
        bool $canExecute = true,
        array $executeResult = ['success' => true, 'data' => 'test']
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->canExecute = $canExecute;
        $this->executeResult = $executeResult;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getParameterSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string'],
                'param2' => ['type' => 'integer']
            ],
            'required' => ['param1']
        ];
    }

    public function execute(array $params): array {
        return $this->executeResult;
    }

    public function dryRun(array $params): string {
        return 'Would execute mock tool with params: ' . json_encode($params);
    }

    public function userCanExecute(): bool {
        return $this->canExecute;
    }
}

/**
 * Tool Registry Test Class
 */
class ToolRegistryTest extends TestCase {

    private ToolRegistry $registry;

    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress functions
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }

        $this->registry = new ToolRegistry();
    }

    public function testRegisterTool(): void {
        $tool = new MockTool('test_tool');
        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('test_tool'));
    }

    public function testUnregisterTool(): void {
        $tool = new MockTool('test_tool');
        $this->registry->register($tool);
        
        $result = $this->registry->unregister('test_tool');
        
        $this->assertTrue($result);
        $this->assertFalse($this->registry->has('test_tool'));
    }

    public function testGetTool(): void {
        $tool = new MockTool('test_tool');
        $this->registry->register($tool);

        $retrieved = $this->registry->get('test_tool');
        
        $this->assertSame($tool, $retrieved);
    }

    public function testGetNonExistentToolReturnsNull(): void {
        $retrieved = $this->registry->get('nonexistent_tool');
        
        $this->assertNull($retrieved);
    }

    public function testExecuteTool(): void {
        $tool = new MockTool('test_tool', 'Test', true, [
            'success' => true,
            'data' => 'executed'
        ]);
        $this->registry->register($tool);

        $result = $this->registry->execute('test_tool', ['param1' => 'value']);

        $this->assertTrue($result['success']);
        $this->assertEquals('executed', $result['data']);
    }

    public function testExecuteNonExistentToolFails(): void {
        $result = $this->registry->execute('nonexistent_tool', []);

        $this->assertFalse($result['success']);
        $this->assertEquals('tool_not_found', $result['code']);
    }

    public function testDryRunMode(): void {
        $tool = new MockTool('test_tool');
        $this->registry->register($tool);

        $result = $this->registry->execute('test_tool', ['param1' => 'value'], true);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['dry_run']);
        $this->assertStringContainsString('Would execute', $result['description']);
    }

    public function testGetToolsSchema(): void {
        $tool = new MockTool('test_tool', 'Test description');
        $this->registry->register($tool);

        $schema = $this->registry->getToolsSchema();

        $this->assertCount(1, $schema);
        $this->assertEquals('function', $schema[0]['type']);
        $this->assertEquals('test_tool', $schema[0]['function']['name']);
        $this->assertEquals('Test description', $schema[0]['function']['description']);
    }

    public function testGetToolsList(): void {
        $tool1 = new MockTool('tool1', 'First tool');
        $tool2 = new MockTool('tool2', 'Second tool');
        
        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $list = $this->registry->getToolsList();

        $this->assertCount(2, $list);
        $this->assertArrayHasKey('tool1', $list);
        $this->assertArrayHasKey('tool2', $list);
        $this->assertEquals('First tool', $list['tool1']['description']);
    }

    public function testExecuteBatch(): void {
        $tool = new MockTool('test_tool', 'Test', true, [
            'success' => true,
            'data' => 'done'
        ]);
        $this->registry->register($tool);

        $actions = [
            ['tool' => 'test_tool', 'params' => ['param1' => 'value1']],
            ['tool' => 'test_tool', 'params' => ['param1' => 'value2']]
        ];

        $result = $this->registry->executeBatch($actions);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['executed']);
    }

    public function testHandleToolCall(): void {
        $tool = new MockTool('test_tool', 'Test', true, [
            'success' => true,
            'data' => 'handled'
        ]);
        $this->registry->register($tool);

        // Test OpenAI-style tool call format
        $toolCall = [
            'function' => [
                'name' => 'test_tool',
                'arguments' => json_encode(['param1' => 'value'])
            ]
        ];

        $result = $this->registry->handleToolCall($toolCall);

        $this->assertTrue($result['success']);
        $this->assertEquals('handled', $result['data']);
    }
}
