<?php

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\Middleware;
use Framework\Auth\JWT;
use Framework\Auth\JWTException;

/**
 * JWT 认证中间件
 * 验证请求中的 JWT Token
 */
class JWTMiddleware implements Middleware
{
    /**
     * JWT 实例
     */
    private JWT $jwt;

    /**
     * 排除的路径（不需要认证）
     */
    private array $except;

    public function __construct(JWT $jwt, array $except = [])
    {
        $this->jwt = $jwt;
        $this->except = $except;
    }

    /**
     * 处理请求
     */
    public function handle(Request $request, callable $next): Response
    {
        // 检查是否在排除列表中
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // 获取 Token
        $token = $this->getToken($request);

        if (!$token) {
            return Response::error('未提供认证令牌', 401, null, 401);
        }

        try {
            // 验证 Token
            $payload = $this->jwt->decode($token);

            // 将用户信息注入请求
            $request->setRouteParameter('jwt_payload', $payload);
            $request->setRouteParameter('user_id', $payload['sub'] ?? $payload['user_id'] ?? null);

            return $next($request);
        } catch (JWTException $e) {
            return Response::error($e->getMessage(), 401, null, 401);
        }
    }

    /**
     * 从请求中获取 Token
     */
    private function getToken(Request $request): ?string
    {
        // 优先从 Authorization 头获取
        $header = $request->getHeader('Authorization');
        $token = JWT::fromHeader($header);

        if ($token) {
            return $token;
        }

        // 从查询参数获取
        $token = $request->getQuery('token');
        if ($token) {
            return $token;
        }

        // 从 Cookie 获取
        return $_COOKIE['token'] ?? null;
    }

    /**
     * 检查是否应该跳过认证
     */
    private function shouldSkip(Request $request): bool
    {
        $path = $request->getPath();

        foreach ($this->except as $pattern) {
            // 精确匹配
            if ($pattern === $path) {
                return true;
            }

            // 通配符匹配
            if (str_contains($pattern, '*')) {
                $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);
                if (preg_match("/^{$regex}$/", $path)) {
                    return true;
                }
            }
        }

        return false;
    }
}
