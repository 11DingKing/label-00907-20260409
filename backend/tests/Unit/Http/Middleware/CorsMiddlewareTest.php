<?php

namespace Tests\Unit\Http\Middleware;

use Framework\Http\Middleware\CorsMiddleware;
use Framework\Http\Request;
use Framework\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * CORS 中间件测试用例
 */
class CorsMiddlewareTest extends TestCase
{
    /**
     * 测试允许所有来源
     */
    public function testAllowAllOrigins(): void
    {
        $middleware = CorsMiddleware::allowAll();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'http://example.com';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success(['test' => 'data']);
        });

        $headers = $response->getHeaders();
        $this->assertEquals('*', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * 测试预检请求
     */
    public function testPreflightRequest(): void
    {
        $middleware = new CorsMiddleware([
            'allowed_origins' => '*',
            'allowed_methods' => ['GET', 'POST', 'PUT'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
        ]);

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'http://example.com';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $headers = $response->getHeaders();
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertStringContainsString('GET', $headers['Access-Control-Allow-Methods']);
        $this->assertStringContainsString('POST', $headers['Access-Control-Allow-Methods']);
        $this->assertStringContainsString('Content-Type', $headers['Access-Control-Allow-Headers']);
    }

    /**
     * 测试特定来源
     */
    public function testSpecificOrigins(): void
    {
        $middleware = new CorsMiddleware([
            'allowed_origins' => ['http://allowed.com', 'http://another.com'],
        ]);

        // 允许的来源
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'http://allowed.com';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $headers = $response->getHeaders();
        $this->assertEquals('http://allowed.com', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * 测试允许凭证
     */
    public function testAllowCredentials(): void
    {
        $middleware = new CorsMiddleware([
            'allowed_origins' => '*',
            'allow_credentials' => true,
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'http://example.com';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $headers = $response->getHeaders();
        $this->assertEquals('true', $headers['Access-Control-Allow-Credentials']);
        // 当允许凭证时，不能使用 *，应该返回具体来源
        $this->assertEquals('http://example.com', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * 测试暴露响应头
     */
    public function testExposedHeaders(): void
    {
        $middleware = new CorsMiddleware([
            'allowed_origins' => '*',
            'exposed_headers' => ['X-Custom-Header', 'X-Another-Header'],
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['HTTP_ORIGIN'] = 'http://example.com';

        $request = Request::createFromGlobals();

        $response = $middleware->handle($request, function ($req) {
            return Response::success([]);
        });

        $headers = $response->getHeaders();
        $this->assertStringContainsString('X-Custom-Header', $headers['Access-Control-Expose-Headers']);
    }
}
