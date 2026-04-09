<?php

namespace Framework\Exception;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Log\Logger;
use Framework\Core\Container;

/**
 * 异常处理器
 * 统一处理应用异常
 */
class Handler
{
    /**
     * 日志服务
     */
    private ?Logger $logger = null;

    /**
     * 是否开启调试模式
     */
    private bool $debug = false;

    /**
     * 构造函数
     * 
     * @param Logger|null $logger 日志服务（可选，支持依赖注入）
     * @param bool $debug 是否开启调试模式
     */
    public function __construct(?Logger $logger = null, bool $debug = false)
    {
        $this->logger = $logger;
        $this->debug = $debug;
    }

    /**
     * 设置日志服务
     */
    public function setLogger(Logger $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 设置调试模式
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * 获取日志服务
     * 优先使用注入的实例，否则从容器或单例获取
     */
    private function getLogger(): Logger
    {
        if ($this->logger !== null) {
            return $this->logger;
        }

        // 尝试从容器获取
        $container = Container::getInstance();
        if ($container->has(Logger::class)) {
            $this->logger = $container->make(Logger::class);
            return $this->logger;
        }

        // 回退到单例
        $this->logger = Logger::getInstance();
        return $this->logger;
    }

    /**
     * 处理异常
     * 
     * @param \Throwable $e 异常对象
     * @param Request|null $request 请求对象
     * @return Response 响应对象
     */
    public function handle(\Throwable $e, ?Request $request): Response
    {
        // 使用 Logger 服务记录日志
        $this->logException($e, $request);

        // 根据异常类型返回不同响应
        return $this->renderException($e);
    }

    /**
     * 记录异常日志
     */
    private function logException(\Throwable $e, ?Request $request): void
    {
        $logger = $this->getLogger();
        
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        // 添加请求信息
        if ($request !== null) {
            $context['request'] = [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
            ];
        }

        // 根据异常类型选择日志级别
        if ($e instanceof \InvalidArgumentException) {
            $logger->warning($e->getMessage(), $context);
        } elseif ($e instanceof \RuntimeException) {
            $logger->error($e->getMessage(), $context);
        } else {
            $logger->critical($e->getMessage(), $context);
        }
    }

    /**
     * 渲染异常响应
     */
    private function renderException(\Throwable $e): Response
    {
        // 根据异常类型返回不同响应
        if ($e instanceof \InvalidArgumentException) {
            return Response::error(
                $this->debug ? $e->getMessage() : '请求参数错误',
                400,
                $this->debug ? $this->getDebugInfo($e) : null,
                400
            );
        }

        if ($e instanceof \RuntimeException) {
            return Response::error(
                $this->debug ? $e->getMessage() : '服务器处理错误',
                500,
                $this->debug ? $this->getDebugInfo($e) : null,
                500
            );
        }

        // 默认错误响应
        return Response::error(
            $this->debug ? $e->getMessage() : '服务器内部错误',
            500,
            $this->debug ? $this->getDebugInfo($e) : null,
            500
        );
    }

    /**
     * 获取调试信息
     */
    private function getDebugInfo(\Throwable $e): array
    {
        return [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ];
    }

    /**
     * 报告异常（可扩展用于发送到外部服务）
     */
    public function report(\Throwable $e): void
    {
        $this->logException($e, null);
    }
}
