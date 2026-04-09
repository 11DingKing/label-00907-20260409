<?php

namespace Framework\Database;

use PDO;
use PDOException;

/**
 * 数据库连接类
 * 封装 PDO 连接，支持 MySQL 和 SQLite
 */
class Connection
{
    /**
     * PDO 实例
     */
    private ?PDO $pdo = null;

    /**
     * 数据库配置
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * 是否在事务中
     */
    private bool $inTransaction = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 获取 PDO 实例（懒加载）
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * 建立数据库连接
     */
    private function connect(): void
    {
        $driver = $this->config['driver'] ?? 'mysql';
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $dsn = $this->buildSqliteDsn();
                $this->pdo = new PDO($dsn, null, null, $options);
            } else {
                $dsn = $this->buildMysqlDsn();
                $this->pdo = new PDO(
                    $dsn,
                    $this->config['username'] ?? '',
                    $this->config['password'] ?? '',
                    $options
                );
            }
        } catch (PDOException $e) {
            throw new \RuntimeException("数据库连接失败: " . $e->getMessage());
        }
    }

    /**
     * 构建 MySQL DSN
     */
    private function buildMysqlDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? 3306,
            $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * 构建 SQLite DSN
     */
    private function buildSqliteDsn(): string
    {
        $database = $this->config['database'] ?? ':memory:';
        return "sqlite:{$database}";
    }

    /**
     * 获取数据库驱动类型
     */
    public function getDriver(): string
    {
        return $this->config['driver'] ?? 'mysql';
    }

    /**
     * 执行查询，返回所有结果
     * 
     * @param string $sql SQL 语句
     * @param array $bindings 绑定参数
     * @return array<array<string, mixed>> 结果集
     */
    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询，返回单条结果
     * 
     * @param string $sql SQL 语句
     * @param array $bindings 绑定参数
     * @return array<string, mixed>|false 结果或 false
     */
    public function queryOne(string $sql, array $bindings = [])
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch();
        return $result ?: false;
    }

    /**
     * 执行更新/插入/删除，返回影响行数
     * 
     * @param string $sql SQL 语句
     * @param array $bindings 绑定参数
     * @return int 影响行数
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * 获取最后插入的 ID
     */
    public function lastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    /**
     * 开始事务
     */
    public function beginTransaction(): void
    {
        if (!$this->inTransaction) {
            $this->getPdo()->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * 提交事务
     */
    public function commit(): void
    {
        if ($this->inTransaction) {
            $this->getPdo()->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * 回滚事务
     */
    public function rollback(): void
    {
        if ($this->inTransaction) {
            $this->getPdo()->rollBack();
            $this->inTransaction = false;
        }
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->pdo = null;
    }
}
