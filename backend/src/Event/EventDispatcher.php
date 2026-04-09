<?php

namespace Framework\Event;

/**
 * 事件调度器
 * 实现观察者模式，支持事件监听和触发
 */
class EventDispatcher
{
    /**
     * 单例实例
     */
    private static ?EventDispatcher $instance = null;

    /**
     * 事件监听器
     * @var array<string, array<callable>>
     */
    private array $listeners = [];

    /**
     * 通配符监听器
     * @var array<callable>
     */
    private array $wildcardListeners = [];

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
     * 注册事件监听器
     * 
     * @param string $event 事件名称，支持通配符 *
     * @param callable $listener 监听器回调
     * @param int $priority 优先级（数字越大越先执行）
     */
    public function listen(string $event, callable $listener, int $priority = 0): self
    {
        if ($event === '*') {
            $this->wildcardListeners[] = $listener;
        } else {
            $this->listeners[$event][$priority][] = $listener;
        }
        return $this;
    }

    /**
     * 触发事件
     * 
     * @param string $event 事件名称
     * @param mixed $payload 事件数据
     * @return array 所有监听器的返回值
     */
    public function dispatch(string $event, mixed $payload = null): array
    {
        $responses = [];

        // 执行通配符监听器
        foreach ($this->wildcardListeners as $listener) {
            $responses[] = $listener($event, $payload);
        }

        // 执行特定事件监听器
        if (isset($this->listeners[$event])) {
            // 按优先级排序（降序）
            krsort($this->listeners[$event]);

            foreach ($this->listeners[$event] as $listeners) {
                foreach ($listeners as $listener) {
                    $response = $listener($payload, $event);
                    $responses[] = $response;

                    // 如果返回 false，停止传播
                    if ($response === false) {
                        return $responses;
                    }
                }
            }
        }

        return $responses;
    }

    /**
     * 触发事件直到有返回值
     */
    public function dispatchUntil(string $event, mixed $payload = null): mixed
    {
        if (isset($this->listeners[$event])) {
            krsort($this->listeners[$event]);

            foreach ($this->listeners[$event] as $listeners) {
                foreach ($listeners as $listener) {
                    $response = $listener($payload, $event);
                    if ($response !== null) {
                        return $response;
                    }
                }
            }
        }

        return null;
    }

    /**
     * 检查事件是否有监听器
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]) || !empty($this->wildcardListeners);
    }

    /**
     * 移除事件监听器
     */
    public function forget(string $event): self
    {
        unset($this->listeners[$event]);
        return $this;
    }

    /**
     * 获取事件的所有监听器
     */
    public function getListeners(string $event): array
    {
        $listeners = [];

        if (isset($this->listeners[$event])) {
            krsort($this->listeners[$event]);
            foreach ($this->listeners[$event] as $priorityListeners) {
                $listeners = array_merge($listeners, $priorityListeners);
            }
        }

        return array_merge($this->wildcardListeners, $listeners);
    }

    /**
     * 订阅者注册（批量注册监听器）
     */
    public function subscribe(object $subscriber): self
    {
        if (method_exists($subscriber, 'subscribe')) {
            $subscriber->subscribe($this);
        }
        return $this;
    }
}

/**
 * 事件基类
 */
abstract class Event
{
    /**
     * 是否停止传播
     */
    private bool $propagationStopped = false;

    /**
     * 停止事件传播
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * 检查是否已停止传播
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
