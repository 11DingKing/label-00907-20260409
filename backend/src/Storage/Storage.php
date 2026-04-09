<?php

namespace Framework\Storage;

/**
 * 文件存储管理类
 * 支持本地存储和云存储
 */
class Storage
{
    /**
     * 单例实例
     */
    private static ?Storage $instance = null;

    /**
     * 存储驱动
     */
    private StorageDriverInterface $driver;

    /**
     * 默认磁盘
     */
    private string $defaultDisk = 'local';

    /**
     * 磁盘配置
     */
    private array $disks = [];

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
     * 设置磁盘配置
     */
    public function setDisks(array $disks): self
    {
        $this->disks = $disks;
        return $this;
    }

    /**
     * 设置默认磁盘
     */
    public function setDefaultDisk(string $disk): self
    {
        $this->defaultDisk = $disk;
        return $this;
    }

    /**
     * 切换磁盘
     */
    public function disk(string $name): self
    {
        $clone = clone $this;
        $clone->driver = $this->createDriver($name);
        return $clone;
    }

    /**
     * 创建驱动
     */
    private function createDriver(string $name): StorageDriverInterface
    {
        $config = $this->disks[$name] ?? [];
        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => new LocalStorage($config['root'] ?? '/tmp/storage'),
            default => throw new \RuntimeException("不支持的存储驱动: {$driver}"),
        };
    }

    /**
     * 获取当前驱动
     */
    private function getDriver(): StorageDriverInterface
    {
        if (!isset($this->driver)) {
            $this->driver = $this->createDriver($this->defaultDisk);
        }
        return $this->driver;
    }

    /**
     * 写入文件
     */
    public function put(string $path, string $contents): bool
    {
        return $this->getDriver()->put($path, $contents);
    }

    /**
     * 读取文件
     */
    public function get(string $path): ?string
    {
        return $this->getDriver()->get($path);
    }

    /**
     * 检查文件是否存在
     */
    public function exists(string $path): bool
    {
        return $this->getDriver()->exists($path);
    }

    /**
     * 删除文件
     */
    public function delete(string $path): bool
    {
        return $this->getDriver()->delete($path);
    }

    /**
     * 复制文件
     */
    public function copy(string $from, string $to): bool
    {
        return $this->getDriver()->copy($from, $to);
    }

    /**
     * 移动文件
     */
    public function move(string $from, string $to): bool
    {
        return $this->getDriver()->move($from, $to);
    }

    /**
     * 获取文件大小
     */
    public function size(string $path): int
    {
        return $this->getDriver()->size($path);
    }

    /**
     * 获取文件最后修改时间
     */
    public function lastModified(string $path): int
    {
        return $this->getDriver()->lastModified($path);
    }

    /**
     * 获取文件 MIME 类型
     */
    public function mimeType(string $path): ?string
    {
        return $this->getDriver()->mimeType($path);
    }

    /**
     * 列出目录文件
     */
    public function files(string $directory = ''): array
    {
        return $this->getDriver()->files($directory);
    }

    /**
     * 列出目录（包含子目录）
     */
    public function directories(string $directory = ''): array
    {
        return $this->getDriver()->directories($directory);
    }

    /**
     * 创建目录
     */
    public function makeDirectory(string $path): bool
    {
        return $this->getDriver()->makeDirectory($path);
    }

    /**
     * 删除目录
     */
    public function deleteDirectory(string $directory): bool
    {
        return $this->getDriver()->deleteDirectory($directory);
    }

    /**
     * 获取文件 URL
     */
    public function url(string $path): string
    {
        return $this->getDriver()->url($path);
    }
}
