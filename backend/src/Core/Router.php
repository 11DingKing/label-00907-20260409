<?php

namespace Framework\Core;

use Framework\Http\Request;

/**
 * 路由管理器
 * 负责路由注册、匹配和分发
 * 支持路由缓存（仅缓存非闭包路由）
 */
class Router
{
    /**
     * 路由列表
     * @var array<Route>
     */
    private array $routes = [];

    /**
     * 路由缓存文件路径
     */
    private ?string $cacheFile = null;

    /**
     * 是否启用路由缓存
     */
    private bool $cacheEnabled = false;

    /**
     * 是否已从缓存加载
     */
    private bool $loadedFromCache = false;

    /**
     * 设置路由缓存
     */
    public function setCache(string $cacheFile): void
    {
        $this->cacheFile = $cacheFile;
        $this->cacheEnabled = true;
    }

    /**
     * 禁用路由缓存
     */
    public function disableCache(): void
    {
        $this->cacheEnabled = false;
    }

    /**
     * 是否启用了缓存
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    /**
     * 注册路由
     * 
     * @param string $method HTTP 方法（GET, POST, PUT, DELETE 等）
     * @param string $path 路由路径
     * @param callable|string $handler 处理器（闭包或 "Controller@method"）
     * @return Route 路由对象
     */
    public function match(string $method, string $path, callable|string $handler): Route
    {
        $route = new Route($method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    /**
     * 注册 GET 路由
     */
    public function get(string $path, callable|string $handler): Route
    {
        return $this->match('GET', $path, $handler);
    }

    /**
     * 注册 POST 路由
     */
    public function post(string $path, callable|string $handler): Route
    {
        return $this->match('POST', $path, $handler);
    }

    /**
     * 注册 PUT 路由
     */
    public function put(string $path, callable|string $handler): Route
    {
        return $this->match('PUT', $path, $handler);
    }

    /**
     * 注册 DELETE 路由
     */
    public function delete(string $path, callable|string $handler): Route
    {
        return $this->match('DELETE', $path, $handler);
    }

    /**
     * 路由分组（支持前缀和中间件）
     * 
     * @param string $prefix 路径前缀
     * @param callable $callback 回调函数，在其中定义路由
     * @param array $middlewares 中间件列表
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $originalRoutes = $this->routes;
        $this->routes = [];

        // 执行回调，注册路由
        $callback($this);

        // 为新注册的路由添加前缀和中间件
        $newRoutes = $this->routes;
        $this->routes = $originalRoutes;

        foreach ($newRoutes as $route) {
            // 添加前缀
            $path = $route->getPath();
            if ($path !== '/') {
                $path = rtrim($prefix, '/') . '/' . ltrim($path, '/');
            } else {
                $path = rtrim($prefix, '/');
            }
            
            // 设置新路径
            $route->setPath($path);

            // 添加中间件
            foreach ($middlewares as $middleware) {
                $route->middleware($middleware);
            }

            $this->routes[] = $route;
        }
    }

    /**
     * 分发路由（查找匹配的路由）
     * 
     * @param Request $request 请求对象
     * @return Route|null 匹配的路由，未找到返回 null
     */
    public function dispatch(Request $request): ?Route
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // 遍历路由，查找匹配的
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * 获取所有路由
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * 加载路由缓存
     * 
     * @return bool 是否成功加载
     */
    public function loadCache(): bool
    {
        if (!$this->cacheEnabled || !$this->cacheFile || !file_exists($this->cacheFile)) {
            return false;
        }

        try {
            $cached = include $this->cacheFile;
            
            if (!is_array($cached)) {
                return false;
            }

            // 从缓存数组重建路由对象
            $this->routes = [];
            foreach ($cached as $routeData) {
                if (is_array($routeData)) {
                    $this->routes[] = Route::fromArray($routeData);
                }
            }

            $this->loadedFromCache = true;
            return true;
        } catch (\Throwable $e) {
            // 缓存加载失败，删除无效缓存
            @unlink($this->cacheFile);
            return false;
        }
    }

    /**
     * 保存路由缓存
     * 注意：只缓存非闭包路由，闭包路由会被跳过并记录警告
     * 
     * @return bool 是否成功保存
     */
    public function saveCache(): bool
    {
        if (!$this->cacheEnabled || !$this->cacheFile) {
            return false;
        }

        // 如果已从缓存加载，不需要重新保存
        if ($this->loadedFromCache) {
            return true;
        }

        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // 收集可缓存的路由
        $cacheableRoutes = [];
        $hasClosureRoutes = false;

        foreach ($this->routes as $route) {
            if ($route->isCacheable()) {
                $cacheableRoutes[] = $route->toArray();
            } else {
                $hasClosureRoutes = true;
            }
        }

        // 如果存在闭包路由，记录警告但仍然缓存可缓存的路由
        if ($hasClosureRoutes) {
            error_log('[Router] 警告：部分路由使用了闭包，无法缓存。建议在生产环境使用控制器字符串形式。');
        }

        // 如果没有可缓存的路由，不创建缓存文件
        if (empty($cacheableRoutes)) {
            return false;
        }

        // 生成缓存内容（使用 JSON 序列化，更安全）
        $content = "<?php\n// 路由缓存文件 - 自动生成于 " . date('Y-m-d H:i:s') . "\n";
        $content .= "// 注意：闭包路由不会被缓存\n";
        $content .= "return " . var_export($cacheableRoutes, true) . ";\n";

        return file_put_contents($this->cacheFile, $content, LOCK_EX) !== false;
    }

    /**
     * 清除路由缓存
     */
    public function clearCache(): bool
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }
        return true;
    }

    /**
     * 是否从缓存加载
     */
    public function isLoadedFromCache(): bool
    {
        return $this->loadedFromCache;
    }

    /**
     * 获取可缓存路由数量
     */
    public function getCacheableRouteCount(): int
    {
        $count = 0;
        foreach ($this->routes as $route) {
            if ($route->isCacheable()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取路由统计信息
     */
    public function getStats(): array
    {
        $total = count($this->routes);
        $cacheable = $this->getCacheableRouteCount();
        
        return [
            'total' => $total,
            'cacheable' => $cacheable,
            'closure_routes' => $total - $cacheable,
            'cache_enabled' => $this->cacheEnabled,
            'loaded_from_cache' => $this->loadedFromCache,
        ];
    }
}
