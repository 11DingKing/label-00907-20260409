<?php

namespace Framework\Config;

use Dotenv\Dotenv;

/**
 * 配置管理类
 * 支持 .env 文件、多环境配置、点号访问、配置缓存
 */
class Config
{
    /**
     * 单例实例
     */
    private static ?Config $instance = null;

    /**
     * 配置数据
     * @var array<string, mixed>
     */
    private array $items = [];

    /**
     * 配置目录
     */
    private string $configPath = '';

    /**
     * 项目根目录
     */
    private string $basePath = '';

    /**
     * 是否已加载
     */
    private bool $loaded = false;

    /**
     * 是否已加载 .env
     */
    private bool $envLoaded = false;

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
     * 重置单例（用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置项目根目录
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = rtrim($path, '/');
        return $this;
    }

    /**
     * 设置配置目录
     */
    public function setPath(string $path): self
    {
        $this->configPath = rtrim($path, '/');
        return $this;
    }

    /**
     * 加载 .env 文件
     */
    public function loadEnvFile(): self
    {
        if ($this->envLoaded) {
            return $this;
        }

        $basePath = $this->basePath ?: dirname($this->configPath);
        
        // 检查 .env 文件是否存在
        if (file_exists($basePath . '/.env')) {
            try {
                $dotenv = Dotenv::createImmutable($basePath);
                $dotenv->safeLoad();
            } catch (\Exception $e) {
                // .env 加载失败，使用默认值
            }
        }

        $this->envLoaded = true;
        return $this;
    }

    /**
     * 加载所有配置文件
     */
    public function load(): self
    {
        if ($this->loaded) {
            return $this;
        }

        // 先加载 .env 文件
        $this->loadEnvFile();

        // 加载配置目录下所有 PHP 文件
        if (!empty($this->configPath)) {
            $files = glob($this->configPath . '/*.php');
            foreach ($files as $file) {
                $key = basename($file, '.php');
                $this->items[$key] = require $file;
            }
        }

        // 加载环境变量覆盖
        $this->loadEnvOverrides();

        $this->loaded = true;
        return $this;
    }

    /**
     * 从环境变量加载配置覆盖
     */
    private function loadEnvOverrides(): void
    {
        // 应用环境配置
        $appEnv = $this->getEnv('APP_ENV', 'development');
        $this->set('app.env', $appEnv);
        $this->set('app.debug', $this->getEnvBool('APP_DEBUG', $appEnv !== 'production'));
        
        // 路由缓存配置（生产环境默认启用）
        $routeCacheDefault = ($appEnv === 'production');
        $this->set('app.route_cache', $this->getEnvBool('ROUTE_CACHE_ENABLED', $routeCacheDefault));
        $this->set('app.route_cache_dir', $this->getEnv('ROUTE_CACHE_DIR', ''));

        // 数据库配置
        if ($this->hasEnv('DB_HOST')) {
            $this->set('database.host', $this->getEnv('DB_HOST'));
        }
        if ($this->hasEnv('DB_PORT')) {
            $this->set('database.port', (int) $this->getEnv('DB_PORT'));
        }
        if ($this->hasEnv('DB_DATABASE')) {
            $this->set('database.database', $this->getEnv('DB_DATABASE'));
        }
        if ($this->hasEnv('DB_USERNAME')) {
            $this->set('database.username', $this->getEnv('DB_USERNAME'));
        }
        if ($this->hasEnv('DB_PASSWORD')) {
            $this->set('database.password', $this->getEnv('DB_PASSWORD'));
        }
        if ($this->hasEnv('DB_CHARSET')) {
            $this->set('database.charset', $this->getEnv('DB_CHARSET', 'utf8mb4'));
        }

        // 数据库连接池配置
        $this->set('database.pool.max_connections', (int) $this->getEnv('DB_POOL_MAX', 10));
        $this->set('database.pool.wait_timeout', (float) $this->getEnv('DB_POOL_TIMEOUT', 3.0));

        // Redis 配置
        if ($this->hasEnv('REDIS_HOST')) {
            $this->set('redis.host', $this->getEnv('REDIS_HOST', '127.0.0.1'));
            $this->set('redis.port', (int) $this->getEnv('REDIS_PORT', 6379));
            $this->set('redis.password', $this->getEnv('REDIS_PASSWORD', ''));
            $this->set('redis.database', (int) $this->getEnv('REDIS_DATABASE', 0));
        }

        // Swoole 配置
        $this->set('swoole.host', $this->getEnv('SWOOLE_HOST', '0.0.0.0'));
        $this->set('swoole.port', (int) $this->getEnv('SWOOLE_PORT', 9501));
        $this->set('swoole.workers', (int) $this->getEnv('SWOOLE_WORKERS', 0)); // 0 = auto
    }

    /**
     * 获取环境变量
     */
    private function getEnv(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }

    /**
     * 检查环境变量是否存在
     */
    private function hasEnv(string $key): bool
    {
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }

    /**
     * 获取布尔类型环境变量
     */
    private function getEnvBool(string $key, bool $default = false): bool
    {
        $value = $this->getEnv($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 获取配置值（支持点号访问）
     * 
     * @param string $key 配置键，如 'database.host'
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->items;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 设置配置值（支持点号访问）
     */
    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);
        $items = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $items[$segment] = $value;
            } else {
                if (!isset($items[$segment]) || !is_array($items[$segment])) {
                    $items[$segment] = [];
                }
                $items = &$items[$segment];
            }
        }

        return $this;
    }

    /**
     * 检查配置是否存在
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * 获取所有配置
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 合并配置
     */
    public function merge(string $key, array $value): self
    {
        $existing = $this->get($key, []);
        if (is_array($existing)) {
            $this->set($key, array_merge($existing, $value));
        }
        return $this;
    }
}

/**
 * 全局配置助手函数
 */
function config(string $key = null, mixed $default = null): mixed
{
    $config = Config::getInstance();
    if ($key === null) {
        return $config->all();
    }
    return $config->get($key, $default);
}
