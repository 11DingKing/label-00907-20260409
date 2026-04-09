<?php

namespace Framework\Database;

use Framework\Core\Container;
use Framework\Config\Config;

/**
 * ORM 模型基类
 * 提供 CRUD 操作和查询构建
 */
abstract class Model
{
    /**
     * 表名（子类可覆盖）
     */
    protected string $table = '';

    /**
     * 主键名（默认 id）
     */
    protected string $primaryKey = 'id';

    /**
     * 是否自动维护时间戳
     */
    protected bool $timestamps = true;

    /**
     * 创建时间字段
     */
    protected string $createdAt = 'created_at';

    /**
     * 更新时间字段
     */
    protected string $updatedAt = 'updated_at';

    /**
     * 模型属性
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * 原始属性（用于检测变更）
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * 是否为新模型
     */
    protected bool $exists = false;

    /**
     * 允许批量赋值的字段
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * 隐藏字段（序列化时不包含）
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * 静态数据库配置（用于 Swoole 环境）
     * 在 Swoole 启动前设置，Worker 进程可直接使用
     * @var array<string, mixed>|null
     */
    private static ?array $databaseConfig = null;

    /**
     * 设置全局数据库配置
     * 应在 Swoole 启动前调用，确保 Worker 进程能获取正确配置
     */
    public static function setDatabaseConfig(array $config): void
    {
        self::$databaseConfig = $config;
    }

    /**
     * 获取全局数据库配置
     */
    public static function getDatabaseConfigStatic(): ?array
    {
        return self::$databaseConfig;
    }

    /**
     * 获取数据库连接
     */
    protected function getConnection(): Connection
    {
        $container = Container::getInstance();
        
        // 尝试从容器获取连接池
        if ($container->has(ConnectionPool::class)) {
            $pool = $container->make(ConnectionPool::class);
            return $pool->getConnection();
        }

        // 尝试从容器获取单独的连接
        if ($container->has(Connection::class)) {
            return $container->make(Connection::class);
        }

        // 否则创建新连接（使用静态配置或环境变量）
        $config = $this->getDatabaseConfig();
        return new Connection($config);
    }

    /**
     * 获取数据库配置
     * 优先使用静态配置（Swoole 环境），其次从环境变量获取
     */
    protected function getDatabaseConfig(): array
    {
        // 优先使用静态配置（Swoole Worker 进程中最可靠）
        if (self::$databaseConfig !== null) {
            return self::$databaseConfig;
        }

        // 从环境变量获取（传统 FPM 模式）
        $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $port = (int) ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $database = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '';
        $username = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
        $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $charset = $_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
        ];
    }

    /**
     * 获取表名
     */
    public function getTable(): string
    {
        if ($this->table) {
            return $this->table;
        }

        // 自动推断表名（类名转复数）
        $className = basename(str_replace('\\', '/', static::class));
        return strtolower($className) . 's';
    }

    /**
     * 设置属性
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * 获取属性
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * 批量设置属性
     */
    public function fill(array $attributes): self
    {
        // 如果有 fillable，只允许填充指定字段
        if (!empty($this->fillable)) {
            foreach ($attributes as $key => $value) {
                if (in_array($key, $this->fillable)) {
                    $this->attributes[$key] = $value;
                }
            }
        } else {
            // 否则允许填充所有字段
            foreach ($attributes as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * 获取所有属性
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        // 过滤隐藏字段
        if (!empty($this->hidden)) {
            foreach ($this->hidden as $field) {
                unset($attributes[$field]);
            }
        }

        return $attributes;
    }

    /**
     * 查找单条记录
     */
    public static function find(int $id): ?static
    {
        $model = new static();
        $connection = $model->getConnection();
        
        $sql = "SELECT * FROM {$model->getTable()} WHERE {$model->primaryKey} = ? LIMIT 1";
        $result = $connection->queryOne($sql, [$id]);

        if ($result === false) {
            return null;
        }

        $model->attributes = $result;
        $model->original = $result;
        $model->exists = true;

        return $model;
    }

    /**
     * 创建查询构建器
     */
    public static function query(): Query
    {
        $model = new static();
        $connection = $model->getConnection();
        return new Query($connection, $model->getTable());
    }

    /**
     * WHERE 查询
     */
    public static function where(string $column, mixed $operator = null, mixed $value = null): Query
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * 分页查询
     * 
     * @param int $pageSize 每页数量
     * @param int|null $page 当前页码（null 则从请求中获取）
     * @return Paginator 分页器对象
     */
    public static function paginate(int $pageSize = 15, ?int $page = null): Paginator
    {
        $model = new static();

        // 获取当前页码
        if ($page === null) {
            $page = (int) ($_GET['page'] ?? 1);
        }
        $page = max(1, $page);

        // 构建查询
        $query = static::query();
        
        // 统计总数
        $total = $query->count();

        // 获取数据
        $offset = ($page - 1) * $pageSize;
        $rows = $query->offset($offset)->limit($pageSize)->get();

        // 将数组转换为 Model 实例
        $items = array_map(function ($row) {
            $instance = new static();
            $instance->attributes = $row;
            $instance->original = $row;
            $instance->exists = true;
            return $instance;
        }, $rows);

        return new Paginator($items, $total, $page, $pageSize);
    }

    /**
     * 保存模型（插入或更新）
     */
    public function save(): bool
    {
        $connection = $this->getConnection();

        // 自动设置时间戳
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $this->attributes[$this->createdAt] = $now;
            }
            $this->attributes[$this->updatedAt] = $now;
        }

        if ($this->exists) {
            // 更新
            return $this->update($connection);
        } else {
            // 插入
            return $this->insert($connection);
        }
    }

    /**
     * 插入记录
     */
    private function insert(Connection $connection): bool
    {
        $columns = array_keys($this->attributes);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($this->attributes);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->getTable(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $affected = $connection->execute($sql, $values);
        
        if ($affected > 0) {
            $this->attributes[$this->primaryKey] = $connection->lastInsertId();
            $this->original = $this->attributes;
            $this->exists = true;
            return true;
        }

        return false;
    }

    /**
     * 更新记录
     */
    private function update(Connection $connection): bool
    {
        $id = $this->attributes[$this->primaryKey] ?? null;
        if (!$id) {
            return false;
        }

        $columns = array_keys($this->attributes);
        $setParts = [];
        $values = [];

        foreach ($columns as $column) {
            if ($column !== $this->primaryKey) {
                $setParts[] = "{$column} = ?";
                $values[] = $this->attributes[$column];
            }
        }

        $values[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->getTable(),
            implode(', ', $setParts),
            $this->primaryKey
        );

        $affected = $connection->execute($sql, $values);
        
        if ($affected > 0) {
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    /**
     * 删除记录
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $id = $this->attributes[$this->primaryKey] ?? null;
        if (!$id) {
            return false;
        }

        $connection = $this->getConnection();
        $sql = "DELETE FROM {$this->getTable()} WHERE {$this->primaryKey} = ?";
        $affected = $connection->execute($sql, [$id]);

        if ($affected > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * 魔术方法：获取属性
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * 魔术方法：设置属性
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * 魔术方法：检查属性是否存在
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
