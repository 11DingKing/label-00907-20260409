<?php

namespace Tests\Unit\Event;

use Framework\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * EventDispatcher（事件调度器）测试用例
 */
class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * 测试注册和触发事件
     */
    public function testListenAndDispatch(): void
    {
        $called = false;
        
        $this->dispatcher->listen('user.created', function ($payload) use (&$called) {
            $called = true;
            $this->assertEquals(['id' => 1, 'name' => 'test'], $payload);
        });
        
        $this->dispatcher->dispatch('user.created', ['id' => 1, 'name' => 'test']);
        
        $this->assertTrue($called);
    }

    /**
     * 测试多个监听器
     */
    public function testMultipleListeners(): void
    {
        $results = [];
        
        $this->dispatcher->listen('test.event', function () use (&$results) {
            $results[] = 'listener1';
        });
        
        $this->dispatcher->listen('test.event', function () use (&$results) {
            $results[] = 'listener2';
        });
        
        $this->dispatcher->dispatch('test.event');
        
        $this->assertCount(2, $results);
        $this->assertContains('listener1', $results);
        $this->assertContains('listener2', $results);
    }

    /**
     * 测试优先级
     */
    public function testPriority(): void
    {
        $results = [];
        
        $this->dispatcher->listen('priority.event', function () use (&$results) {
            $results[] = 'low';
        }, 0);
        
        $this->dispatcher->listen('priority.event', function () use (&$results) {
            $results[] = 'high';
        }, 10);
        
        $this->dispatcher->dispatch('priority.event');
        
        $this->assertEquals(['high', 'low'], $results);
    }

    /**
     * 测试停止传播
     */
    public function testStopPropagation(): void
    {
        $results = [];
        
        $this->dispatcher->listen('stop.event', function () use (&$results) {
            $results[] = 'first';
            return false; // 停止传播
        }, 10);
        
        $this->dispatcher->listen('stop.event', function () use (&$results) {
            $results[] = 'second';
        }, 0);
        
        $this->dispatcher->dispatch('stop.event');
        
        $this->assertEquals(['first'], $results);
    }

    /**
     * 测试通配符监听器
     */
    public function testWildcardListener(): void
    {
        $events = [];
        
        $this->dispatcher->listen('*', function ($event, $payload) use (&$events) {
            $events[] = $event;
        });
        
        $this->dispatcher->dispatch('event.one');
        $this->dispatcher->dispatch('event.two');
        
        $this->assertContains('event.one', $events);
        $this->assertContains('event.two', $events);
    }

    /**
     * 测试检查监听器是否存在
     */
    public function testHasListeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('no.listeners'));
        
        $this->dispatcher->listen('has.listeners', function () {});
        
        $this->assertTrue($this->dispatcher->hasListeners('has.listeners'));
    }

    /**
     * 测试移除监听器
     */
    public function testForget(): void
    {
        $this->dispatcher->listen('forget.event', function () {});
        $this->assertTrue($this->dispatcher->hasListeners('forget.event'));
        
        $this->dispatcher->forget('forget.event');
        $this->assertFalse($this->dispatcher->hasListeners('forget.event'));
    }

    /**
     * 测试 dispatchUntil
     */
    public function testDispatchUntil(): void
    {
        $this->dispatcher->listen('until.event', function () {
            return null;
        }, 10);
        
        $this->dispatcher->listen('until.event', function () {
            return 'found';
        }, 5);
        
        $this->dispatcher->listen('until.event', function () {
            return 'should not reach';
        }, 0);
        
        $result = $this->dispatcher->dispatchUntil('until.event');
        
        $this->assertEquals('found', $result);
    }

    /**
     * 测试获取监听器列表
     */
    public function testGetListeners(): void
    {
        $listener1 = function () {};
        $listener2 = function () {};
        
        $this->dispatcher->listen('get.listeners', $listener1);
        $this->dispatcher->listen('get.listeners', $listener2);
        
        $listeners = $this->dispatcher->getListeners('get.listeners');
        
        $this->assertCount(2, $listeners);
    }
}
