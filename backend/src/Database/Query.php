<?php

namespace Framework\Database;

/**
 * 查询构建器
 * 提供链式调用构建 SQL 查询
 */
class Query
{
    /**
     * 数据库连接
     */
    private Connection $connection;

    /**
     * 表名
     */
    private string $table;

    /**
     * WHERE 条件
     * @var array<string, mixed>
     */
    private array $wheres = [];

    /**
     * 排序
     * @var array<string, string>
     */
    private array $orders = [];

    /**
     * 限制数量
     */
    private ?int $limit = null;

    /**
     * 偏移量
     */
    private ?int $offset = null;

    /**
     * 查询字段
     */
    private string $columns = '*';

    /**
     * 允许的列名白名单
     * @var array<string>
     */
    private array $allowedColumns = [];

    /**
     * 允许的操作符白名单
     * @var array<string>
     */
    private array $allowedOperators = [
        '=', '!=', '<>', '<', '>', '<=', '>=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
        'IS NULL', 'IS NOT NULL', 'BETWEEN', 'NOT BETWEEN'
    ];

    /**
     * 允许的排序方向
     * @var array<string>
     */
    private array $allowedDirections = ['ASC', 'DESC'];

    /**
     * 是否已经获取过表列信息
     */
    private bool $hasFetchedColumns = false;

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * 设置允许的列名白名单
     * 
     * @param array<string> $columns 允许的列名列表
     */
    public function setAllowedColumns(array $columns): self
    {
        $this->allowedColumns = $columns;
        $this->hasFetchedColumns = true;
        return $this;
    }

    /**
     * 获取允许的列名白名单
     * 如果未手动设置，会尝试从数据库获取表结构
     * 
     * @return array<string>
     */
    public function getAllowedColumns(): array
    {
        if (!$this->hasFetchedColumns && empty($this->allowedColumns)) {
            $this->fetchTableColumns();
        }
        return $this->allowedColumns;
    }

    /**
     * 从数据库获取表的列信息
     */
    private function fetchTableColumns(): void
    {
        try {
            $driver = $this->connection->getDriver();
            $columns = [];

            if ($driver === 'sqlite') {
                $sql = "PRAGMA table_info({$this->table})";
                $result = $this->connection->query($sql);
                foreach ($result as $row) {
                    $columns[] = $row['name'];
                }
            } else {
                $database = $this->getDatabaseName();
                if ($database) {
                    $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? 
                            ORDER BY ORDINAL_POSITION";
                    $result = $this->connection->query($sql, [$database, $this->table]);
                    foreach ($result as $row) {
                        $columns[] = $row['COLUMN_NAME'];
                    }
                }
            }

            $this->allowedColumns = $columns;
        } catch (\Exception $e) {
            // 如果获取失败，保持空列表，后续校验会使用严格模式
        }

        $this->hasFetchedColumns = true;
    }

