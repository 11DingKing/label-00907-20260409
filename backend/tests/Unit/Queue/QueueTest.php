<?php

namespace Tests\Unit\Queue;

use Framework\Queue\Queue;
use Framework\Queue\Job;
use Framework\Queue\QueueDriverInterface;
use PHPUnit\Framework\TestCase;

/**
 * Queue（队列系统）测试用例
 */
class QueueTest extends TestCase
{
    private Queue $queue;
    private MockQueueDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new MockQueueDriver();
        $this->queue = Queue::getInstance();
        $this->queue->setDriver($this->driver);
    }

    /**
     * 测试推送任务
     */
    public function testPush(): void
    {
        $job = new TestJob();
        $job->setData(['user_id' => 1]);
        
        $id = $this->queue->push($job);
        
        $this->assertNotEmpty($id);
        $this->assertEquals(1, $this->queue->size());
    }

    /**
     * 测试延迟推送
     */
    public function testLater(): void
    {
        $job = new TestJob();
        
        $id = $this->queue->later(60, $job);
        
        $this->assertNotEmpty($id);
        $this->assertTrue($this->driver->hasDelayedJob($id));
    }

    /**
     * 测试批量推送
     */
    public function testBulk(): void
    {
        $jobs = [
            new TestJob(),
            new TestJob(),
            new TestJob(),
        ];
        
        $ids = $this->queue->bulk($jobs);
        
        $this->assertCount(3, $ids);
        $this->assertEquals(3, $this->queue->size());
    }

    /**
     * 测试获取任务
     */
    public function testPop(): void
    {
        $job = new TestJob();
        $job->setData(['test' => 'data']);
        
        $this->queue->push($job);
        
        $payload = $this->queue->pop();
        
        $this->assertNotNull($payload);
        $this->assertEquals(TestJob::class, $payload['class']);
        $this->assertEquals(['test' => 'data'], $payload['data']);
    }

    /**
     * 测试队列大小
     */
    public function testSize(): void
    {
        $this->assertEquals(0, $this->queue->size());
        
        $this->queue->push(new TestJob());
        $this->queue->push(new TestJob());
        
        $this->assertEquals(2, $this->queue->size());
    }

    /**
     * 测试清空队列
     */
    public function testClear(): void
    {
        $this->queue->push(new TestJob());
        $this->queue->push(new TestJob());
        
        $this->queue->clear();
        
        $this->assertEquals(0, $this->queue->size());
    }

    /**
     * 测试处理任务
     */
    public function testProcess(): void
    {
        $job = new TestJob();
        $job->setData(['value' => 42]);
        
        $this->queue->push($job);
        $payload = $this->queue->pop();
        
        $result = $this->queue->process($payload);
        
        $this->assertTrue($result);
    }
}

/**
 * 测试用任务类
 */
class TestJob extends Job
{
    public function handle(): void
    {
        // 模拟任务处理
        $value = $this->data['value'] ?? 0;
    }
}

/**
 * 模拟队列驱动
 */
class MockQueueDriver implements QueueDriverInterface
{
    private array $queues = [];
    private array $delayed = [];

    public function push(string $queue, array $payload): string
    {
        $this->queues[$queue][] = $payload;
        return $payload['id'];
    }

    public function later(string $queue, array $payload, int $delay): string
    {
        $this->delayed[$payload['id']] = [
            'queue' => $queue,
            'payload' => $payload,
            'available_at' => time() + $delay,
        ];
        return $payload['id'];
    }

    public function pop(string $queue): ?array
    {
        if (empty($this->queues[$queue])) {
            return null;
        }
        return array_shift($this->queues[$queue]);
    }

    public function size(string $queue): int
    {
        return count($this->queues[$queue] ?? []);
    }

    public function clear(string $queue): bool
    {
        $this->queues[$queue] = [];
        return true;
    }

    public function hasDelayedJob(string $id): bool
    {
        return isset($this->delayed[$id]);
    }
}
