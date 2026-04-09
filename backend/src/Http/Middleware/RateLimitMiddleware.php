<?php

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\Middleware;
use Framework\Cache\CacheInterface;

/**
 * 限流中间件
 * 保护 API 免受过度请求
 */
class RateLimitMiddleware implements Middleware
{
    /**
     * 缓存实例
     */
    private CacheInterface $cache;

    /**
     * 时间窗口内最大请求数
     */
    private int $maxAttempts;

    /**
     * 时间窗口（秒）
     */
    private int $decaySeconds;

    /**
     * 限流键前缀
     */
    private string $prefix = 'rate_limit:';

    /**
     * 限流键生成器
     * @var callable|null
     */
    private $keyResolver;

    public function __construct(
        CacheInterface $cache,
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        ?callable $keyResolver = null
    ) {
        $this->cache = $cache;
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->keyResolver = $keyResolver;
    }

    /**
     * 处理请求
     */
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveKey($request);

        // 获取当前请求次数
        $attempts = $this->getAttempts($key);

        // 检查是否超过限制
        if ($attempts >= $this->maxAttempts) {
            return $this->buildTooManyAttemptsResponse($key);
        }

        // 增加请求计数
        $this->incrementAttempts($key);

        // 执行请求
        $response = $next($request);

        // 添加限流响应头
        return $this->addRateLimitHeaders($response, $key);
    }

    /**
     * 解析限流键
     */
    private function resolveKey(Request $request): string
    {
        if ($this->keyResolver) {
            return $this->prefix . ($this->keyResolver)($request);
        }

        // 默认使用 IP + 路径
        $ip = $this->getClientIp($request);
        $path = $request->getPath();

        return $this->prefix . md5("{$ip}:{$path}");
    }

    /**
     * 获取客户端 IP
     */
    private function getClientIp(Request $request): string
    {
        // 优先从代理头获取
        $headers = ['X-Forwarded-For', 'X-Real-Ip', 'Client-Ip'];

        foreach ($headers as $header) {
            $ip = $request->getHeader($header);
            if ($ip) {
                // X-Forwarded-For 可能包含多个 IP，取第一个
                $ips = explode(',', $ip);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 获取当前请求次数
     */
    private function getAttempts(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    /**
     * 增加请求计数
     */
    private function incrementAttempts(string $key): void
    {
        $attempts = $this->getAttempts($key);

        if ($attempts === 0) {
            // 首次请求，设置过期时间
            $this->cache->set($key, 1, $this->decaySeconds);
        } else {
            // 增加计数（保持原有过期时间）
            $this->cache->set($key, $attempts + 1, $this->decaySeconds);
        }
    }

    /**
     * 获取剩余请求次数
     */
    private function getRemainingAttempts(string $key): int
    {
        return max(0, $this->maxAttempts - $this->getAttempts($key));
    }

    /**
     * 获取重置时间
     */
    private function getResetTime(string $key): int
    {
        return time() + $this->decaySeconds;
    }

    /**
     * 构建超出限制响应
     */
    private function buildTooManyAttemptsResponse(string $key): Response
    {
        $retryAfter = $this->decaySeconds;

        $response = Response::error('请求过于频繁，请稍后再试', 429, [
            'retry_after' => $retryAfter,
        ], 429);

        $response->setHeader('Retry-After', (string) $retryAfter);
        $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', '0');
        $response->setHeader('X-RateLimit-Reset', (string) $this->getResetTime($key));

        return $response;
    }

    /**
     * 添加限流响应头
     */
    private function addRateLimitHeaders(Response $response, string $key): Response
    {
        $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string) $this->getRemainingAttempts($key));
        $response->setHeader('X-RateLimit-Reset', (string) $this->getResetTime($key));

        return $response;
    }

    /**
     * 清除限流记录
     */
    public function clear(string $key): bool
    {
        return $this->cache->delete($this->prefix . $key);
    }

    /**
     * 创建基于 IP 的限流器
     */
    public static function perIp(CacheInterface $cache, int $maxAttempts = 60, int $decaySeconds = 60): self
    {
        return new self($cache, $maxAttempts, $decaySeconds, function (Request $request) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            return "ip:{$ip}";
        });
    }

    /**
     * 创建基于用户的限流器
     */
    public static function perUser(CacheInterface $cache, int $maxAttempts = 60, int $decaySeconds = 60): self
    {
        return new self($cache, $maxAttempts, $decaySeconds, function (Request $request) {
            $userId = $request->getRouteParameter('user_id', 'anonymous');
            return "user:{$userId}";
        });
    }

    /**
     * 创建基于路由的限流器
     */
    public static function perRoute(CacheInterface $cache, int $maxAttempts = 60, int $decaySeconds = 60): self
    {
        return new self($cache, $maxAttempts, $decaySeconds, function (Request $request) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $path = $request->getPath();
            $method = $request->getMethod();
            return "route:{$ip}:{$method}:{$path}";
        });
    }
}
