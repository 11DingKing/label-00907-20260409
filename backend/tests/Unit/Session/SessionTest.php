<?php

namespace Tests\Unit\Session;

use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Session（会话管理）测试用例
 */
class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session();
    }

    /**
     * 测试设置和获取会话值
     */
    public function testSetAndGet(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', ['a' => 1, 'b' => 2]);
        
        $this->assertEquals('value1', $this->session->get('key1'));
        $this->assertEquals(['a' => 1, 'b' => 2], $this->session->get('key2'));
    }

    /**
     * 测试默认值
     */
    public function testDefaultValue(): void
    {
        $this->assertEquals('default', $this->session->get('nonexistent', 'default'));
        $this->assertNull($this->session->get('nonexistent'));
    }

    /**
     * 测试检查会话键是否存在
     */
    public function testHas(): void
    {
        $this->session->set('exists', 'value');
        
        $this->assertTrue($this->session->has('exists'));
        $this->assertFalse($this->session->has('not_exists'));
    }

    /**
     * 测试删除会话值
     */
    public function testRemove(): void
    {
        $this->session->set('to_remove', 'value');
        $this->assertTrue($this->session->has('to_remove'));
        
        $this->session->remove('to_remove');
        $this->assertFalse($this->session->has('to_remove'));
    }

    /**
     * 测试获取所有会话数据
     */
    public function testAll(): void
    {
        $this->session->set('all1', 'value1');
        $this->session->set('all2', 'value2');
        
        $all = $this->session->all();
        
        $this->assertIsArray($all);
        $this->assertArrayHasKey('all1', $all);
        $this->assertArrayHasKey('all2', $all);
    }

    /**
     * 测试清空会话
     */
    public function testClear(): void
    {
        $this->session->set('clear1', 'value1');
        $this->session->set('clear2', 'value2');
        
        $this->session->clear();
        
        $this->assertFalse($this->session->has('clear1'));
        $this->assertFalse($this->session->has('clear2'));
    }

    /**
     * 测试闪存数据
     */
    public function testFlash(): void
    {
        $this->session->flash('message', 'Success!');
        
        // 第一次获取
        $value = $this->session->getFlash('message');
        $this->assertEquals('Success!', $value);
        
        // 第二次获取应该返回默认值
        $value = $this->session->getFlash('message', 'default');
        $this->assertEquals('default', $value);
    }
}
