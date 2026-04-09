<?php

namespace Framework\Core;

/**
 * 懒加载代理类
 * 实现延迟实例化，只有在实际使用时才创建对象
 */
class LazyProxy
{
    /**
     * 实际对象实例
     */
    private ?object $instance = null;

    /**
     * 对象创建工厂
     * @var callable
     */
    private $factory;

    /**
     * 目标类名
     */
    private string $className;

    /**
     * 是否已初始化
     */
    private bool $initialized = false;

    /**
     * 构造函数
     * 
     * @param string $className 目标类名
     * @param callable $factory 创建实例的工厂函数
     */
    public function __construct(string $className, callable $factory)
    {
        $this->className = $className;
        $this->factory = $factory;
    }

    /**
     * 获取实际实例（懒加载）
     */
    public function getInstance(): object
    {
        if (!$this->initialized) {
            $this->instance = ($this->factory)();
            $this->initialized = true;
        }
        return $this->instance;
    }

    /**
     * 是否已初始化
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * 获取目标类名
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * 魔术方法：调用实际对象的方法
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->getInstance()->$method(...$arguments);
    }

    /**
     * 魔术方法：获取实际对象的属性
     */
    public function __get(string $name): mixed
    {
        return $this->getInstance()->$name;
    }

    /**
     * 魔术方法：设置实际对象的属性
     */
    public function __set(string $name, mixed $value): void
    {
        $this->getInstance()->$name = $value;
    }

    /**
     * 魔术方法：检查实际对象的属性是否存在
     */
    public function __isset(string $name): bool
    {
        return isset($this->getInstance()->$name);
    }
}
