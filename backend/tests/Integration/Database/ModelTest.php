<?php

namespace Tests\Integration\Database;

use Framework\Database\Model;
use Framework\Database\Connection;
use Framework\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Model（ORM模型）集成测试
 * 使用 SQLite 内存数据库进行真实测试
 */
class ModelTest extends TestCase
{
    private static ?Connection $connection = null;

    public static function setUpBeforeClass(): void
    {
        // 创建 SQLite 内存数据库连接
        self::$connection = new Connection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // 创建测试表
        self::$connection->execute('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )
        ');

        // 插入测试数据
        self::$connection->execute(
            "INSERT INTO users (username, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            ['admin', 'admin@example.com', 'hashed_password', '2026-01-01 00:00:00', '2026-01-01 00:00:00']
        );
        self::$connection->execute(
            "INSERT INTO users (username, email, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            ['test', 'test@example.com', 'hashed_password', '2026-01-01 00:00:00', '2026-01-01 00:00:00']
        );

        // 注册连接到容器
        $container = Container::getInstance();
        $container->instance(Connection::class, self::$connection);
    }

    public static function tearDownAfterClass(): void
    {
        self::$connection = null;
        Container::resetInstance();
    }

    /**
     * 测试模型查找
     */
    public function testFind(): void
    {
        $user = TestUser::find(1);
        
        $this->assertNotNull($user);
        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('admin', $user->username);
        $this->assertEquals('admin@example.com', $user->email);
    }

    /**
     * 测试查找不存在的记录
     */
    public function testFindNotFound(): void
    {
        $user = TestUser::find(999);
        $this->assertNull($user);
    }

    /**
     * 测试模型查询构建器
     */
    public function testQuery(): void
    {
        $query = TestUser::query();
        $this->assertInstanceOf(\Framework\Database\Query::class, $query);
    }

    /**
     * 测试 WHERE 查询
     */
    public function testWhere(): void
    {
        $users = TestUser::where('username', '=', 'admin')->get();
        
        $this->assertCount(1, $users);
        $this->assertEquals('admin', $users[0]['username']);
    }

    /**
     * 测试分页
     */
    public function testPaginate(): void
    {
        $_GET['page'] = 1;
        $paginator = TestUser::paginate(10);
        
        $this->assertInstanceOf(\Framework\Database\Paginator::class, $paginator);
        $this->assertIsArray($paginator->getItems());
        $this->assertEquals(2, $paginator->getTotal());
        $this->assertCount(2, $paginator->getItems());
    }

    /**
     * 测试创建新记录
     */
    public function testCreate(): void
    {
        $user = new TestUser();
        $user->fill([
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);
        
        $result = $user->save();
        
        $this->assertTrue($result);
        $this->assertNotNull($user->id);
        $this->assertEquals('newuser', $user->username);
        
        // 验证数据库中存在
        $found = TestUser::find($user->id);
        $this->assertNotNull($found);
        $this->assertEquals('newuser', $found->username);
    }

    /**
     * 测试更新记录
     */
    public function testUpdate(): void
    {
        $user = TestUser::find(2);
        $this->assertNotNull($user);
        
        $user->username = 'updated_test';
        $result = $user->save();
        
        $this->assertTrue($result);
        
        // 重新查询验证
        $updated = TestUser::find(2);
        $this->assertEquals('updated_test', $updated->username);
    }

    /**
     * 测试删除记录
     */
    public function testDelete(): void
    {
        // 先创建一条记录
        $user = new TestUser();
        $user->fill([
            'username' => 'to_delete',
            'email' => 'delete@example.com',
            'password' => 'password',
        ]);
        $user->save();
        $id = $user->id;
        
        // 删除
        $result = $user->delete();
        $this->assertTrue($result);
        
        // 验证已删除
        $found = TestUser::find($id);
        $this->assertNull($found);
    }

    /**
     * 测试 toArray 隐藏字段
     */
    public function testToArrayHidesFields(): void
    {
        $user = TestUser::find(1);
        $array = $user->toArray();
        
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayNotHasKey('password', $array); // password 应该被隐藏
    }

    /**
     * 测试批量赋值 fillable 保护
     */
    public function testFillableProtection(): void
    {
        $user = new TestUser();
        $user->fill([
            'username' => 'fillable_test',
            'email' => 'fillable@example.com',
            'password' => 'password',
            'id' => 999, // 不在 fillable 中，应该被忽略
        ]);
        
        $this->assertEquals('fillable_test', $user->username);
        $this->assertNull($user->id); // id 不应该被设置
    }
}

/**
 * 测试用户模型
 */
class TestUser extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['username', 'email', 'password'];
    protected array $hidden = ['password'];
}
