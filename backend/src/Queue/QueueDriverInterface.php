<?php

namespace Framework\Queue;

/**
 * 队列驱动接口
 */
interface QueueDriverInterface
{
    public function push(string $queue, array $payload): string;
    public function later(string $queue, array $payload, int $delay): string;
    public function pop(string $queue): ?array;
    public function size(string $queue): int;
    public function clear(string $queue): bool;
}
