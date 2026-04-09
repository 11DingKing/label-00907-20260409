<?php

namespace Framework\Session;

/**
 * 会话管理类
 * 支持多种存储驱动
 */
class Session
{
    /**
     * 单例实例
     */
    private static ?Session $instance = null;

    /**
     * 会话数据
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * 会话 ID
     */
    private string $id = '';

    /**
     * 是否已启动
     */
    private bool $started = false;

    /**
     * 存储驱动
     */
    private SessionHandlerInterface $handler;

    /**
     * 会话配置
     */
    private array $config = [
        'name' => 'PHPSESSID',
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
    ];

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
     * 设置存储驱动
     */
    public function setHandler(SessionHandlerInterface $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * 启动会话
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        // 从 Cookie 获取会话 ID
        $this->id = $_COOKIE[$this->config['name']] ?? '';

        if (empty($this->id)) {
            $this->id = $this->generateId();
            $this->setCookie();
        }

        // 加载会话数据
        if (isset($this->handler)) {
            $data = $this->handler->read($this->id);
            $this->data = $data ? unserialize($data) : [];
        } else {
            // 使用原生 PHP 会话
            session_name($this->config['name']);
            session_start();
            $this->data = &$_SESSION;
        }

        $this->started = true;
        return true;
    }

    /**
     * 生成会话 ID
     */
    private function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 设置会话 Cookie
     */
    private function setCookie(): void
    {
        setcookie(
            $this->config['name'],
            $this->id,
            [
                'expires' => time() + $this->config['lifetime'],
                'path' => $this->config['path'],
                'domain' => $this->config['domain'],
                'secure' => $this->config['secure'],
                'httponly' => $this->config['httponly'],
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * 获取会话值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $this->data[$key] ?? $default;
    }

    /**
     * 设置会话值
     */
    public function set(string $key, mixed $value): self
    {
        $this->start();
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * 检查会话键是否存在
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($this->data[$key]);
    }

    /**
     * 删除会话值
     */
    public function remove(string $key): self
    {
        $this->start();
        unset($this->data[$key]);
        return $this;
    }

    /**
     * 获取所有会话数据
     */
    public function all(): array
    {
        $this->start();
        return $this->data;
    }

    /**
     * 清空会话数据
     */
    public function clear(): self
    {
        $this->start();
        $this->data = [];
        return $this;
    }

    /**
     * 重新生成会话 ID
     */
    public function regenerate(bool $deleteOld = true): bool
    {
        if ($deleteOld && isset($this->handler)) {
            $this->handler->destroy($this->id);
        }

        $this->id = $this->generateId();
        $this->setCookie();

        return true;
    }

    /**
     * 销毁会话
     */
    public function destroy(): bool
    {
        $this->data = [];

        if (isset($this->handler)) {
            $this->handler->destroy($this->id);
        }

        // 删除 Cookie
        setcookie($this->config['name'], '', time() - 3600, $this->config['path']);

        $this->started = false;
        return true;
    }

    /**
     * 保存会话
     */
    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        if (isset($this->handler)) {
            $this->handler->write($this->id, serialize($this->data));
        }
    }

    /**
     * 获取会话 ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 闪存数据（只在下次请求有效）
     */
    public function flash(string $key, mixed $value): self
    {
        $this->set('_flash.' . $key, $value);
        return $this;
    }

    /**
     * 获取闪存数据
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get('_flash.' . $key, $default);
        $this->remove('_flash.' . $key);
        return $value;
    }

    /**
     * 析构时保存会话
     */
    public function __destruct()
    {
        $this->save();
    }
}

/**
 * 会话处理器接口
 */
interface SessionHandlerInterface
{
    public function read(string $id): string;
    public function write(string $id, string $data): bool;
    public function destroy(string $id): bool;
    public function gc(int $maxLifetime): bool;
}
