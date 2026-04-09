<?php

namespace Framework\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * 日志管理类
 * 实现 PSR-3 LoggerInterface
 */
class Logger implements LoggerInterface
{
    /**
     * 日志级别优先级
     */
    private const LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    /**
     * 单例实例
     */
    private static ?Logger $instance = null;

    /**
     * 日志存储路径
     */
    private string $path = '';

    /**
     * 最低记录级别
     */
    private string $level = LogLevel::DEBUG;

    /**
     * 日志通道
     */
    private string $channel = 'app';

    /**
     * 日志处理器
     * @var array<callable>
     */
    private array $handlers = [];

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
     * 设置日志路径
     */
    public function setPath(string $path): self
    {
        $this->path = rtrim($path, '/');
        if (!empty($this->path) && !is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        return $this;
    }

    /**
     * 设置最低日志级别
     */
    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * 设置日志通道
     */
    public function channel(string $channel): self
    {
        $clone = clone $this;
        $clone->channel = $channel;
        return $clone;
    }

    /**
     * 添加日志处理器
     */
    public function addHandler(callable $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    /**
     * PSR-3: 记录日志
     * 
     * @param mixed $level 日志级别
     * @param string|\Stringable $message 日志消息
     * @param array $context 上下文数据
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // 检查日志级别
        if (!$this->shouldLog($level)) {
            return;
        }

        // 转换 Stringable 为字符串
        $message = (string) $message;

        // 替换上下文占位符
        $message = $this->interpolate($message, $context);

        // 构建日志记录
        $record = [
            'datetime' => date('Y-m-d H:i:s'),
            'channel' => $this->channel,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];

        // 执行处理器
        foreach ($this->handlers as $handler) {
            $handler($record);
        }

        // 写入文件
        $this->write($record);
    }

    /**
     * 检查是否应该记录该级别日志
     */
    private function shouldLog(string $level): bool
    {
        $levelPriority = self::LEVELS[$level] ?? 7;
        $minPriority = self::LEVELS[$this->level] ?? 7;
        return $levelPriority <= $minPriority;
    }

    /**
     * 替换消息中的占位符（PSR-3 规范）
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * 写入日志文件
     */
    private function write(array $record): void
    {
        if (empty($this->path)) {
            return;
        }

        $filename = $this->path . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
        $contextStr = !empty($record['context']) ? json_encode($record['context'], JSON_UNESCAPED_UNICODE) : '';
        
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            $record['datetime'],
            $record['channel'],
            $record['level'],
            $record['message'],
            $contextStr
        );

        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }

    // PSR-3 便捷方法

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
