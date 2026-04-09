<?php

namespace Framework\Database;

/**
 * 数据库连接池
 * 管理多个数据库连接，支持连接复用
 * 
 * 在 Swoole 协程环境下使用 Channel 实现非阻塞等待
 * 在传统 PHP-FPM 环境下使用数组队列
 */
class ConnectionPool
{
    /**
     * 连接池实例（单例）
     */
    private static ?ConnectionPool $instance = null;

    /**
     * 可用连接队列（传统模式）
     * @var array<Connection>
     */
    private array $available = [];

    /**
     * 已使用的连接（传统模式）
     * @var array<Connection>
     */
    private array $inUse = [];

    /**
     * Swoole Channel（协程模式）
     * @var \Swoole\Coroutine\Channel|null
     */
    private $channel = null;

    /**
     * 数据库配置
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * 最大连接数
     */
    private int $maxConnections = 10;

    /**
     * 当前连接数
     */
    private int $currentConnections = 0;

    /**
     * 是否运行在协程环境
     */
    private bool $coroutineMode = false;

    /**
     * 等待超时时间（秒）
     */
    private float $waitTimeout = 3.0;

    /**
     * 获取连接池单例
     */
    public static function getInstance(): ConnectionPool
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 重置单例（用于测试）
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->closeAll();
            self::$instance = null;
        }
    }

    /**
     * 设置数据库配置
     * 
     * @param array $config 数据库配置
     * @param int $maxConnections 最大连接数
     * @param float $waitTimeout 等待超时时间（秒）
     */
    public function setConfig(array $config, int $maxConnections = 10, float $waitTimeout = 3.0): void
    {
        $this->config = $config;
        $this->maxConnections = $maxConnections;
        $this->waitTimeout = $waitTimeout;

        // 检测是否在 Swoole 协程环境中
        $this->detectCoroutineMode();

        // 如果是协程模式，初始化 Channel
        if ($this->coroutineMode) {
            $this->initChannel();
        }
    }

    /**
     * 检测是否运行在 Swoole 协程环境
     */
    private function detectCoroutineMode(): void
    {
        $this->coroutineMode = extension_loaded('swoole') 
            && class_exists('\Swoole\Coroutine') 
            && \Swoole\Coroutine::getCid() >= 0;
    }

    /**
     * 初始化 Swoole Channel
     */
    private function initChannel(): void
    {
        if ($this->channel !== null) {
            return;
        }

        $this->channel = new \Swoole\Coroutine\Channel($this->maxConnections);

        // 预创建连接放入 Channel
        for ($i = 0; $i < $this->maxConnections; $i++) {
            $connection = new Connection($this->config);
            $this->channel->push($connection);
            $this->currentConnections++;
        }
    }

    /**
     * 从连接池获取连接
     * 
     * 协程模式：使用 Channel 实现非阻塞等待
     * 传统模式：使用数组队列，池满时抛出异常
     * 
     * @throws \RuntimeException 连接池已满或等待超时
     */
    public function getConnection(): Connection
    {
        // 协程模式：使用 Channel
        if ($this->coroutineMode && $this->channel !== null) {
            return $this->getConnectionFromChannel();
        }

        // 传统模式：使用数组队列
        return $this->getConnectionFromArray();
    }

    /**
     * 从 Channel 获取连接（协程模式）
     * 支持非阻塞等待，协程会自动挂起
     */
    private function getConnectionFromChannel(): Connection
    {
        // 从 Channel 弹出连接，如果没有可用连接会挂起当前协程等待
        $connection = $this->channel->pop($this->waitTimeout);

        if ($connection === false) {
            // 超时或 Channel 已关闭
            $stats = $this->channel->stats();
            throw new \RuntimeException(
                "数据库连接池等待超时（{$this->waitTimeout}秒），" .
                "当前队列长度: {$stats['queue_num']}，消费者数: {$stats['consumer_num']}"
            );
        }

        // 检查连接是否有效，无效则重新创建
        if (!$this->isConnectionValid($connection)) {
            $connection->close();
            $connection = new Connection($this->config);
        }

        return $connection;
    }

    /**
     * 从数组获取连接（传统模式）
     */
    private function getConnectionFromArray(): Connection
    {
        // 如果池中有可用连接，直接返回
        if (!empty($this->available)) {
            $connection = array_pop($this->available);
            
            // 检查连接是否有效
            if (!$this->isConnectionValid($connection)) {
                $connection->close();
                $this->currentConnections--;
                return $this->getConnectionFromArray();
            }
            
            $this->inUse[] = $connection;
            return $connection;
        }

        // 如果未达到最大连接数，创建新连接
        if ($this->currentConnections < $this->maxConnections) {
            $connection = new Connection($this->config);
            $this->currentConnections++;
            $this->inUse[] = $connection;
            return $connection;
        }

        // 连接池已满
        throw new \RuntimeException(
            "数据库连接池已满（最大连接数: {$this->maxConnections}），" .
            "可用: " . count($this->available) . "，使用中: " . count($this->inUse)
        );
    }

    /**
     * 检查连接是否有效
     */
    private function isConnectionValid(Connection $connection): bool
    {
        try {
            // 执行简单查询检测连接
            $connection->queryOne('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 归还连接到池中
     */
    public function releaseConnection(Connection $connection): void
    {
        // 重置连接状态（如果有事务，回滚）
        try {
            $connection->rollback();
        } catch (\Exception $e) {
            // 忽略回滚错误
        }

        // 协程模式：推回 Channel
        if ($this->coroutineMode && $this->channel !== null) {
            $this->channel->push($connection);
            return;
        }

        // 传统模式：放回数组
        $key = array_search($connection, $this->inUse, true);
        if ($key !== false) {
            unset($this->inUse[$key]);
            $this->inUse = array_values($this->inUse);
            $this->available[] = $connection;
        }
    }

    /**
     * 关闭所有连接
     */
    public function closeAll(): void
    {
        // 协程模式：清空 Channel
        if ($this->coroutineMode && $this->channel !== null) {
            while (!$this->channel->isEmpty()) {
                $connection = $this->channel->pop(0.1);
                if ($connection instanceof Connection) {
                    $connection->close();
                }
            }
            $this->channel->close();
            $this->channel = null;
        }

        // 传统模式：关闭数组中的连接
        foreach ($this->available as $connection) {
            $connection->close();
        }
        foreach ($this->inUse as $connection) {
            $connection->close();
        }

        $this->available = [];
        $this->inUse = [];
        $this->currentConnections = 0;
    }

    /**
     * 获取连接池状态（调试用）
     */
    public function getStatus(): array
    {
        $status = [
            'mode' => $this->coroutineMode ? 'coroutine' : 'traditional',
            'current' => $this->currentConnections,
            'max' => $this->maxConnections,
            'wait_timeout' => $this->waitTimeout,
        ];

        if ($this->coroutineMode && $this->channel !== null) {
            $channelStats = $this->channel->stats();
            $status['channel_queue'] = $channelStats['queue_num'];
            $status['channel_consumers'] = $channelStats['consumer_num'];
        } else {
            $status['available'] = count($this->available);
            $status['in_use'] = count($this->inUse);
        }

        return $status;
    }

    /**
     * 是否运行在协程模式
     */
    public function isCoroutineMode(): bool
    {
        return $this->coroutineMode;
    }

    /**
     * 设置等待超时时间
     */
    public function setWaitTimeout(float $timeout): void
    {
        $this->waitTimeout = $timeout;
    }
}
