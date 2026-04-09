<?php

namespace Framework\Storage;

/**
 * 本地文件存储驱动
 */
class LocalStorage implements StorageDriverInterface
{
    /**
     * 根目录
     */
    private string $root;

    /**
     * URL 前缀
     */
    private string $urlPrefix;

    public function __construct(string $root, string $urlPrefix = '/storage')
    {
        $this->root = rtrim($root, '/');
        $this->urlPrefix = rtrim($urlPrefix, '/');

        // 确保根目录存在
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    /**
     * 获取完整路径
     */
    private function getFullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * 写入文件
     */
    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);

        // 确保目录存在
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($fullPath, $contents, LOCK_EX) !== false;
    }

    /**
     * 读取文件
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * 检查文件是否存在
     */
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * 删除文件
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return true;
        }

        return unlink($fullPath);
    }

    /**
     * 复制文件
     */
    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        // 确保目标目录存在
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return copy($fromPath, $toPath);
    }

    /**
     * 移动文件
     */
    public function move(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        // 确保目标目录存在
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return rename($fromPath, $toPath);
    }

    /**
     * 获取文件大小
     */
    public function size(string $path): int
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return 0;
        }

        return filesize($fullPath);
    }

    /**
     * 获取文件最后修改时间
     */
    public function lastModified(string $path): int
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return 0;
        }

        return filemtime($fullPath);
    }

    /**
     * 获取文件 MIME 类型
     */
    public function mimeType(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        return $mimeType ?: null;
    }

    /**
     * 列出目录文件
     */
    public function files(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            if (is_file($itemPath)) {
                $files[] = $directory ? "{$directory}/{$item}" : $item;
            }
        }

        return $files;
    }

    /**
     * 列出目录
     */
    public function directories(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            if (is_dir($itemPath)) {
                $directories[] = $directory ? "{$directory}/{$item}" : $item;
            }
        }

        return $directories;
    }

    /**
     * 创建目录
     */
    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0755, true);
    }

    /**
     * 删除目录
     */
    public function deleteDirectory(string $directory): bool
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return true;
        }

        return $this->removeDirectory($fullPath);
    }

    /**
     * 递归删除目录
     */
    private function removeDirectory(string $directory): bool
    {
        $items = array_diff(scandir($directory), ['.', '..']);

        foreach ($items as $item) {
            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }

    /**
     * 获取文件 URL
     */
    public function url(string $path): string
    {
        return $this->urlPrefix . '/' . ltrim($path, '/');
    }

    /**
     * 追加内容到文件
     */
    public function append(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($fullPath, $contents, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * 在文件开头添加内容
     */
    public function prepend(string $path, string $contents): bool
    {
        $existing = $this->get($path) ?? '';
        return $this->put($path, $contents . $existing);
    }

    /**
     * 获取根目录
     */
    public function getRoot(): string
    {
        return $this->root;
    }
}
