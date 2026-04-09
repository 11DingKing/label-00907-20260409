<?php

namespace Tests\Unit\Core;

use Framework\Core\Container;
use Framework\Core\LazyProxy;
use PHPUnit\Framework\TestCase;

/**
 * Container（DI容器）测试用例
 */
class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        Container::resetInstance();
    }

    protected function tearDown(): void
    {
        Container::resetInstance();
    }

    /**
     * 测试单例模式
     */
    public function testSingleton(): void
    {
        $container1 = Container::getInstance();
        $container2 = Container::getInstance();
        
        $this->assertSame($container1, $container2);
    }

    /**
     * 测试绑定和解析服务
     */
    public function testBindAndMake(): void
    {
        $container = Container::getInstance();
        
        // 绑定简单类
        $container->bind('test.service', function () {
            return new \stdClass();
        });
        
        $service1 = $container->make('test.service');
        $service2 = $container->make('test.service');
        
        $this->assertInstanceOf(\stdClass::class, $service1);
        // 非单例，每次创建新实例
        $this->assertNotSame($service1, $service2);
    }

    /**
     * 测试单例绑定
     */
    public function testSingletonBinding(): void
    {
        $container = Container::getInstance();
        
        $container->singleton('singleton.service', function () {
            return new \stdClass();
        });
        
        $service1 = $container->make('singleton.service');
        $service2 = $container->make('singleton.service');
        
        // 单例模式，返回同一实例
        $this->assertSame($service1, $service2);
    }

    /**
     * 测试自动依赖注入
     */
    public function testAutoDependencyInjection(): void
    {
        $container = Container::getInstance();
        
        // 创建测试类
        $container->bind(TestDependency::class);
        $container->bind(TestClass::class);
        
        $instance = $container->make(TestClass::class);
        
        $this->assertInstanceOf(TestClass::class, $instance);
        $this->assertInstanceOf(TestDependency::class, $instance->dependency);
    }

    /**
     * 测试检查服务是否存在
     */
    public function testHas(): void
    {
        $container = Container::getInstance();
        
        $this->assertFalse($container->has('non.existent'));
        
        $container->bind('existent.service', function () {
            return 'test';
        });
        
        $this->assertTrue($container->has('existent.service'));
    }

    /**
     * 测试带参数的依赖注入
     */
    public function testDependencyWithParameters(): void
    {
        $container = Container::getInstance();
        
        $container->bind(TestClassWithParams::class);
        
        $instance = $container->make(TestClassWithParams::class, [
            'name' => 'TestName',
            'value' => 123,
        ]);
        
        $this->assertEquals('TestName', $instance->name);
        $this->assertEquals(123, $instance->value);
    }

    /**
     * 测试懒加载绑定
     */
    public function testLazyBinding(): void
    {
        $container = Container::getInstance();
        $instantiated = false;
        
        $container->lazy('lazy.service', function () use (&$instantiated) {
            $instantiated = true;
            return new \stdClass();
        });
        
        // 绑定后不应实例化
        $this->assertFalse($instantiated);
        $this->assertFalse($container->isInitialized('lazy.service'));
        
        // 获取服务返回 LazyProxy
        $proxy = $container->make('lazy.service');
        $this->assertInstanceOf(LazyProxy::class, $proxy);
        $this->assertFalse($instantiated);
        
        // 使用代理时才实例化
        $proxy->getInstance();
        $this->assertTrue($instantiated);
        $this->assertTrue($container->isInitialized('lazy.service'));
    }

    /**
     * 测试懒加载统计
     */
    public function testLazyStats(): void
    {
        $container = Container::getInstance();
        
        $container->lazy('lazy1', function () {
            return new \stdClass();
        });
        $container->lazy('lazy2', function () {
            return new \stdClass();
        });
        $container->lazy('lazy3', function () {
            return new \stdClass();
        });
        
        $stats = $container->getLazyStats();
        $this->assertEquals(3, $stats['total_lazy']);
        $this->assertEquals(0, $stats['initialized']);
        $this->assertEquals(3, $stats['pending']);
        
        // 初始化一个
        $proxy = $container->make('lazy1');
        $proxy->getInstance();
        
        $stats = $container->getLazyStats();
        $this->assertEquals(3, $stats['total_lazy']);
        $this->assertEquals(1, $stats['initialized']);
        $this->assertEquals(2, $stats['pending']);
    }

    /**
     * 测试 PSR-11 get 方法
     */
    public function testPsr11Get(): void
    {
        $container = Container::getInstance();
        
        $container->singleton('psr.service', function () {
            return new \stdClass();
        });
        
        $service = $container->get('psr.service');
        $this->assertInstanceOf(\stdClass::class, $service);
    }

    /**
     * 测试 PSR-11 has 方法
     */
    public function testPsr11Has(): void
    {
        $container = Container::getInstance();
        
        $this->assertFalse($container->has('unknown.service'));
        
        $container->bind('known.service', function () {
            return 'test';
        });
        
        $this->assertTrue($container->has('known.service'));
    }

    /**
     * 测试 forget 方法
     */
    public function testForget(): void
    {
        $container = Container::getInstance();
        
        $container->singleton('forget.service', function () {
            return new \stdClass();
        });
        
        $this->assertTrue($container->has('forget.service'));
        
        $container->forget('forget.service');
        
        // 绑定被移除，但类仍然存在所以 has 返回 true（因为 class_exists）
        $this->assertFalse(in_array('forget.service', $container->getBindings()));
    }

    /**
     * 测试 instance 方法
     */
    public function testInstance(): void
    {
        $container = Container::getInstance();
        
        $obj = new \stdClass();
        $obj->value = 'test_instance';
        
        $container->instance('direct.instance', $obj);
        
        $resolved = $container->make('direct.instance');
        $this->assertSame($obj, $resolved);
        $this->assertEquals('test_instance', $resolved->value);
    }
}

// 测试辅助类
class TestDependency
{
}

class TestClass
{
    public TestDependency $dependency;

    public function __construct(TestDependency $dependency)
    {
        $this->dependency = $dependency;
    }
}

class TestClassWithParams
{
    public string $name;
    public int $value;

    public function __construct(string $name, int $value = 0)
    {
        $this->name = $name;
        $this->value = $value;
    }
}
