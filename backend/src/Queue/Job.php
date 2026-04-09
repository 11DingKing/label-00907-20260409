<?php

namespace Framework\Queue;

/**
 * 任务基类
 */
abstract class Job
{
    /**
     * 任务数据
     */
    protected array $data = [];

    /**
     * 最大重试次数
     */
    protected int $maxAttempts = 3;

    /**
     * 超时时间（秒）
     */
    protected int $timeout = 60;

    /**
     * 设置数据
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 获取数据
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取最大重试次数
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * 执行任务（子类实现）
     */
    abstract public function handle(): void;
}
