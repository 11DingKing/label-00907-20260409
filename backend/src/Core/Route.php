<?php

namespace Framework\Core;

/**
 * 路由对象
 * 封装路由信息，支持序列化缓存
 */
class Route
{
    /**
     * HTTP 方法
     */
    private string $method;

    /**
     * 路由路径（支持占位符，如 /user/{id}）
     */
    private string $path;

    /**
     * 路由处理器（闭包或控制器@方法）
     * @var callable|string
     */
    private mixed $handler;

    /**
     * 路由参数（从路径中提取）
     * @var array<string, string>
     */
    private array $parameters = [];

    /**
     * 中间件列表
     * @var array<callable|string>
     */
    private array $middlewares = [];

    /**
     * 路由名称（可选）
     */
    private ?string $name = null;

    /**
     * 是否包含闭包（不可缓存）
     */
    private bool $hasClosure = false;

    public function __construct(string $method, string $path, mixed $handler)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->hasClosure = $this->isClosure($handler);
    }

    /**
     * 检查是否为闭包
     */
    private function isClosure(mixed $value): bool
    {
        return $value instanceof \Closure;
    }

    /**
     * 设置路由名称
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 添加中间件
     */
    public function middleware(mixed $middleware): self
    {
        $this->middlewares[] = $middleware;
        if ($this->isClosure($middleware)) {
            $this->hasClosure = true;
        }
        return $this;
    }

    /**
     * 检查路由是否匹配请求路径
     * 
     * @param string $method HTTP 方法
     * @param string $path 请求路径
     * @return bool 是否匹配
     */
    public function matches(string $method, string $path): bool
    {
        // 方法不匹配
        if ($this->method !== strtoupper($method)) {
            return false;
        }

        // 将路由路径转换为正则表达式
        $pattern = $this->pathToRegex($this->path);
        
        // 匹配路径
        if (preg_match($pattern, $path, $matches)) {
            // 提取参数
            $this->parameters = [];
            preg_match_all('/\{(\w+)\}/', $this->path, $paramNames);
            
            foreach ($paramNames[1] as $index => $name) {
                if (isset($matches[$index + 1])) {
                    $this->parameters[$name] = $matches[$index + 1];
                }
            }
            
            return true;
        }

        return false;
    }

    /**
     * 将路径模式转换为正则表达式
     * 例如：/user/{id} -> #^/user/(\w+)$#
     */
    private function pathToRegex(string $path): string
    {
        // 先将 {param} 替换为占位符
        $pattern = preg_replace('/\{(\w+)\}/', '___PARAM___', $path);
        
        // 转义特殊字符
        $pattern = preg_quote($pattern, '#');
        
        // 将占位符替换为正则捕获组
        $pattern = str_replace('___PARAM___', '([^/]+)', $pattern);
        
        return '#^' . $pattern . '$#';
    }

    /**
     * 获取 HTTP 方法
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 获取路由路径
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 设置路由路径（用于路由分组）
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 获取处理器
     */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * 获取路由参数
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * 获取中间件列表
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * 获取路由名称
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * 是否包含闭包（不可缓存）
     */
    public function hasClosure(): bool
    {
        return $this->hasClosure;
    }

    /**
     * 是否可缓存
     */
    public function isCacheable(): bool
    {
        return !$this->hasClosure;
    }

    /**
     * 转换为可缓存的数组格式
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'handler' => $this->handler,
            'middlewares' => $this->middlewares,
            'name' => $this->name,
        ];
    }

    /**
     * 从数组创建路由对象
     */
    public static function fromArray(array $data): self
    {
        $route = new self($data['method'], $data['path'], $data['handler']);
        $route->middlewares = $data['middlewares'] ?? [];
        $route->name = $data['name'] ?? null;
        return $route;
    }

    /**
     * 支持 var_export 缓存（兼容旧方式）
     */
    public static function __set_state(array $data): self
    {
        return self::fromArray($data);
    }
}
