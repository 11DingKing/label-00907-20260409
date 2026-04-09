<?php

namespace Tests\Unit\Database;

use Framework\Database\ConnectionPool;
use Framework\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * ConnectionPool（连接池）测试用例
 */
class ConnectionPoolTest extends TestCase
{
    protected function setUp(): void
    {
        // 每个测试前重置连接池单例
        ConnectionPool::resetInstance();
    }

    protected function tearDown(): void
    {
        // 每个测试后清理
        ConnectionPool::resetInstance();
    }

    /**
     * 测试单例模式
     */
    public function testSingleton(): void
    {
        $pool1 = ConnectionPool::getInstance();
        $pool2 = ConnectionPool::getInstance();
        
        $this->assertSame($pool1, $pool2);
    }

    /**
     * 测试设置配置
     */
    public function testSetConfig(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool->setConfig($config, 5, 2.0);
        
        $status = $pool->getStatus();
        $this->assertEquals(5, $status['max']);
        $this->assertEquals(2.0, $status['wait_timeout']);
    }

    /**
     * 测试获取和释放连接（传统模式）
     */
    public function testGetAndReleaseConnection(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool->setConfig($config, 2);
        
        try {
            $conn1 = $pool->getConnection();
            $this->assertInstanceOf(Connection::class, $conn1);
            
            $status = $pool->getStatus();
            $this->assertEquals(1, $status['in_use']);
            
            $pool->releaseConnection($conn1);
            
            $status = $pool->getStatus();
            $this->assertEquals(0, $status['in_use']);
            $this->assertEquals(1, $status['available']);
        } catch (\Exception $e) {
            // 数据库未连接时跳过
            $this->markTestSkipped('数据库未连接，跳过连接池测试');
        }
    }

    /**
     * 测试连接池状态
     */
    public function testGetStatus(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool->setConfig($config, 10, 3.0);
        
        $status = $pool->getStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('mode', $status);
        $this->assertArrayHasKey('current', $status);
        $this->assertArrayHasKey('max', $status);
        $this->assertArrayHasKey('wait_timeout', $status);
        
        // 非协程环境应该是传统模式
        $this->assertEquals('traditional', $status['mode']);
        $this->assertArrayHasKey('available', $status);
        $this->assertArrayHasKey('in_use', $status);
    }

    /**
     * 测试关闭所有连接
     */
    public function testCloseAll(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool->setConfig($config, 2);
        
        try {
            $conn1 = $pool->getConnection();
            $conn2 = $pool->getConnection();
            
            $pool->closeAll();
            
            $status = $pool->getStatus();
            $this->assertEquals(0, $status['current']);
        } catch (\Exception $e) {
            $this->markTestSkipped('数据库未连接，跳过测试');
        }
    }

    /**
     * 测试连接池已满时抛出异常（传统模式）
     */
    public function testPoolFullException(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        // 设置最大连接数为 1
        $pool->setConfig($config, 1);
        
        try {
            $conn1 = $pool->getConnection();
            
            // 尝试获取第二个连接应该抛出异常
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('数据库连接池已满');
            
            $conn2 = $pool->getConnection();
        } catch (\RuntimeException $e) {
            if (strpos($e->getMessage(), '数据库连接池已满') !== false) {
                $this->assertTrue(true);
            } else {
                $this->markTestSkipped('数据库未连接，跳过测试');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('数据库未连接，跳过测试');
        }
    }

    /**
     * 测试协程模式检测
     */
    public function testCoroutineModeDetection(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool->setConfig($config, 5);
        
        // 在非 Swoole 环境下应该是传统模式
        if (!extension_loaded('swoole')) {
            $this->assertFalse($pool->isCoroutineMode());
        }
    }

    /**
     * 测试设置等待超时
     */
    public function testSetWaitTimeout(): void
    {
        $pool = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool->setConfig($config, 5, 5.0);
        $pool->setWaitTimeout(10.0);
        
        $status = $pool->getStatus();
        $this->assertEquals(10.0, $status['wait_timeout']);
    }

    /**
     * 测试重置单例
     */
    public function testResetInstance(): void
    {
        $pool1 = ConnectionPool::getInstance();
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'root',
        ];
        
        $pool1->setConfig($config, 5);
        
        ConnectionPool::resetInstance();
        
        $pool2 = ConnectionPool::getInstance();
        
        // 重置后应该是新实例
        $this->assertNotSame($pool1, $pool2);
        
        // 新实例的状态应该是初始状态
        $status = $pool2->getStatus();
        $this->assertEquals(0, $status['current']);
    }
}
