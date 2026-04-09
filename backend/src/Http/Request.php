<?php

namespace Framework\Http;

/**
 * HTTP 请求类
 * 封装请求信息，遵循 PSR-7 简化版
 */
class Request
{
    /**
     * 请求方法
     */
    private string $method;

    /**
     * 请求路径
     */
    private string $path;

    /**
     * 查询参数
     * @var array<string, mixed>
     */
    private array $query = [];

    /**
     * POST 数据
     * @var array<string, mixed>
     */
    private array $post = [];

    /**
     * 请求头
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * 原始输入
     */
    private string $rawInput = '';

    /**
     * 路由参数
     * @var array<string, mixed>
     */
    private array $routeParams = [];

    /**
     * 从全局变量创建请求对象
     */
    public static function createFromGlobals(): self
    {
        $request = new self();
        
        // 请求方法
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // 请求路径（去除查询字符串）
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $request->path = $path ?: '/';
        
        // 查询参数
        $request->query = $_GET ?? [];
        
        // POST 数据
        $request->post = $_POST ?? [];
        
        // 原始输入（JSON 等）
        $request->rawInput = file_get_contents('php://input');
        
        // 如果是 JSON，解析到 post
        if (!empty($request->rawInput)) {
            $json = json_decode($request->rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->post = array_merge($request->post, $json);
            }
        }
        
        // 请求头
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $request->headers[$header] = $value;
            }
        }
        
        return $request;
    }

    /**
     * 从 Swoole 请求创建
     */
    public static function createFromSwoole(\Swoole\Http\Request $swooleRequest): self
    {
        $request = new self();
        
        $request->method = $swooleRequest->server['request_method'] ?? 'GET';
        $request->path = $swooleRequest->server['path_info'] ?? '/';
        $request->query = $swooleRequest->get ?? [];
        $request->post = $swooleRequest->post ?? [];
        $request->headers = $swooleRequest->header ?? [];
        $request->rawInput = $swooleRequest->rawContent();
        
        // 解析 JSON
        if (!empty($request->rawInput)) {
            $json = json_decode($request->rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->post = array_merge($request->post, $json);
            }
        }
        
        return $request;
    }

    /**
     * 获取请求方法
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 获取请求路径
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 获取查询参数
     * 
     * @param string|null $key 参数名，null 返回所有
     * @param mixed $default 默认值
     */
    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    /**
     * 获取 POST 数据
     * 
     * @param string|null $key 参数名，null 返回所有
     * @param mixed $default 默认值
     */
    public function getPost(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    /**
     * 获取所有输入数据（GET + POST）
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * 获取输入值（优先 POST，其次 GET）
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * 获取请求头
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * 获取所有请求头
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 获取原始输入
     */
    public function getRawInput(): string
    {
        return $this->rawInput;
    }

    /**
     * 是否为 AJAX 请求
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * 是否为 JSON 请求
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type', '');
        return strpos($contentType, 'application/json') !== false;
    }

    /**
     * 设置路由参数
     */
    public function setRouteParameter(string $key, mixed $value): void
    {
        $this->routeParams[$key] = $value;
    }

    /**
     * 获取路由参数
     */
    public function getRouteParameter(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * 获取所有路由参数
     */
    public function getRouteParameters(): array
    {
        return $this->routeParams;
    }
}
