<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Controllers\UserController;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Database\Connection;
use Framework\Database\ConnectionPool;
use Framework\Core\Container;

/**
 * UserController 全接口单元测试
 * 覆盖 index / show / store / update / destroy 五个接口
 * 验证：HTTP 状态码、响应结构、友好提示、异常处理
 */
class UserControllerTest extends TestCase
{
    private UserController $controller;
    private Connection&MockObject $mockConnection;

    protected function setUp(): void
    {
        parent::setUp();
        Container::resetInstance();
        ConnectionPool::resetInstance();

        $this->mockConnection = $this->createMock(Connection::class);
        $mockPool = $this->createMock(ConnectionPool::class);
        $mockPool->method('getConnection')->willReturn($this->mockConnection);

        $container = Container::getInstance();
        $container->instance(ConnectionPool::class, $mockPool);

        $this->controller = new UserController();
    }

    protected function tearDown(): void
    {
        Container::resetInstance();
        ConnectionPool::resetInstance();
        parent::tearDown();
    }

    // ==================== 辅助方法 ====================

    private function createRequest(array $postData = [], array $queryData = []): Request
    {
        $request = new Request();
        $ref = new \ReflectionClass($request);

        $postProp = $ref->getProperty('post');
        $postProp->setAccessible(true);
        $postProp->setValue($request, $postData);

        $queryProp = $ref->getProperty('query');
        $queryProp->setAccessible(true);
        $queryProp->setValue($request, $queryData);

        $methodProp = $ref->getProperty('method');
        $methodProp->setAccessible(true);
        $methodProp->setValue($request, empty($postData) ? 'GET' : 'POST');

        return $request;
    }

    private function decodeResponse(Response $response): array
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * 通用响应结构断言：每个响应都必须包含 code, message, data
     */
    private function assertResponseStructure(array $body): void
    {
        $this->assertArrayHasKey('code', $body, '响应缺少 code 字段');
        $this->assertArrayHasKey('message', $body, '响应缺少 message 字段');
        $this->assertArrayHasKey('data', $body, '响应缺少 data 字段');
        // message 不能包含系统级错误信息
        $this->assertStringNotContainsStringIgnoringCase('exception', $body['message']);
        $this->assertStringNotContainsStringIgnoringCase('stack trace', $body['message']);
        $this->assertStringNotContainsStringIgnoringCase('fatal', $body['message']);
    }

