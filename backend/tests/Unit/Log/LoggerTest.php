<?php

namespace Tests\Unit\Log;

use Framework\Log\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Logger（日志管理）测试用例
 */
class LoggerTest extends TestCase
{
    private Logger $logger;
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/framework_log_test';
        $this->logger = Logger::getInstance();
        $this->logger->setPath($this->logPath);
    }

    protected function tearDown(): void
    {
        // 清理日志文件
        $files = glob($this->logPath . '/*.log');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->logPath)) {
            rmdir($this->logPath);
        }
    }

    /**
     * 测试单例模式
     */
    public function testSingleton(): void
    {
        $logger1 = Logger::getInstance();
        $logger2 = Logger::getInstance();
        
        $this->assertSame($logger1, $logger2);
    }

    /**
     * 测试日志级别
     */
    public function testLogLevels(): void
    {
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->notice('Notice message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        $this->logger->critical('Critical message');
        $this->logger->alert('Alert message');
        $this->logger->emergency('Emergency message');
        
        $logFile = $this->logPath . '/app-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);
        
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('ERROR', $content);
    }

    /**
     * 测试上下文替换
     */
    public function testContextInterpolation(): void
    {
        $this->logger->info('User {username} logged in from {ip}', [
            'username' => 'admin',
            'ip' => '127.0.0.1',
        ]);
        
        $logFile = $this->logPath . '/app-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);
        
        $this->assertStringContainsString('User admin logged in from 127.0.0.1', $content);
    }

    /**
     * 测试日志通道
     */
    public function testChannel(): void
    {
        $this->logger->channel('sql')->info('Query executed');
        
        $logFile = $this->logPath . '/sql-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);
        
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('sql.INFO', $content);
    }

    /**
     * 测试日志级别过滤
     */
    public function testLevelFilter(): void
    {
        $logger = clone $this->logger;
        $logger->setLevel(Logger::ERROR);
        
        // 创建新的日志文件
        $logger->channel('filtered')->debug('Should not appear');
        $logger->channel('filtered')->error('Should appear');
        
        $logFile = $this->logPath . '/filtered-' . date('Y-m-d') . '.log';
        
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $this->assertStringNotContainsString('Should not appear', $content);
            $this->assertStringContainsString('Should appear', $content);
        }
    }

    /**
     * 测试自定义处理器
     */
    public function testCustomHandler(): void
    {
        $handled = false;
        
        $this->logger->addHandler(function ($record) use (&$handled) {
            $handled = true;
            $this->assertEquals('INFO', $record['level']);
        });
        
        $this->logger->info('Test handler');
        
        $this->assertTrue($handled);
    }
}
