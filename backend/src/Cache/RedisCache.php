<?php

namespace Framework\Cache;

/**
 * Redis 缓存驱动
 * 适用于分布式环境和高并发场景
 */
class RedisCache implements CacheInterface
{
    /**
     * Redis 实例
     */
    private \Redis $redis;

    /**
     * 键前缀
     */
    private string $prefix;

    /**
     * 默认过期时间（秒）
     */
    private int $defaultTtl;

    public function __construct(array $config = [])
    {
        $this->redis = new \Redis();
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        
        $this->prefix = $config['prefix'] ?? 'cache:';
        $this->defaultTtl = $config['ttl'] ?? 3600;

        $this->redis->connect($host, $port, $timeout);
        
        if ($password) {
            $this->redis->auth($password);
        }
        
        if ($database > 0) {
            $this->redis->select($database);
        }
    }

    /**
     * 获取缓存
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * 设置缓存
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }

        return $this->redis->set($this->prefix . $key, $serialized);
    }

    /**
     * 删除缓存
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    /**
     * 清空所有缓存
     */
    public function clear(): bool
    {
        // 只清除带前缀的键
        $keys = $this->redis->keys($this->prefix . '*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
        return true;
    }

    /**
     * 批量获取
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        $values = $this->redis->mget($prefixedKeys);

        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] !== false ? unserialize($values[$i]) : $default;
        }

        return $result;
    }

    /**
     * 批量设置
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $pipe = $this->redis->multi(\Redis::PIPELINE);

        foreach ($values as $key => $value) {
            $serialized = serialize($value);
            if ($ttl > 0) {
                $pipe->setex($this->prefix . $key, $ttl, $serialized);
            } else {
                $pipe->set($this->prefix . $key, $serialized);
            }
        }

        $pipe->exec();
        return true;
    }

    /**
     * 批量删除
     */
    public function deleteMultiple(array $keys): bool
    {
        $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        $this->redis->del($prefixedKeys);
        return true;
    }

    /**
     * 检查缓存是否存在
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    /**
     * 记住缓存
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * 自增
     */
    public function increment(string $key, int $step = 1): int
    {
        return $this->redis->incrBy($this->prefix . $key, $step);
    }

    /**
     * 自减
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->redis->decrBy($this->prefix . $key, $step);
    }

    /**
     * 获取 Redis 实例（用于高级操作）
     */
    public function getRedis(): \Redis
    {
        return $this->redis;
    }

    /**
     * 加锁（分布式锁）
     */
    public function lock(string $key, int $ttl = 10): bool
    {
        return $this->redis->set(
            $this->prefix . 'lock:' . $key,
            1,
            ['NX', 'EX' => $ttl]
        );
    }

    /**
     * 解锁
     */
    public function unlock(string $key): bool
    {
        return $this->delete('lock:' . $key);
    }
}
