<?php

namespace Framework\Console;

/**
 * 命令行基类
 * 提供命令行工具支持
 */
abstract class Command
{
    /**
     * 命令名称
     */
    protected string $name = '';

    /**
     * 命令描述
     */
    protected string $description = '';

    /**
     * 命令参数
     * @var array<string, mixed>
     */
    protected array $arguments = [];

    /**
     * 命令选项
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * 输入参数
     */
    protected array $input = [];

    /**
     * 获取命令名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 设置输入参数
     */
    public function setInput(array $input): self
    {
        $this->input = $input;
        return $this;
    }

    /**
     * 获取参数值
     */
    protected function argument(string $name, mixed $default = null): mixed
    {
        return $this->input['arguments'][$name] ?? $default;
    }

    /**
     * 获取选项值
     */
    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->input['options'][$name] ?? $default;
    }

    /**
     * 输出信息
     */
    protected function info(string $message): void
    {
        echo "\033[32m{$message}\033[0m\n";
    }

    /**
     * 输出错误
     */
    protected function error(string $message): void
    {
        echo "\033[31m{$message}\033[0m\n";
    }

    /**
     * 输出警告
     */
    protected function warn(string $message): void
    {
        echo "\033[33m{$message}\033[0m\n";
    }

    /**
     * 输出普通文本
     */
    protected function line(string $message): void
    {
        echo "{$message}\n";
    }

    /**
     * 输出表格
     */
    protected function table(array $headers, array $rows): void
    {
        // 计算列宽
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        // 输出表头
        $line = '+';
        foreach ($widths as $width) {
            $line .= str_repeat('-', $width + 2) . '+';
        }
        echo $line . "\n";

        echo '|';
        foreach ($headers as $i => $header) {
            echo ' ' . str_pad($header, $widths[$i]) . ' |';
        }
        echo "\n" . $line . "\n";

        // 输出数据行
        foreach ($rows as $row) {
            echo '|';
            foreach ($row as $i => $cell) {
                echo ' ' . str_pad((string) $cell, $widths[$i]) . ' |';
            }
            echo "\n";
        }
        echo $line . "\n";
    }

    /**
     * 确认提示
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $suffix = $default ? '[Y/n]' : '[y/N]';
        echo "{$question} {$suffix}: ";
        $answer = trim(fgets(STDIN));

        if (empty($answer)) {
            return $default;
        }

        return strtolower($answer[0]) === 'y';
    }

    /**
     * 输入提示
     */
    protected function ask(string $question, ?string $default = null): string
    {
        $suffix = $default !== null ? "[{$default}]" : '';
        echo "{$question} {$suffix}: ";
        $answer = trim(fgets(STDIN));

        return $answer ?: ($default ?? '');
    }

    /**
     * 执行命令（子类实现）
     */
    abstract public function handle(): int;
}

/**
 * 命令行应用
 */
class Console
{
    /**
     * 注册的命令
     * @var array<string, Command>
     */
    private array $commands = [];

    /**
     * 注册命令
     */
    public function register(Command $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * 运行命令行应用
     */
    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? 'list';

        if ($commandName === 'list' || $commandName === 'help') {
            return $this->showHelp();
        }

        if (!isset($this->commands[$commandName])) {
            echo "\033[31mCommand '{$commandName}' not found.\033[0m\n";
            return 1;
        }

        $command = $this->commands[$commandName];
        $input = $this->parseInput(array_slice($argv, 2));
        $command->setInput($input);

        return $command->handle();
    }

    /**
     * 解析输入参数
     */
    private function parseInput(array $args): array
    {
        $arguments = [];
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            } elseif (str_starts_with($arg, '-')) {
                $options[substr($arg, 1)] = true;
            } else {
                $arguments[] = $arg;
            }
        }

        return ['arguments' => $arguments, 'options' => $options];
    }

    /**
     * 显示帮助信息
     */
    private function showHelp(): int
    {
        echo "\033[33mAvailable commands:\033[0m\n\n";

        foreach ($this->commands as $name => $command) {
            echo "  \033[32m{$name}\033[0m\t{$command->getDescription()}\n";
        }

        echo "\n";
        return 0;
    }
}
