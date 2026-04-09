<?php

namespace Framework\Core;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\Middleware;
use Framework\Config\Config;
use Framework\Exception\Handler as ExceptionHandler;

/**
 * 应用程序核心类
 * 负责请求处理、路由分发、中间件执行
 */
class Application
{
    /**
     * 应用实例（单例）
     */
    private static ?Application $instance = null;

    /**
     * DI 容器
     */
    private Container $container;

    /**
     * 路由管理器
     */
    private Router $router;

    /**
     * 全局中间件
     * @var array<callable|string>
     */
    private array $middlewares = [];

    /**
     * 异常处理器
     */
    private ?ExceptionHandler $exceptionHandler = null;

    /**
     * 基础路径
     */
    private string $basePath;

    /**
     * 是否启用 Swoole
     */
    private bool $swooleEnabled = false;

    /**
     * 配置实例
     */
    private ?Config $config = null;

    /**
     * 是否启用路由缓存
     */
    private bool $routeCacheEnabled = false;

    /**
     * 获取应用单例
     */
    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 2);
        $this->container = Container::getInstance();
        $this->router = new Router();
        
        // 注册核心服务到容器
        $this->container->singleton(Router::class, function () {
            return $this->router;
        });
        
        $this->container->singleton(Application::class, function () {
            return $this;
        });

        // 自动检测环境并配置路由缓存
        $this->autoConfigureRouteCache();
    }

    /**
     * 自动配置路由缓存
     * 根据环境变量或配置自动启用/禁用路由缓存
     */
    private function autoConfigureRouteCache(): void
    {
        // 从环境变量读取配置
        $enableCache = $_ENV['ROUTE_CACHE_ENABLED'] ?? $_SERVER['ROUTE_CACHE_ENABLED'] ?? null;
        $cacheDir = $_ENV['ROUTE_CACHE_DIR'] ?? $_SERVER['ROUTE_CACHE_DIR'] ?? null;

        // 如果环境变量明确设置，使用环境变量
        if ($enableCache !== null) {
            $this->routeCacheEnabled = filter_var($enableCache, FILTER_VALIDATE_BOOLEAN);
        } else {
            // 默认：生产环境启用，开发环境禁用
            $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'development';
            $this->routeCacheEnabled = ($appEnv === 'production');
        }

        // 配置缓存目录
        if ($this->routeCacheEnabled) {
            $cacheFile = $cacheDir 
                ? rtrim($cacheDir, '/') . '/routes.cache.php'
                : $this->basePath . '/storage/cache/routes.cache.php';
            
            $this->router->setCache($cacheFile);
        }
    }

    /**
     * 设置配置实例
     */
    public function setConfig(Config $config): self
    {
        $this->config = $config;
        
        // 从配置中读取路由缓存设置
        $routeCacheEnabled = $config->get('app.route_cache', null);
        if ($routeCacheEnabled !== null) {
            $this->setRouteCacheEnabled($routeCacheEnabled);
        }

        return $this;
    }

    /**
     * 获取配置实例
     */
    public function getConfig(): ?Config
    {
        return $this->config;
    }

    /**
     * 手动设置路由缓存开关
     */
    public function setRouteCacheEnabled(bool $enabled, ?string $cacheFile = null): self
    {
        $this->routeCacheEnabled = $enabled;

        if ($enabled) {
            $cacheFile = $cacheFile ?? $this->basePath . '/storage/cache/routes.cache.php';
            $this->router->setCache($cacheFile);
        }

        return $this;
    }

    /**
     * 是否启用了路由缓存
     */
    public function isRouteCacheEnabled(): bool
    {
        return $this->routeCacheEnabled;
    }

    /**
     * 加载路由（支持缓存）
     * 
     * @param callable $routeDefinition 路由定义回调
     */
    public function loadRoutes(callable $routeDefinition): self
    {
        // 如果启用缓存且缓存存在，从缓存加载
        if ($this->routeCacheEnabled && $this->router->loadCache()) {
            return $this;
        }

        // 执行路由定义
        $routeDefinition($this->router);

        // 如果启用缓存，保存到缓存
        if ($this->routeCacheEnabled) {
            $this->router->saveCache();
        }

        return $this;
    }

    /**
     * 清除路由缓存
     */
    public function clearRouteCache(): bool
    {
        $cacheFile = $this->basePath . '/storage/cache/routes.cache.php';
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return true;
    }

    /**
     * 设置基础路径
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = $path;
        return $this;
    }

    /**
     * 获取基础路径
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * 获取路由管理器
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * 获取容器
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * 添加全局中间件
     */
    public function middleware(callable|string $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * 设置异常处理器
     */
    public function setExceptionHandler(ExceptionHandler $handler): self
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    /**
     * 处理请求
     * 
     * @param Request|null $request 请求对象，null 则从全局变量创建
     * @return Response 响应对象
     */
    public function handle(?Request $request = null): Response
    {
        try {
            // 创建请求对象
            if ($request === null) {
                $request = Request::createFromGlobals();
            }

            // 执行中间件链
            $response = $this->runMiddlewareStack($request, function ($req) {
                return $this->dispatchRoute($req);
            });

            return $response;
        } catch (\Throwable $e) {
            return $this->handleException($e, $request ?? null);
        }
    }

    /**
     * 执行中间件栈
     */
    private function runMiddlewareStack(Request $request, callable $core): Response
    {
        $middlewares = array_reverse($this->middlewares);
        $next = $core;

        foreach ($middlewares as $middleware) {
            $next = function ($req) use ($middleware, $next) {
                $instance = $this->resolveMiddleware($middleware);
                return $instance->handle($req, $next);
            };
        }

        return $next($request);
    }

    /**
     * 解析中间件
     */
    private function resolveMiddleware(callable|string $middleware): Middleware
    {
        // 如果是闭包，包装成中间件
        if (is_callable($middleware) && !is_string($middleware)) {
            return new class($middleware) implements Middleware {
                private $callback;
                public function __construct($callback) {
                    $this->callback = $callback;
                }
                public function handle(Request $request, callable $next): Response {
                    return ($this->callback)($request, $next);
                }
            };
        }

        // 字符串类名，从容器解析
        if (is_string($middleware)) {
            return $this->container->make($middleware);
        }

        throw new \RuntimeException('无效的中间件');
    }

    /**
     * 路由分发
     */
    private function dispatchRoute(Request $request): Response
    {
        $route = $this->router->dispatch($request);

        if ($route === null) {
            return Response::error('路由未找到', 404, null, 404);
        }

        // 执行路由中间件
        $middlewares = array_reverse($route->getMiddlewares());
        $next = function ($req) use ($route) {
            return $this->executeHandler($req, $route);
        };

        foreach ($middlewares as $middleware) {
            $next = function ($req) use ($middleware, $next) {
                $instance = $this->resolveMiddleware($middleware);
                return $instance->handle($req, $next);
            };
        }

        return $next($request);
    }

    /**
     * 执行路由处理器
     */
    private function executeHandler(Request $request, Route $route): Response
    {
        $handler = $route->getHandler();
        $parameters = $route->getParameters();

        // 将路由参数注入到请求对象（简化处理）
        foreach ($parameters as $key => $value) {
            $request->setRouteParameter($key, $value);
        }

        // 如果是闭包，直接执行
        if (is_callable($handler) && !is_string($handler)) {
            return $handler($request, ...array_values($parameters));
        }

        // 如果是字符串（Controller@method），解析并调用
        if (is_string($handler)) {
            return $this->callController($handler, $request, $parameters);
        }

        throw new \RuntimeException('无效的路由处理器');
    }

    /**
     * 调用控制器方法
     */
    private function callController(string $handler, Request $request, array $parameters): Response
    {
        // 解析 Controller@method
        if (strpos($handler, '@') === false) {
            throw new \RuntimeException('控制器格式错误，应为 Controller@method');
        }

        [$controllerClass, $method] = explode('@', $handler, 2);

        // 从容器解析控制器（支持依赖注入）
        $controller = $this->container->make($controllerClass);

        // 检查方法是否存在
        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("控制器方法不存在: {$controllerClass}::{$method}");
        }

        // 调用方法
        return $controller->$method($request, ...array_values($parameters));
    }

    /**
     * 处理异常
     */
    private function handleException(\Throwable $e, ?Request $request): Response
    {
        if ($this->exceptionHandler) {
            return $this->exceptionHandler->handle($e, $request);
        }

        // 默认异常处理
        return Response::error(
            '服务器内部错误: ' . $e->getMessage(),
            500,
            null,
            500
        );
    }

    /**
     * 运行应用（传统模式）
     */
    public function run(): void
    {
        $response = $this->handle();
        $response->send();
    }

    /**
     * 启用 Swoole 支持
     * 
     * @param int $port 监听端口
     * @param string $host 监听地址
     * @param array $options Swoole 服务器配置选项
     */
    public function enableSwoole(int $port = 9501, string $host = '0.0.0.0', array $options = []): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole 扩展未安装');
        }

        $this->swooleEnabled = true;

        $http = new \Swoole\Http\Server($host, $port);

        // 默认配置
        $defaultOptions = [
            'worker_num' => swoole_cpu_num(),
            'max_request' => 10000,
            'enable_coroutine' => true,
            'log_level' => SWOOLE_LOG_WARNING,
        ];

        // 合并用户配置
        $serverOptions = array_merge($defaultOptions, $options);
        $http->set($serverOptions);

        // 启动事件
        $http->on('start', function (\Swoole\Http\Server $server) use ($host, $port, $serverOptions) {
            $workerNum = $serverOptions['worker_num'] ?? swoole_cpu_num();
            $daemon = $serverOptions['daemonize'] ?? false;
            
            if (!$daemon) {
                echo "\033[32m[INFO]\033[0m Swoole HTTP Server 已启动\n";
                echo "\033[32m[INFO]\033[0m 监听地址: http://{$host}:{$port}\n";
                echo "\033[32m[INFO]\033[0m Master PID: {$server->master_pid}\n";
                echo "\033[32m[INFO]\033[0m Worker 进程数: {$workerNum}\n";
            }
        });

        // Worker 启动事件
        $http->on('workerStart', function (\Swoole\Http\Server $server, int $workerId) {
            // 确保环境变量在 Worker 进程中可用
            // Swoole fork 后环境变量可能丢失，需要重新设置
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'DB_') === 0 || strpos($key, 'APP_') === 0 || strpos($key, 'SWOOLE_') === 0) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        });

        // 请求处理
        $http->on('request', function (\Swoole\Http\Request $swooleRequest, \Swoole\Http\Response $swooleResponse) {
            try {
                $request = Request::createFromSwoole($swooleRequest);
                $response = $this->handle($request);
                $response->toSwooleResponse($swooleResponse);
            } catch (\Throwable $e) {
                $errorResponse = $this->handleException($e, null);
                $errorResponse->toSwooleResponse($swooleResponse);
            }
        });

        $http->start();
    }

    /**
     * 是否在 Swoole 模式下运行
     */
    public function isSwooleEnabled(): bool
    {
        return $this->swooleEnabled;
    }
}
