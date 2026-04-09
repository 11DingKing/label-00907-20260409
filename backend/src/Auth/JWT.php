<?php

namespace Framework\Auth;

/**
 * JWT (JSON Web Token) 管理类
 * 用于 API 无状态认证
 */
class JWT
{
    /**
     * 密钥
     */
    private string $secret;

    /**
     * 算法
     */
    private string $algorithm = 'HS256';

    /**
     * 默认过期时间（秒）
     */
    private int $ttl = 3600;

    /**
     * 签发者
     */
    private string $issuer = '';

    /**
     * 支持的算法
     */
    private const ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    public function __construct(string $secret, array $options = [])
    {
        $this->secret = $secret;
        $this->algorithm = $options['algorithm'] ?? 'HS256';
        $this->ttl = $options['ttl'] ?? 3600;
        $this->issuer = $options['issuer'] ?? '';
    }

    /**
     * 生成 Token
     * 
     * @param array $payload 载荷数据（如用户ID等）
     * @param int|null $ttl 过期时间（秒），null 使用默认值
     * @return string JWT Token
     */
    public function encode(array $payload, ?int $ttl = null): string
    {
        $ttl = $ttl ?? $this->ttl;
        $now = time();

        // 标准声明
        $claims = [
            'iss' => $this->issuer,      // 签发者
            'iat' => $now,                // 签发时间
            'exp' => $now + $ttl,         // 过期时间
            'nbf' => $now,                // 生效时间
            'jti' => $this->generateJti(), // 唯一标识
        ];

        // 合并自定义载荷
        $payload = array_merge($claims, $payload);

        // 构建 Header
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm,
        ];

        // 编码
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // 签名
        $signature = $this->sign("{$headerEncoded}.{$payloadEncoded}");
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * 解析并验证 Token
     * 
     * @param string $token JWT Token
     * @return array 载荷数据
     * @throws JWTException 验证失败时抛出异常
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new JWTException('Token 格式无效');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // 验证签名
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = $this->sign("{$headerEncoded}.{$payloadEncoded}");

        if (!hash_equals($expectedSignature, $signature)) {
            throw new JWTException('Token 签名无效');
        }

        // 解析载荷
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            throw new JWTException('Token 载荷无效');
        }

        // 验证过期时间
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new JWTException('Token 已过期');
        }

        // 验证生效时间
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new JWTException('Token 尚未生效');
        }

        return $payload;
    }

    /**
     * 刷新 Token
     */
    public function refresh(string $token, ?int $ttl = null): string
    {
        $payload = $this->decode($token);

        // 移除标准声明，保留自定义数据
        unset($payload['iss'], $payload['iat'], $payload['exp'], $payload['nbf'], $payload['jti']);

        return $this->encode($payload, $ttl);
    }

    /**
     * 验证 Token 是否有效（不抛异常）
     */
    public function validate(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    /**
     * 从请求头获取 Token
     */
    public static function fromHeader(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        // Bearer Token
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 签名
     */
    private function sign(string $data): string
    {
        $algo = self::ALGORITHMS[$this->algorithm] ?? 'sha256';
        return hash_hmac($algo, $data, $this->secret, true);
    }

    /**
     * Base64 URL 安全编码
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL 安全解码
     */
    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * 生成唯一标识
     */
    private function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 获取 Token 剩余有效时间（秒）
     */
    public function getTimeToExpire(string $token): int
    {
        try {
            $payload = $this->decode($token);
            return max(0, ($payload['exp'] ?? 0) - time());
        } catch (JWTException $e) {
            return 0;
        }
    }
}

/**
 * JWT 异常类
 */
class JWTException extends \Exception
{
}
