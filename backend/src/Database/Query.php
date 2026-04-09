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

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
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
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
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
            $parts[] = $logic . "{$where['column']} {$where['operator']} ?";
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
            $bindings[] = $where['value'];
        }
        return $bindings;
    }
}
