<?php

namespace Tests\Unit\Cache;

use Framework\Cache\FileCache;
use PHPUnit\Framework\TestCase;

/**
 * FileCache（文件缓存）测试用例
 */
class FileCacheTest extends TestCase
{
    private FileCache $cache;
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/framework_cache_test';
        $this->cache = new FileCache($this->cachePath, 3600);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    /**
     * 测试设置和获取缓存
     */
    public function testSetAndGet(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', ['a' => 1, 'b' => 2]);
        
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals(['a' => 1, 'b' => 2], $this->cache->get('key2'));
    }

    /**
     * 测试默认值
     */
    public function testDefaultValue(): void
    {
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
        $this->assertNull($this->cache->get('nonexistent'));
    }

    /**
     * 测试删除缓存
     */
    public function testDelete(): void
    {
        $this->cache->set('to_delete', 'value');
        $this->assertTrue($this->cache->has('to_delete'));
        
        $this->cache->delete('to_delete');
        $this->assertFalse($this->cache->has('to_delete'));
    }

    /**
     * 测试检查缓存是否存在
     */
    public function testHas(): void
    {
        $this->cache->set('exists', 'value');
        
        $this->assertTrue($this->cache->has('exists'));
        $this->assertFalse($this->cache->has('not_exists'));
    }

    /**
     * 测试批量操作
     */
    public function testMultiple(): void
    {
        $this->cache->setMultiple([
            'multi1' => 'value1',
            'multi2' => 'value2',
            'multi3' => 'value3',
        ]);
        
        $result = $this->cache->getMultiple(['multi1', 'multi2', 'multi3']);
        
        $this->assertEquals('value1', $result['multi1']);
        $this->assertEquals('value2', $result['multi2']);
        $this->assertEquals('value3', $result['multi3']);
        
        $this->cache->deleteMultiple(['multi1', 'multi2']);
        $this->assertFalse($this->cache->has('multi1'));
        $this->assertFalse($this->cache->has('multi2'));
        $this->assertTrue($this->cache->has('multi3'));
    }

    /**
     * 测试清空缓存
     */
    public function testClear(): void
    {
        $this->cache->set('clear1', 'value1');
        $this->cache->set('clear2', 'value2');
        
        $this->cache->clear();
        
        $this->assertFalse($this->cache->has('clear1'));
        $this->assertFalse($this->cache->has('clear2'));
    }

    /**
     * 测试 remember 方法
     */
    public function testRemember(): void
    {
        $counter = 0;
        
        $value1 = $this->cache->remember('remember_key', 3600, function () use (&$counter) {
            $counter++;
            return 'computed_value';
        });
        
        $value2 = $this->cache->remember('remember_key', 3600, function () use (&$counter) {
            $counter++;
            return 'computed_value';
        });
        
        $this->assertEquals('computed_value', $value1);
        $this->assertEquals('computed_value', $value2);
        $this->assertEquals(1, $counter); // 回调只执行一次
    }

    /**
     * 测试自增自减
     */
    public function testIncrementDecrement(): void
    {
        $this->cache->set('counter', 10);
        
        $this->assertEquals(11, $this->cache->increment('counter'));
        $this->assertEquals(13, $this->cache->increment('counter', 2));
        $this->assertEquals(12, $this->cache->decrement('counter'));
        $this->assertEquals(10, $this->cache->decrement('counter', 2));
    }
}
