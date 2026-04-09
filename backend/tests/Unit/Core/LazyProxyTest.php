<?php

namespace Tests\Unit\Core;

use Framework\Core\LazyProxy;
use PHPUnit\Framework\TestCase;

/**
 * LazyProxy（懒加载代理）测试用例
 */
class LazyProxyTest extends TestCase
{
    /**
     * 测试懒加载延迟实例化
     */
    public function testLazyInstantiation(): void
    {
        $instantiated = false;
        
        $proxy = new LazyProxy(TestService::class, function () use (&$instantiated) {
            $instantiated = true;
            return new TestService();
        });
        
        // 创建代理时不应实例化
        $this->assertFalse($instantiated);
        $this->assertFalse($proxy->isInitialized());
        
        // 获取实例时才实例化
        $instance = $proxy->getInstance();
        $this->assertTrue($instantiated);
        $this->assertTrue($proxy->isInitialized());
        $this->assertInstanceOf(TestService::class, $instance);
    }

    /**
     * 测试方法调用触发实例化
     */
    public function testMethodCallTriggersInstantiation(): void
    {
        $instantiated = false;
        
        $proxy = new LazyProxy(TestService::class, function () use (&$instantiated) {
            $instantiated = true;
            return new TestService();
        });
        
        $this->assertFalse($instantiated);
        
        // 调用方法触发实例化
        $result = $proxy->getValue();
        
        $this->assertTrue($instantiated);
        $this->assertEquals('test_value', $result);
    }

    /**
     * 测试属性访问触发实例化
     */
    public function testPropertyAccessTriggersInstantiation(): void
    {
        $instantiated = false;
        
        $proxy = new LazyProxy(TestService::class, function () use (&$instantiated) {
            $instantiated = true;
            return new TestService();
        });
        
        $this->assertFalse($instantiated);
        
        // 访问属性触发实例化
        $value = $proxy->publicValue;
        
        $this->assertTrue($instantiated);
        $this->assertEquals('public_test', $value);
    }

    /**
     * 测试属性设置触发实例化
     */
    public function testPropertySetTriggersInstantiation(): void
    {
        $instantiated = false;
        
        $proxy = new LazyProxy(TestService::class, function () use (&$instantiated) {
            $instantiated = true;
            return new TestService();
        });
        
        $this->assertFalse($instantiated);
        
        // 设置属性触发实例化
        $proxy->publicValue = 'new_value';
        
        $this->assertTrue($instantiated);
        $this->assertEquals('new_value', $proxy->publicValue);
    }

    /**
     * 测试 isset 触发实例化
     */
    public function testIssetTriggersInstantiation(): void
    {
        $instantiated = false;
        
        $proxy = new LazyProxy(TestService::class, function () use (&$instantiated) {
            $instantiated = true;
            return new TestService();
        });
        
        $this->assertFalse($instantiated);
        
        // isset 触发实例化
        $exists = isset($proxy->publicValue);
        
        $this->assertTrue($instantiated);
        $this->assertTrue($exists);
    }

    /**
     * 测试获取类名
     */
    public function testGetClassName(): void
    {
        $proxy = new LazyProxy(TestService::class, function () {
            return new TestService();
        });
        
        $this->assertEquals(TestService::class, $proxy->getClassName());
    }

    /**
     * 测试单例行为（多次获取返回同一实例）
     */
    public function testSingletonBehavior(): void
    {
        $callCount = 0;
        
        $proxy = new LazyProxy(TestService::class, function () use (&$callCount) {
            $callCount++;
            return new TestService();
        });
        
        $instance1 = $proxy->getInstance();
        $instance2 = $proxy->getInstance();
        $instance3 = $proxy->getInstance();
        
        // 工厂只调用一次
        $this->assertEquals(1, $callCount);
        $this->assertSame($instance1, $instance2);
        $this->assertSame($instance2, $instance3);
    }
}

/**
 * 测试用服务类
 */
class TestService
{
    public string $publicValue = 'public_test';
    
    public function getValue(): string
    {
        return 'test_value';
    }
    
    public function process(string $input): string
    {
        return "processed: {$input}";
    }
}
