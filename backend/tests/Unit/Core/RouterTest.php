<?php

namespace Tests\Unit\Core;

use Framework\Core\Router;
use Framework\Core\Route;
use Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Router（路由管理器）测试用例
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * 测试注册 GET 路由
     */
    public function testRegisterGetRoute(): void
    {
        $route = $this->router->get('/test', function () {
            return 'test';
        });
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/test', $route->getPath());
    }

    /**
     * 测试注册 POST 路由
     */
    public function testRegisterPostRoute(): void
    {
        $route = $this->router->post('/test', 'Controller@method');
        
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('POST', $route->getMethod());
    }

    /**
     * 测试路由匹配
     */
    public function testRouteMatching(): void
    {
        $this->router->get('/users', 'UserController@index');
        
        $request = $this->createRequest('GET', '/users');
        $route = $this->router->dispatch($request);
        
        $this->assertNotNull($route);
        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/users', $route->getPath());
    }

    /**
     * 测试路由参数提取
     */
    public function testRouteParameters(): void
    {
        $this->router->get('/users/{id}', 'UserController@show');
        
        $request = $this->createRequest('GET', '/users/123');
        $route = $this->router->dispatch($request);
        
        $this->assertNotNull($route);
        $parameters = $route->getParameters();
        $this->assertEquals('123', $parameters['id']);
    }

    /**
     * 测试路由未找到
     */
    public function testRouteNotFound(): void
    {
        $request = $this->createRequest('GET', '/non-existent');
        $route = $this->router->dispatch($request);
        
        $this->assertNull($route);
    }

    /**
     * 测试路由分组
     */
    public function testRouteGroup(): void
    {
        $this->router->group('/api', function ($router) {
            $router->get('/users', 'UserController@index');
            $router->get('/posts', 'PostController@index');
        });
        
        // 测试分组后的路由
        $request1 = $this->createRequest('GET', '/api/users');
        $route1 = $this->router->dispatch($request1);
        $this->assertNotNull($route1);
        
        $request2 = $this->createRequest('GET', '/api/posts');
        $route2 = $this->router->dispatch($request2);
        $this->assertNotNull($route2);
    }

    /**
     * 测试路由中间件
     */
    public function testRouteMiddleware(): void
    {
        $route = $this->router->get('/test', 'Controller@method');
        $route->middleware('AuthMiddleware');
        
        $middlewares = $route->getMiddlewares();
        $this->assertCount(1, $middlewares);
        $this->assertEquals('AuthMiddleware', $middlewares[0]);
    }

    /**
     * 测试路由缓存（控制器字符串形式）
     */
    public function testRouteCache(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routes_cache_test.php';
        @unlink($cacheFile); // 确保干净状态
        
        // 注册路由（使用控制器字符串，可缓存）
        $this->router->get('/test', 'Controller@method');
        $this->router->post('/users', 'UserController@store');
        
        // 保存缓存
        $this->router->setCache($cacheFile);
        $this->assertTrue($this->router->saveCache());
        $this->assertFileExists($cacheFile);
        
        // 创建新路由实例并加载缓存
        $newRouter = new Router();
        $newRouter->setCache($cacheFile);
        $this->assertTrue($newRouter->loadCache());
        $this->assertTrue($newRouter->isLoadedFromCache());
        
        // 验证路由已加载
        $request = $this->createRequest('GET', '/test');
        $route = $newRouter->dispatch($request);
        $this->assertNotNull($route);
        $this->assertEquals('Controller@method', $route->getHandler());
        
        // 清理
        @unlink($cacheFile);
    }

    /**
     * 测试闭包路由不被缓存
     */
    public function testClosureRoutesNotCached(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routes_closure_test.php';
        @unlink($cacheFile);
        
        // 注册混合路由
        $this->router->get('/cacheable', 'Controller@method');
        $this->router->get('/closure', function () {
            return 'closure';
        });
        
        // 验证统计
        $stats = $this->router->getStats();
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['cacheable']);
        $this->assertEquals(1, $stats['closure_routes']);
        
        // 保存缓存
        $this->router->setCache($cacheFile);
        $this->assertTrue($this->router->saveCache());
        
        // 加载缓存到新路由器
        $newRouter = new Router();
        $newRouter->setCache($cacheFile);
        $this->assertTrue($newRouter->loadCache());
        
        // 只有可缓存的路由被加载
        $routes = $newRouter->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertEquals('/cacheable', $routes[0]->getPath());
        
        // 清理
        @unlink($cacheFile);
    }

    /**
     * 测试只有闭包路由时不创建缓存
     */
    public function testNoCacheWhenOnlyClosures(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routes_only_closure.php';
        @unlink($cacheFile);
        
        // 只注册闭包路由
        $this->router->get('/closure1', function () { return 'a'; });
        $this->router->get('/closure2', function () { return 'b'; });
        
        $this->router->setCache($cacheFile);
        $this->assertFalse($this->router->saveCache());
        $this->assertFileDoesNotExist($cacheFile);
    }

    /**
     * 测试路由缓存清除
     */
    public function testClearCache(): void
    {
        $cacheFile = sys_get_temp_dir() . '/routes_clear_test.php';
        
        $this->router->get('/test', 'Controller@method');
        $this->router->setCache($cacheFile);
        $this->router->saveCache();
        
        $this->assertFileExists($cacheFile);
        $this->assertTrue($this->router->clearCache());
        $this->assertFileDoesNotExist($cacheFile);
    }

    /**
     * 测试路由统计信息
     */
    public function testRouteStats(): void
    {
        $this->router->get('/a', 'Controller@a');
        $this->router->post('/b', 'Controller@b');
        $this->router->get('/c', function () { return 'c'; });
        
        $stats = $this->router->getStats();
        
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['cacheable']);
        $this->assertEquals(1, $stats['closure_routes']);
        $this->assertFalse($stats['cache_enabled']);
        $this->assertFalse($stats['loaded_from_cache']);
    }

    /**
     * 测试 Route::toArray 和 Route::fromArray
     */
    public function testRouteSerializationDeserialization(): void
    {
        $route = new Route('GET', '/users/{id}', 'UserController@show');
        $route->name('user.show')->middleware('AuthMiddleware');
        
        // 转换为数组
        $array = $route->toArray();
        $this->assertEquals('GET', $array['method']);
        $this->assertEquals('/users/{id}', $array['path']);
        $this->assertEquals('UserController@show', $array['handler']);
        $this->assertEquals('user.show', $array['name']);
        $this->assertContains('AuthMiddleware', $array['middlewares']);
        
        // 从数组恢复
        $restored = Route::fromArray($array);
        $this->assertEquals($route->getMethod(), $restored->getMethod());
        $this->assertEquals($route->getPath(), $restored->getPath());
        $this->assertEquals($route->getHandler(), $restored->getHandler());
        $this->assertEquals($route->getName(), $restored->getName());
    }

    /**
     * 测试 Route::isCacheable
     */
    public function testRouteIsCacheable(): void
    {
        $cacheableRoute = new Route('GET', '/test', 'Controller@method');
        $this->assertTrue($cacheableRoute->isCacheable());
        $this->assertFalse($cacheableRoute->hasClosure());
        
        $closureRoute = new Route('GET', '/test', function () { return 'test'; });
        $this->assertFalse($closureRoute->isCacheable());
        $this->assertTrue($closureRoute->hasClosure());
    }

    /**
     * 测试闭包中间件使路由不可缓存
     */
    public function testClosureMiddlewareMakesRouteUncacheable(): void
    {
        $route = new Route('GET', '/test', 'Controller@method');
        $this->assertTrue($route->isCacheable());
        
        // 添加闭包中间件
        $route->middleware(function ($request, $next) {
            return $next($request);
        });
        
        $this->assertFalse($route->isCacheable());
        $this->assertTrue($route->hasClosure());
    }

    /**
     * 创建测试请求对象
     */
    private function createRequest(string $method, string $path): Request
    {
        // 模拟请求
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_GET = [];
        $_POST = [];
        
        return Request::createFromGlobals();
    }
}