    /**
     * 模拟一个已存在的用户（从数据库查出来的）
     */
    private function mockFindUser(array $attrs = []): void
    {
        $default = [
            'id' => 1,
            'username' => 'existuser',
            'email' => 'exist@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
        $this->mockConnection->method('queryOne')
            ->willReturn(array_merge($default, $attrs));
    }

    /**
     * 模拟用户不存在
     */
    private function mockFindUserNotFound(): void
    {
        $this->mockConnection->method('queryOne')->willReturn(false);
    }

    // ==================== index 接口测试 ====================

    public function testIndexReturnsUserListWithPagination(): void
    {
        // mock count 查询和 list 查询
        $this->mockConnection->method('queryOne')
            ->willReturn(['count' => 2]);
        $this->mockConnection->method('query')
            ->willReturn([
                ['id' => 1, 'username' => 'user1', 'email' => 'u1@example.com', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
                ['id' => 2, 'username' => 'user2', 'email' => 'u2@example.com', 'created_at' => '2026-01-02 00:00:00', 'updated_at' => '2026-01-02 00:00:00'],
            ]);

        $request = $this->createRequest([], ['page_size' => '10']);
        $response = $this->controller->index($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('获取成功', $body['message']);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
        $this->assertCount(2, $body['data']['items']);

        // 验证分页结构
        $pagination = $body['data']['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
        $this->assertArrayHasKey('has_previous', $pagination);
        $this->assertArrayHasKey('has_next', $pagination);
    }

    public function testIndexReturnsEmptyListWhenNoUsers(): void
    {
        $this->mockConnection->method('queryOne')->willReturn(['count' => 0]);
        $this->mockConnection->method('query')->willReturn([]);

        $request = $this->createRequest();
        $response = $this->controller->index($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertCount(0, $body['data']['items']);
        $this->assertEquals(0, $body['data']['pagination']['total']);
    }

    public function testIndexClampsPageSizeToValidRange(): void
    {
        $this->mockConnection->method('queryOne')->willReturn(['count' => 0]);
        $this->mockConnection->method('query')->willReturn([]);

        // page_size=0 应被限制为 1
        $request = $this->createRequest([], ['page_size' => '0']);
        $response = $this->controller->index($request);
        $body = $this->decodeResponse($response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $body['data']['pagination']['per_page']);
    }

    public function testIndexClampsPageSizeMax(): void
    {
        $this->mockConnection->method('queryOne')->willReturn(['count' => 0]);
        $this->mockConnection->method('query')->willReturn([]);

        // page_size=999 应被限制为 100
        $request = $this->createRequest([], ['page_size' => '999']);
        $response = $this->controller->index($request);
        $body = $this->decodeResponse($response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(100, $body['data']['pagination']['per_page']);
    }

    public function testIndexReturnsFriendlyErrorOnDbException(): void
    {
        $this->mockConnection->method('queryOne')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $request = $this->createRequest();
        $response = $this->controller->index($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('获取用户列表失败', $body['message']);
        // 不能暴露系统错误细节
        $this->assertStringNotContainsString('Connection refused', $body['message']);
    }

    public function testIndexHidesPasswordField(): void
    {
        $this->mockConnection->method('queryOne')->willReturn(['count' => 1]);
        $this->mockConnection->method('query')->willReturn([
            ['id' => 1, 'username' => 'user1', 'email' => 'u1@example.com', 'password' => 'hashed', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);

        $request = $this->createRequest();
        $response = $this->controller->index($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('password', $body['data']['items'][0]);
    }

    // ==================== show 接口测试 ====================

    public function testShowReturnsUserWhenFound(): void
    {
        $this->mockFindUser();

        $request = $this->createRequest();
        $response = $this->controller->show($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('获取成功', $body['message']);
        $this->assertEquals(1, $body['data']['id']);
        $this->assertEquals('existuser', $body['data']['username']);
        $this->assertArrayNotHasKey('password', $body['data']);
    }

    public function testShowReturns404WhenUserNotFound(): void
    {
        $this->mockFindUserNotFound();

        $request = $this->createRequest();
        $response = $this->controller->show($request, 9999);
        $body = $this->decodeResponse($response);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('用户不存在', $body['message']);
    }

    public function testShowReturnsFriendlyErrorOnDbException(): void
    {
        $this->mockConnection->method('queryOne')
            ->willThrowException(new \RuntimeException('SQLSTATE timeout'));

        $request = $this->createRequest();
        $response = $this->controller->show($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('获取用户信息失败', $body['message']);
        $this->assertStringNotContainsString('SQLSTATE', $body['message']);
    }

    // ==================== store 接口测试 ====================

    public function testStoreSuccessWithValidData(): void
    {
        $this->mockConnection->method('execute')->willReturn(1);
        $this->mockConnection->method('lastInsertId')->willReturn('42');

        $request = $this->createRequest([
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('创建成功', $body['message']);
        $this->assertEquals('newuser', $body['data']['username']);
        $this->assertEquals('new@example.com', $body['data']['email']);
        $this->assertArrayNotHasKey('password', $body['data']);
    }

    public function testStoreFailsWithoutUsername(): void
    {
        $request = $this->createRequest([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('验证失败', $body['message']);
        $this->assertArrayHasKey('username', $body['data']);
    }

    public function testStoreFailsWithoutEmail(): void
    {
        $request = $this->createRequest([
            'username' => 'testuser',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('email', $body['data']);
    }

    public function testStoreFailsWithoutPassword(): void
    {
        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('password', $body['data']);
    }

    public function testStoreFailsWithInvalidEmail(): void
    {
        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('email', $body['data']);
    }

    public function testStoreFailsWithShortUsername(): void
    {
        $request = $this->createRequest([
            'username' => 'a',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('username', $body['data']);
    }

    public function testStoreFailsWithShortPassword(): void
    {
        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => '123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('password', $body['data']);
    }

    public function testStoreFailsWithEmptyData(): void
    {
        $request = $this->createRequest([]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('验证失败', $body['message']);
    }

    public function testStoreFailsWhenDbInsertFails(): void
    {
        $this->mockConnection->method('execute')->willReturn(0);

        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('创建失败', $body['message']);
    }

    public function testStoreHashesPassword(): void
    {
        $capturedValues = [];
        $this->mockConnection->method('execute')
            ->willReturnCallback(function (string $sql, array $values) use (&$capturedValues) {
                $capturedValues = $values;
                return 1;
            });
        $this->mockConnection->method('lastInsertId')->willReturn('1');

        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $this->controller->store($request);

        // attributes 顺序: username, email, password, created_at, updated_at
        $passwordValue = $capturedValues[2] ?? null;
        $this->assertNotNull($passwordValue);
        $this->assertNotEquals('secret123', $passwordValue);
        $this->assertTrue(password_verify('secret123', $passwordValue));
    }

    public function testStoreReturnsFriendlyErrorOnDbException(): void
    {
        $this->mockConnection->method('execute')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('创建用户失败', $body['message']);
        $this->assertStringNotContainsString('Connection refused', $body['message']);
    }

    public function testStoreReturnsDuplicateErrorOnUniqueViolation(): void
    {
        $this->mockConnection->method('execute')
            ->willThrowException(new \RuntimeException('Duplicate entry for key'));

        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('已存在', $body['message']);
    }

    // ==================== update 接口测试 ====================

    public function testUpdateSuccessWithValidData(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')->willReturn(1);

        $request = $this->createRequest(['username' => 'updatedname']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('更新成功', $body['message']);
        $this->assertEquals('updatedname', $body['data']['username']);
        $this->assertArrayNotHasKey('password', $body['data']);
    }

    public function testUpdateReturns404WhenUserNotFound(): void
    {
        $this->mockFindUserNotFound();

        $request = $this->createRequest(['username' => 'newname']);
        $response = $this->controller->update($request, 9999);
        $body = $this->decodeResponse($response);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('用户不存在', $body['message']);
    }

    public function testUpdateFailsWithInvalidEmail(): void
    {
        $this->mockFindUser();

        $request = $this->createRequest(['email' => 'bad-email']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('验证失败', $body['message']);
        $this->assertArrayHasKey('email', $body['data']);
    }

    public function testUpdateFailsWithShortUsername(): void
    {
        $this->mockFindUser();

        $request = $this->createRequest(['username' => 'a']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('username', $body['data']);
    }

    public function testUpdateFailsWithShortPassword(): void
    {
        $this->mockFindUser();

        $request = $this->createRequest(['password' => '12']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('password', $body['data']);
    }

    public function testUpdateOnlyChangesProvidedFields(): void
    {
        $this->mockFindUser(['username' => 'original', 'email' => 'orig@example.com']);
        $this->mockConnection->method('execute')->willReturn(1);

        // 只更新 email
        $request = $this->createRequest(['email' => 'new@example.com']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('original', $body['data']['username']);
        $this->assertEquals('new@example.com', $body['data']['email']);
    }

    public function testUpdateHashesNewPassword(): void
    {
        $this->mockFindUser();
        $capturedValues = [];
        $this->mockConnection->method('execute')
            ->willReturnCallback(function (string $sql, array $values) use (&$capturedValues) {
                $capturedValues = $values;
                return 1;
            });

        $request = $this->createRequest(['password' => 'newpass123']);
        $this->controller->update($request, 1);

        // 找到 password 值并验证已 hash
        $foundHash = false;
        foreach ($capturedValues as $val) {
            if (is_string($val) && password_verify('newpass123', $val)) {
                $foundHash = true;
                break;
            }
        }
        $this->assertTrue($foundHash, '密码应该被 hash 处理');
    }

    public function testUpdateFailsWhenDbUpdateFails(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')->willReturn(0);

        $request = $this->createRequest(['username' => 'newname']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('更新失败', $body['message']);
    }

    public function testUpdateReturnsFriendlyErrorOnDbException(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')
            ->willThrowException(new \RuntimeException('Deadlock found'));

        $request = $this->createRequest(['username' => 'newname']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('更新用户失败', $body['message']);
        $this->assertStringNotContainsString('Deadlock', $body['message']);
    }

    public function testUpdateReturnsDuplicateErrorOnUniqueViolation(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')
            ->willThrowException(new \RuntimeException('Duplicate entry for key'));

        $request = $this->createRequest(['email' => 'taken@example.com']);
        $response = $this->controller->update($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('已存在', $body['message']);
    }

    // ==================== destroy 接口测试 ====================

    public function testDestroySuccessWhenUserExists(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')->willReturn(1);

        $request = $this->createRequest();
        $response = $this->controller->destroy($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('删除成功', $body['message']);
        $this->assertNull($body['data']);
    }

    public function testDestroyReturns404WhenUserNotFound(): void
    {
        $this->mockFindUserNotFound();

        $request = $this->createRequest();
        $response = $this->controller->destroy($request, 9999);
        $body = $this->decodeResponse($response);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertEquals('用户不存在', $body['message']);
    }

    public function testDestroyFailsWhenDbDeleteFails(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')->willReturn(0);

        $request = $this->createRequest();
        $response = $this->controller->destroy($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('删除失败', $body['message']);
    }

    public function testDestroyReturnsFriendlyErrorOnDbException(): void
    {
        $this->mockFindUser();
        $this->mockConnection->method('execute')
            ->willThrowException(new \RuntimeException('Foreign key constraint'));

        $request = $this->createRequest();
        $response = $this->controller->destroy($request, 1);
        $body = $this->decodeResponse($response);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertResponseStructure($body);
        $this->assertStringContainsString('删除用户失败', $body['message']);
        $this->assertStringNotContainsString('Foreign key', $body['message']);
    }

    // ==================== 响应格式一致性测试 ====================

    public function testAllErrorResponsesHaveConsistentStructure(): void
    {
        // 验证失败
        $request = $this->createRequest([]);
        $r1 = $this->decodeResponse($this->controller->store($request));
        $this->assertResponseStructure($r1);

        // 用户不存在
        $this->mockFindUserNotFound();
        $r2 = $this->decodeResponse($this->controller->show($this->createRequest(), 999));
        $this->assertResponseStructure($r2);
    }

    public function testSuccessResponseCodeIsZero(): void
    {
        $this->mockConnection->method('execute')->willReturn(1);
        $this->mockConnection->method('lastInsertId')->willReturn('1');

        $request = $this->createRequest([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
        $response = $this->controller->store($request);
        $body = $this->decodeResponse($response);

        // 成功响应的 body code 应该是 0
        $this->assertEquals(0, $body['code']);
    }

    public function testErrorResponseCodeMatchesHttpStatus(): void
    {
        $this->mockFindUserNotFound();
        $response = $this->controller->show($this->createRequest(), 999);
        $body = $this->decodeResponse($response);

        // 404 错误的 body code 应该和 HTTP 状态码一致
        $this->assertEquals(404, $body['code']);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
