<?php

namespace Tests\Unit\Http\Middleware;

use Framework\Http\Middleware\RateLimitMiddleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Cache\FileCache;
use PHPUnit\Framework\TestCase;

/**
 * 限流中间件测试用例
 */
class RateLimitMiddlewareTest extends TestCase
{
    private FileCache $cache;
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/rate_limit_test';
        $this->cache = new FileCache($this->cachePath);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    /**
     * 测试正常请求
     */
    public function testNormalRequest(): void
    {
        $middleware = new RateLimitMiddleware($this->cache, 10, 60);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success(['test' => 'data']);
        });

        $this->assertEquals(200, $response->getStatusCode());

        $headers = $response->getHeaders();
        $this->assertEquals('10', $headers['X-RateLimit-Limit']);
        $this->assertEquals('9', $headers['X-RateLimit-Remaining']);
    }

    /**
     * 测试超出限制
     */
    public function testTooManyRequests(): void
    {
        $middleware = new RateLimitMiddleware($this->cache, 3, 60);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $request = Request::createFromGlobals();

        // 发送 3 次请求
        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($request, function ($req) {
                return Response::success([]);
            });
        }

        // 第 4 次请求应该被限制
        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $this->assertEquals(429, $response->getStatusCode());

        $headers = $response->getHeaders();
        $this->assertEquals('0', $headers['X-RateLimit-Remaining']);
        $this->assertArrayHasKey('Retry-After', $headers);
    }

    /**
     * 测试基于 IP 的限流
     */
    public function testPerIpRateLimit(): void
    {
        $middleware = RateLimitMiddleware::perIp($this->cache, 5, 60);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $headers = $response->getHeaders();
        $this->assertEquals('5', $headers['X-RateLimit-Limit']);
    }

    /**
     * 测试基于路由的限流
     */
    public function testPerRouteRateLimit(): void
    {
        $middleware = RateLimitMiddleware::perRoute($this->cache, 10, 60);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/login';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * 测试限流头信息
     */
    public function testRateLimitHeaders(): void
    {
        $middleware = new RateLimitMiddleware($this->cache, 100, 3600);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/data';
        $_SERVER['REMOTE_ADDR'] = '172.16.0.1';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $headers = $response->getHeaders();

        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
    }
}
