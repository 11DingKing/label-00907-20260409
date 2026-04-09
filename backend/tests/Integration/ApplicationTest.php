<?php

namespace Tests\Integration;

use Framework\Core\Application;
use Framework\Core\Router;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Exception\Handler;
use PHPUnit\Framework\TestCase;

/**
 * Application（应用核心）集成测试
 */
class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(__DIR__);
        $this->app->setExceptionHandler(new Handler());
    }

    /**
     * 测试应用单例
     */
    public function testApplicationSingleton(): void
    {
        $app1 = Application::getInstance();
        $app2 = Application::getInstance();
        
        $this->assertSame($app1, $app2);
    }

    /**
     * 测试路由注册和处理
     */
    public function testRouteHandling(): void
    {
        $router = $this->app->getRouter();
        $router->get('/test', function (Request $request) {
            return Response::success(['message' => 'test']);
        });

        // 创建请求
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_GET = [];
        $_POST = [];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * 测试路由参数传递
     */
    public function testRouteParameters(): void
    {
        $router = $this->app->getRouter();
        $router->get('/users/{id}', function (Request $request, string $id) {
            return Response::success(['id' => $id]);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/123';
        $_GET = [];
        $_POST = [];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('123', $body['data']['id']);
    }

    /**
     * 测试 404 处理
     */
    public function testNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/non-existent';
        $_GET = [];
        $_POST = [];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertStringContainsString('未找到', $body['message']);
    }

    /**
     * 测试中间件
     */
    public function testMiddleware(): void
    {
        $router = $this->app->getRouter();
        
        // 添加全局中间件
        $this->app->middleware(function (Request $request, $next) {
            $response = $next($request);
            $response->setHeader('X-Middleware', 'executed');
            return $response;
        });

        $router->get('/test', function () {
            return Response::success([]);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_GET = [];
        $_POST = [];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $headers = $response->getHeaders();
        $this->assertEquals('executed', $headers['X-Middleware']);
    }

    /**
     * 测试异常处理
     */
    public function testExceptionHandling(): void
    {
        $router = $this->app->getRouter();
        $router->get('/error', function () {
            throw new \RuntimeException('测试异常');
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/error';
        $_GET = [];
        $_POST = [];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('message', $body);
    }

    /**
     * 测试路由分组
     */
    public function testRouteGroup(): void
    {
        $router = $this->app->getRouter();
        $router->group('/api', function ($router) {
            $router->get('/users', function () {
                return Response::success(['users' => []]);
            });
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/users';
        $_GET = [];
        $_POST = [];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * 测试 POST 请求
     */
    public function testPostRequest(): void
    {
        $router = $this->app->getRouter();
        $router->post('/users', function (Request $request) {
            $data = $request->getPost();
            return Response::success($data, '创建成功');
        });

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';
        $_GET = [];
        $_POST = ['name' => 'test', 'email' => 'test@example.com'];

        $request = Request::createFromGlobals();
        $response = $this->app->handle($request);

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('test', $body['data']['name']);
    }
}
