<?php

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\Middleware;

/**
 * CORS 跨域中间件
 * 处理跨域资源共享
 */
class CorsMiddleware implements Middleware
{
    /**
     * 允许的来源
     * @var array|string
     */
    private array|string $allowedOrigins;

    /**
     * 允许的方法
     */
    private array $allowedMethods;

    /**
     * 允许的请求头
     */
    private array $allowedHeaders;

    /**
     * 暴露的响应头
     */
    private array $exposedHeaders;

    /**
     * 是否允许携带凭证
     */
    private bool $allowCredentials;

    /**
     * 预检请求缓存时间（秒）
     */
    private int $maxAge;

    public function __construct(array $options = [])
    {
        $this->allowedOrigins = $options['allowed_origins'] ?? '*';
        $this->allowedMethods = $options['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $options['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->exposedHeaders = $options['exposed_headers'] ?? [];
        $this->allowCredentials = $options['allow_credentials'] ?? false;
        $this->maxAge = $options['max_age'] ?? 86400;
    }

    /**
     * 处理请求
     */
    public function handle(Request $request, callable $next): Response
    {
        // 获取请求来源
        $origin = $request->getHeader('Origin');

        // 预检请求（OPTIONS）
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        // 正常请求
        $response = $next($request);

        // 添加 CORS 头
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * 处理预检请求
     */
    private function handlePreflight(Request $request, ?string $origin): Response
    {
        $response = Response::json(null, 204);
        
        // 添加 CORS 头
        $response = $this->addCorsHeaders($response, $origin);

        // 预检请求特有的头
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        $response->setHeader('Access-Control-Max-Age', (string) $this->maxAge);

        return $response;
    }

    /**
     * 添加 CORS 响应头
     */
    private function addCorsHeaders(Response $response, ?string $origin): Response
    {
        // 检查来源是否允许
        $allowedOrigin = $this->getAllowedOrigin($origin);

        if ($allowedOrigin) {
            $response->setHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }

        // 允许携带凭证
        if ($this->allowCredentials) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // 暴露的响应头
        if (!empty($this->exposedHeaders)) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        }

        return $response;
    }

    /**
     * 获取允许的来源
     */
    private function getAllowedOrigin(?string $origin): ?string
    {
        if (!$origin) {
            return null;
        }

        // 允许所有来源
        if ($this->allowedOrigins === '*') {
            return $this->allowCredentials ? $origin : '*';
        }

        // 检查是否在允许列表中
        $origins = is_array($this->allowedOrigins) ? $this->allowedOrigins : [$this->allowedOrigins];

        foreach ($origins as $allowed) {
            // 支持通配符匹配
            if ($allowed === '*' || $allowed === $origin) {
                return $origin;
            }

            // 支持正则匹配
            if (str_starts_with($allowed, '/') && preg_match($allowed, $origin)) {
                return $origin;
            }
        }

        return null;
    }

    /**
     * 创建默认配置的中间件
     */
    public static function allowAll(): self
    {
        return new self([
            'allowed_origins' => '*',
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['*'],
            'allow_credentials' => false,
        ]);
    }
}
