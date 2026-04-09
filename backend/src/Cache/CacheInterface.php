<?php

namespace Framework\Cache;

/**
 * 缓存接口
 * 遵循 PSR-16 简单缓存接口
 */
interface CacheInterface
{
    /**
     * 获取缓存
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * 设置缓存
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * 删除缓存
     */
    public function delete(string $key): bool;

    /**
     * 清空所有缓存
     */
    public function clear(): bool;

    /**
     * 批量获取
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * 批量设置
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * 批量删除
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * 检查缓存是否存在
     */
    public function has(string $key): bool;
}
