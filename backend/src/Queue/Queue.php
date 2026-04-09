<?php

namespace Framework\Queue;

/**
 * 队列管理类
 * 支持异步任务处理
 */
class Queue
{
    /**
     * 单例实例
     */
    private static ?Queue $instance = null;

    /**
     * 队列驱动
     */
    private QueueDriverInterface $driver;

    /**
     * 默认队列名称
     */
    private string $defaultQueue = 'default';

    /**
     * 获取单例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 设置驱动
     */
    public function setDriver(QueueDriverInterface $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * 设置默认队列
     */
    public function setDefaultQueue(string $queue): self
    {
        $this->defaultQueue = $queue;
        return $this;
    }

    /**
     * 推送任务到队列
     * 
     * @param Job $job 任务对象
     * @param string|null $queue 队列名称
     * @param int $delay 延迟秒数
     * @return string 任务 ID
     */
    public function push(Job $job, ?string $queue = null, int $delay = 0): string
    {
        $queue = $queue ?? $this->defaultQueue;
        $payload = $this->createPayload($job);

        if ($delay > 0) {
            return $this->driver->later($queue, $payload, $delay);
        }

        return $this->driver->push($queue, $payload);
    }

    /**
     * 延迟推送任务
     */
    public function later(int $delay, Job $job, ?string $queue = null): string
    {
        return $this->push($job, $queue, $delay);
    }

    /**
     * 批量推送任务
     */
    public function bulk(array $jobs, ?string $queue = null): array
    {
        $ids = [];
        foreach ($jobs as $job) {
            $ids[] = $this->push($job, $queue);
        }
        return $ids;
    }

    /**
     * 从队列获取任务
     */
    public function pop(?string $queue = null): ?array
    {
        $queue = $queue ?? $this->defaultQueue;
        return $this->driver->pop($queue);
    }

    /**
     * 获取队列长度
     */
    public function size(?string $queue = null): int
    {
        $queue = $queue ?? $this->defaultQueue;
        return $this->driver->size($queue);
    }

    /**
     * 清空队列
     */
    public function clear(?string $queue = null): bool
    {
        $queue = $queue ?? $this->defaultQueue;
        return $this->driver->clear($queue);
    }

    /**
     * 创建任务载荷
     */
    private function createPayload(Job $job): array
    {
        return [
            'id' => uniqid('job_', true),
            'class' => get_class($job),
            'data' => $job->getData(),
            'attempts' => 0,
            'max_attempts' => $job->getMaxAttempts(),
            'created_at' => time(),
        ];
    }

    /**
     * 处理任务
     */
    public function process(array $payload): bool
    {
        $class = $payload['class'];
        
        if (!class_exists($class)) {
            throw new \RuntimeException("Job class not found: {$class}");
        }

        /** @var Job $job */
        $job = new $class();
        $job->setData($payload['data']);

        try {
            $job->handle();
            return true;
        } catch (\Throwable $e) {
            $payload['attempts']++;
            
            if ($payload['attempts'] < $payload['max_attempts']) {
                // 重新入队
                $this->driver->push($this->defaultQueue, $payload);
            } else {
                // 移入失败队列
                $payload['error'] = $e->getMessage();
                $this->driver->push('failed', $payload);
            }
            
            return false;
        }
    }
}

/**
 * 数据库队列驱动
 */
class DatabaseQueueDriver implements QueueDriverInterface
{
    private \Framework\Database\Connection $connection;
    private string $table;

    public function __construct(\Framework\Database\Connection $connection, string $table = 'jobs')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function push(string $queue, array $payload): string
    {
        $id = $payload['id'];
        $this->connection->execute(
            "INSERT INTO {$this->table} (id, queue, payload, available_at, created_at) VALUES (?, ?, ?, ?, ?)",
            [$id, $queue, json_encode($payload), time(), time()]
        );
        return $id;
    }

    public function later(string $queue, array $payload, int $delay): string
    {
        $id = $payload['id'];
        $this->connection->execute(
            "INSERT INTO {$this->table} (id, queue, payload, available_at, created_at) VALUES (?, ?, ?, ?, ?)",
            [$id, $queue, json_encode($payload), time() + $delay, time()]
        );
        return $id;
    }

    public function pop(string $queue): ?array
    {
        $result = $this->connection->queryOne(
            "SELECT * FROM {$this->table} WHERE queue = ? AND available_at <= ? ORDER BY created_at ASC LIMIT 1",
            [$queue, time()]
        );

        if (!$result) {
            return null;
        }

        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$result['id']]
        );

        return json_decode($result['payload'], true);
    }

    public function size(string $queue): int
    {
        $result = $this->connection->queryOne(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE queue = ?",
            [$queue]
        );
        return (int) ($result['count'] ?? 0);
    }

    public function clear(string $queue): bool
    {
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE queue = ?",
            [$queue]
        );
        return true;
    }
}
