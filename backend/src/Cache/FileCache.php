<?php

namespace Framework\Cache;

/**
 * 文件缓存驱动
 * 适用于单机环境
 */
class FileCache implements CacheInterface
{
    /**
     * 缓存目录
     */
    private string $path;

    /**
     * 默认过期时间（秒）
     */
    private int $defaultTtl = 3600;

    public function __construct(string $path, int $defaultTtl = 3600)
    {
        $this->path = rtrim($path, '/');
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * 获取缓存
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        $data = unserialize($content);

        // 检查是否过期
        if ($data['expire'] !== 0 && $data['expire'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * 设置缓存
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $data = [
            'value' => $value,
            'expire' => $ttl > 0 ? time() + $ttl : 0,
        ];

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * 删除缓存
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * 清空所有缓存
     */
    public function clear(): bool
    {
        return $this->deleteDirectory($this->path);
    }

    /**
     * 批量获取
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * 批量设置
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 批量删除
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 检查缓存是否存在
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * 获取缓存文件路径
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        // 使用两级目录结构避免单目录文件过多
        return $this->path . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * 记住缓存（不存在则执行回调并缓存）
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
        $value = (int) $this->get($key, 0);
        $value += $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * 自减
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }
}
