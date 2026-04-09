<?php

namespace Tests\Unit\Config;

use Framework\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * Config（配置管理）测试用例
 */
class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = Config::getInstance();
    }

    /**
     * 测试单例模式
     */
    public function testSingleton(): void
    {
        $config1 = Config::getInstance();
        $config2 = Config::getInstance();
        
        $this->assertSame($config1, $config2);
    }

    /**
     * 测试设置和获取配置
     */
    public function testSetAndGet(): void
    {
        $this->config->set('app.name', 'TestApp');
        $this->config->set('app.debug', true);
        
        $this->assertEquals('TestApp', $this->config->get('app.name'));
        $this->assertTrue($this->config->get('app.debug'));
    }

    /**
     * 测试点号访问嵌套配置
     */
    public function testDotNotation(): void
    {
        $this->config->set('database.mysql.host', 'localhost');
        $this->config->set('database.mysql.port', 3306);
        
        $this->assertEquals('localhost', $this->config->get('database.mysql.host'));
        $this->assertEquals(3306, $this->config->get('database.mysql.port'));
    }

    /**
     * 测试默认值
     */
    public function testDefaultValue(): void
    {
        $this->assertEquals('default', $this->config->get('non.existent.key', 'default'));
        $this->assertNull($this->config->get('non.existent.key'));
    }

    /**
     * 测试检查配置是否存在
     */
    public function testHas(): void
    {
        $this->config->set('exists.key', 'value');
        
        $this->assertTrue($this->config->has('exists.key'));
        $this->assertFalse($this->config->has('not.exists'));
    }

    /**
     * 测试获取所有配置
     */
    public function testAll(): void
    {
        $this->config->set('test.key1', 'value1');
        $this->config->set('test.key2', 'value2');
        
        $all = $this->config->all();
        
        $this->assertIsArray($all);
        $this->assertArrayHasKey('test', $all);
    }

    /**
     * 测试合并配置
     */
    public function testMerge(): void
    {
        $this->config->set('merge.test', ['a' => 1, 'b' => 2]);
        $this->config->merge('merge.test', ['c' => 3]);
        
        $result = $this->config->get('merge.test');
        
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }
}
