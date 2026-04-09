<?php

namespace Framework\Auth;

use Framework\Session\Session;

/**
 * 认证管理类
 * 提供用户认证和授权功能
 */
class Auth
{
    /**
     * 单例实例
     */
    private static ?Auth $instance = null;

    /**
     * 当前认证用户
     */
    private ?array $user = null;

    /**
     * 用户提供者
     */
    private ?UserProviderInterface $provider = null;

    /**
     * 会话实例
     */
    private Session $session;

    /**
     * 认证守卫
     */
    private string $guard = 'session';

    public function __construct()
    {
        $this->session = Session::getInstance();
    }

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
     * 设置用户提供者
     */
    public function setProvider(UserProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * 尝试登录
     * 
     * @param array $credentials 凭证（如 ['email' => '', 'password' => '']）
     * @param bool $remember 是否记住登录
     * @return bool 是否登录成功
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        if (!$this->provider) {
            throw new \RuntimeException('未设置用户提供者');
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * 登录用户
     */
    public function login(array $user, bool $remember = false): void
    {
        $this->user = $user;
        $this->session->set('auth_user_id', $user['id'] ?? null);
        $this->session->regenerate();

        if ($remember) {
            // 生成记住令牌
            $token = bin2hex(random_bytes(32));
            $this->session->set('auth_remember_token', $token);
            
            // 设置长期 Cookie
            setcookie('remember_token', $token, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * 登出
     */
    public function logout(): void
    {
        $this->user = null;
        $this->session->remove('auth_user_id');
        $this->session->remove('auth_remember_token');
        $this->session->regenerate();

        // 清除记住 Cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }

    /**
     * 检查是否已登录
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 检查是否为访客
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * 获取当前用户
     */
    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        // 从会话恢复
        $userId = $this->session->get('auth_user_id');
        if ($userId && $this->provider) {
            $this->user = $this->provider->retrieveById($userId);
            return $this->user;
        }

        // 尝试从记住令牌恢复
        $rememberToken = $_COOKIE['remember_token'] ?? null;
        if ($rememberToken && $this->provider) {
            $this->user = $this->provider->retrieveByToken($rememberToken);
            if ($this->user) {
                $this->session->set('auth_user_id', $this->user['id']);
            }
            return $this->user;
        }

        return null;
    }

    /**
     * 获取用户 ID
     */
    public function id(): ?int
    {
        $user = $this->user();
        return $user['id'] ?? null;
    }

    /**
     * 验证密码
     */
    public function validate(array $credentials): bool
    {
        if (!$this->provider) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);
        return $user && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * 使用 ID 登录
     */
    public function loginUsingId(int $id, bool $remember = false): bool
    {
        if (!$this->provider) {
            return false;
        }

        $user = $this->provider->retrieveById($id);
        if ($user) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * 一次性登录（不保存会话）
     */
    public function once(array $credentials): bool
    {
        if (!$this->provider) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);
        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->user = $user;
            return true;
        }

        return false;
    }
}

/**
 * 用户提供者接口
 */
interface UserProviderInterface
{
    /**
     * 通过 ID 获取用户
     */
    public function retrieveById(int $id): ?array;

    /**
     * 通过凭证获取用户
     */
    public function retrieveByCredentials(array $credentials): ?array;

    /**
     * 验证凭证
     */
    public function validateCredentials(array $user, array $credentials): bool;

    /**
     * 通过令牌获取用户
     */
    public function retrieveByToken(string $token): ?array;
}

/**
 * 数据库用户提供者
 */
class DatabaseUserProvider implements UserProviderInterface
{
    private \Framework\Database\Connection $connection;
    private string $table;

    public function __construct(\Framework\Database\Connection $connection, string $table = 'users')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function retrieveById(int $id): ?array
    {
        $result = $this->connection->queryOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $result ?: null;
    }

    public function retrieveByCredentials(array $credentials): ?array
    {
        $email = $credentials['email'] ?? $credentials['username'] ?? null;
        if (!$email) {
            return null;
        }

        $result = $this->connection->queryOne(
            "SELECT * FROM {$this->table} WHERE email = ? OR username = ?",
            [$email, $email]
        );
        return $result ?: null;
    }

    public function validateCredentials(array $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? '';
        return password_verify($password, $user['password'] ?? '');
    }

    public function retrieveByToken(string $token): ?array
    {
        $result = $this->connection->queryOne(
            "SELECT * FROM {$this->table} WHERE remember_token = ?",
            [$token]
        );
        return $result ?: null;
    }
}
