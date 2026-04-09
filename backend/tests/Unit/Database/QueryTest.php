<?php

namespace Tests\Unit\Database;

use Framework\Database\Query;
use Framework\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Query（查询构建器）测试用例
 */
class QueryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        // 使用内存 SQLite 进行测试
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test_db',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8mb4',
        ];
        
        $this->connection = new Connection($config);
    }

    /**
     * 测试 WHERE 条件
     */
    public function testWhere(): void
    {
        $query = new Query($this->connection, 'users');
        $query->where('name', 'test');
        
        // 验证 SQL 构建（通过反射检查私有方法）
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildWhereSql');
        $method->setAccessible(true);
        
        $whereSql = $method->invoke($query);
        $this->assertStringContainsString('name = ?', $whereSql);
    }

    /**
     * 测试 OR WHERE 条件
     */
    public function testOrWhere(): void
    {
        $query = new Query($this->connection, 'users');
        $query->where('name', 'test')
              ->orWhere('email', 'test@example.com');
        
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildWhereSql');
        $method->setAccessible(true);
        
        $whereSql = $method->invoke($query);
        $this->assertStringContainsString('OR', $whereSql);
    }

    /**
     * 测试排序
     */
    public function testOrderBy(): void
    {
        $query = new Query($this->connection, 'users');
        $query->orderBy('created_at', 'DESC');
        
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildSelectSql');
        $method->setAccessible(true);
        
        $sql = $method->invoke($query);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('created_at DESC', $sql);
    }

    /**
     * 测试 LIMIT
     */
    public function testLimit(): void
    {
        $query = new Query($this->connection, 'users');
        $query->limit(10);
        
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildSelectSql');
        $method->setAccessible(true);
        
        $sql = $method->invoke($query);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    /**
     * 测试 OFFSET
     */
    public function testOffset(): void
    {
        $query = new Query($this->connection, 'users');
        $query->limit(10)->offset(20);
        
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildSelectSql');
        $method->setAccessible(true);
        
        $sql = $method->invoke($query);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    /**
     * 测试 SELECT 字段
     */
    public function testSelect(): void
    {
        $query = new Query($this->connection, 'users');
        $query->select('id, name, email');
        
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('buildSelectSql');
        $method->setAccessible(true);
        
        $sql = $method->invoke($query);
        $this->assertStringContainsString('SELECT id, name, email', $sql);
    }

    /**
     * 测试链式调用
     */
    public function testChaining(): void
    {
        $query = new Query($this->connection, 'users');
        $result = $query->where('status', 'active')
                       ->orderBy('created_at', 'DESC')
                       ->limit(10)
                       ->offset(0);
        
        $this->assertInstanceOf(Query::class, $result);
    }
}