    /**
     * 获取当前数据库名
     */
    private function getDatabaseName(): ?string
    {
        try {
            $pdo = $this->connection->getPdo();
            $stmt = $pdo->query("SELECT DATABASE()");
            if ($stmt) {
                $result = $stmt->fetch();
                return $result ? $result[0] : null;
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
        return null;
    }

    /**
     * 校验列名是否在白名单中
     * 
     * @param string $column 列名
     * @return bool
     * @throws \InvalidArgumentException 如果列名不在白名单中
     */
    private function validateColumn(string $column): bool
    {
        $allowedColumns = $this->getAllowedColumns();

        // 如果白名单为空（无法从数据库获取），使用语法校验
        if (empty($allowedColumns)) {
            return $this->validateColumnSyntax($column);
        }

        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException(
                sprintf("无效的列名 '%s'，不在允许的列名白名单中", $column)
            );
        }

        return true;
    }

    /**
     * 语法校验列名（当无法获取白名单时使用）
     * 只允许字母、数字、下划线，且不能以数字开头
     * 
     * @param string $column 列名
     * @return bool
     * @throws \InvalidArgumentException 如果列名语法无效
     */
    private function validateColumnSyntax(string $column): bool
    {
        // 支持带点号的列名（如 table.column）
        $parts = explode('.', $column);
        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
                throw new \InvalidArgumentException(
                    sprintf("无效的列名语法 '%s'", $column)
                );
            }
        }
        return true;
    }

    /**
     * 校验操作符是否在白名单中
     * 
     * @param string $operator 操作符
     * @return bool
     * @throws \InvalidArgumentException 如果操作符不在白名单中
     */
    private function validateOperator(string $operator): bool
    {
        $operator = strtoupper(trim($operator));

        if (!in_array($operator, $this->allowedOperators, true)) {
            throw new \InvalidArgumentException(
                sprintf("无效的操作符 '%s'，不在允许的操作符白名单中", $operator)
            );
        }

        return true;
    }

    /**
     * 校验排序方向
     * 
     * @param string $direction 排序方向
     * @return string 标准化后的排序方向（ASC/DESC）
     * @throws \InvalidArgumentException 如果排序方向无效
     */
    private function validateDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));

        if (!in_array($direction, $this->allowedDirections, true)) {
            throw new \InvalidArgumentException(
                sprintf("无效的排序方向 '%s'，只允许 ASC 或 DESC", $direction)
            );
        }

        return $direction;
    }

    /**
     * 设置查询字段
     */
    public function select(string $columns = '*'): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * WHERE 条件
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        // 支持 where('name', 'value') 和 where('name', '=', 'value')
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        // 校验列名和操作符
        $this->validateColumn($column);
        if ($operator !== null) {
            $this->validateOperator($operator);
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'logic' => 'AND',
        ];

        return $this;
    }

    /**
     * OR WHERE 条件
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        // 校验列名和操作符
        $this->validateColumn($column);
        if ($operator !== null) {
            $this->validateOperator($operator);
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'logic' => 'OR',
        ];

        return $this;
    }

    /**
     * 排序
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        // 校验列名和排序方向
        $this->validateColumn($column);
        $validatedDirection = $this->validateDirection($direction);

        $this->orders[] = [
            'column' => $column,
            'direction' => $validatedDirection,
        ];
        return $this;
    }

    /**
     * 限制数量
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * 偏移量
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * 获取所有结果
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        $bindings = $this->getBindings();
        return $this->connection->query($sql, $bindings);
    }

    /**
     * 获取单条结果
     */
    public function first(): array|false
    {
        $this->limit(1);
        $sql = $this->buildSelectSql();
        $bindings = $this->getBindings();
        return $this->connection->queryOne($sql, $bindings);
    }

    /**
     * 统计数量
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $whereSql = $this->buildWhereSql();
        if ($whereSql) {
            $sql .= ' WHERE ' . $whereSql;
        }
        $bindings = $this->getBindings();
        $result = $this->connection->queryOne($sql, $bindings);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * 构建 SELECT SQL
     */
    private function buildSelectSql(): string
    {
        $sql = "SELECT {$this->columns} FROM {$this->table}";
        
        $whereSql = $this->buildWhereSql();
        if ($whereSql) {
            $sql .= ' WHERE ' . $whereSql;
        }

        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $sql;
    }

    /**
     * 构建 WHERE SQL
     */
    private function buildWhereSql(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $index => $where) {
            $logic = $index > 0 ? ' ' . $where['logic'] . ' ' : '';
            $operator = strtoupper(trim($where['operator']));
            
            // 根据操作符类型构建不同的 SQL 片段
            if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                $parts[] = $logic . "{$where['column']} {$operator}";
            } elseif (in_array($operator, ['IN', 'NOT IN'], true)) {
                // IN 操作符需要特殊处理
                $placeholders = is_array($where['value']) 
                    ? implode(', ', array_fill(0, count($where['value']), '?'))
                    : '?';
                $parts[] = $logic . "{$where['column']} {$operator} ({$placeholders})";
            } elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                // BETWEEN 操作符需要两个占位符
                $parts[] = $logic . "{$where['column']} {$operator} ? AND ?";
            } else {
                // 普通操作符
                $parts[] = $logic . "{$where['column']} {$operator} ?";
            }
        }

        return implode('', $parts);
    }

    /**
     * 获取绑定参数
     */
    private function getBindings(): array
    {
        $bindings = [];
        foreach ($this->wheres as $where) {
            $operator = strtoupper(trim($where['operator']));
            
            // 跳过不需要绑定值的操作符
            if (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                continue;
            }
            
            // 处理 IN/NOT IN 操作符
            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                if (is_array($where['value'])) {
                    foreach ($where['value'] as $val) {
                        $bindings[] = $val;
                    }
                } else {
                    $bindings[] = $where['value'];
                }
                continue;
            }
            
            // 处理 BETWEEN/NOT BETWEEN 操作符
            if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                if (is_array($where['value']) && count($where['value']) >= 2) {
                    $bindings[] = $where['value'][0];
                    $bindings[] = $where['value'][1];
                }
                continue;
            }
            
            // 普通操作符
            $bindings[] = $where['value'];
        }
        return $bindings;
    }
}
