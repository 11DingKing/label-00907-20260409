<?php

namespace Tests\Unit\Auth;

use Framework\Auth\JWT;
use Framework\Auth\JWTException;
use PHPUnit\Framework\TestCase;

/**
 * JWT 测试用例
 */
class JWTTest extends TestCase
{
    private JWT $jwt;
    private string $secret = 'test-secret-key-for-jwt-testing';

    protected function setUp(): void
    {
        $this->jwt = new JWT($this->secret, [
            'ttl' => 3600,
            'issuer' => 'test-app',
        ]);
    }

    /**
     * 测试生成 Token
     */
    public function testEncode(): void
    {
        $token = $this->jwt->encode(['user_id' => 1, 'role' => 'admin']);

        $this->assertNotEmpty($token);
        $this->assertCount(3, explode('.', $token));
    }

    /**
     * 测试解析 Token
     */
    public function testDecode(): void
    {
        $payload = ['user_id' => 123, 'name' => 'test'];
        $token = $this->jwt->encode($payload);

        $decoded = $this->jwt->decode($token);

        $this->assertEquals(123, $decoded['user_id']);
        $this->assertEquals('test', $decoded['name']);
        $this->assertEquals('test-app', $decoded['iss']);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertArrayHasKey('iat', $decoded);
    }

    /**
     * 测试无效 Token
     */
    public function testInvalidToken(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Token 格式无效');

        $this->jwt->decode('invalid-token');
    }

    /**
     * 测试签名验证
     */
    public function testInvalidSignature(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Token 签名无效');

        $token = $this->jwt->encode(['user_id' => 1]);
        // 篡改 Token
        $parts = explode('.', $token);
        $parts[2] = 'invalid-signature';
        $tamperedToken = implode('.', $parts);

        $this->jwt->decode($tamperedToken);
    }

    /**
     * 测试过期 Token
     */
    public function testExpiredToken(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Token 已过期');

        // 创建一个已过期的 Token（TTL = -1）
        $jwt = new JWT($this->secret, ['ttl' => -1]);
        $token = $jwt->encode(['user_id' => 1]);

        $this->jwt->decode($token);
    }

    /**
     * 测试刷新 Token
     */
    public function testRefresh(): void
    {
        $originalToken = $this->jwt->encode(['user_id' => 1, 'role' => 'admin']);
        $newToken = $this->jwt->refresh($originalToken);

        $this->assertNotEquals($originalToken, $newToken);

        $decoded = $this->jwt->decode($newToken);
        $this->assertEquals(1, $decoded['user_id']);
        $this->assertEquals('admin', $decoded['role']);
    }

    /**
     * 测试验证 Token
     */
    public function testValidate(): void
    {
        $validToken = $this->jwt->encode(['user_id' => 1]);
        $invalidToken = 'invalid.token.here';

        $this->assertTrue($this->jwt->validate($validToken));
        $this->assertFalse($this->jwt->validate($invalidToken));
    }

    /**
     * 测试从请求头获取 Token
     */
    public function testFromHeader(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test.signature';

        $this->assertEquals($token, JWT::fromHeader("Bearer {$token}"));
        $this->assertEquals($token, JWT::fromHeader("bearer {$token}"));
        $this->assertNull(JWT::fromHeader(null));
        $this->assertNull(JWT::fromHeader('Basic auth'));
    }

    /**
     * 测试获取剩余有效时间
     */
    public function testGetTimeToExpire(): void
    {
        $token = $this->jwt->encode(['user_id' => 1]);
        $ttl = $this->jwt->getTimeToExpire($token);

        $this->assertGreaterThan(3500, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }

    /**
     * 测试自定义过期时间
     */
    public function testCustomTtl(): void
    {
        $token = $this->jwt->encode(['user_id' => 1], 7200);
        $ttl = $this->jwt->getTimeToExpire($token);

        $this->assertGreaterThan(7100, $ttl);
        $this->assertLessThanOrEqual(7200, $ttl);
    }
}
