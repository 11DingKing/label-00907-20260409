<?php

namespace Framework\Http;

/**
 * HTTP 响应类
 * 封装响应信息，遵循 PSR-7 简化版
 */
class Response
{
    /**
     * 状态码
     */
    private int $statusCode = 200;

    /**
     * 响应头
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * 响应体
     */
    private mixed $body = '';

    /**
     * 创建 JSON 响应
     */
    public static function json(mixed $data, int $statusCode = 200): self
    {
        $response = new self();
        $response->statusCode = $statusCode;
        $response->setHeader('Content-Type', 'application/json; charset=utf-8');
        $response->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $response;
    }

    /**
     * 创建成功响应
     */
    public static function success(mixed $data = null, string $message = 'success', int $code = 0): self
    {
        return self::json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * 创建错误响应
     */
    public static function error(string $message = 'error', int $code = 1, mixed $data = null, int $httpStatus = 400): self
    {
        return self::json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $httpStatus);
    }

    /**
     * 创建 HTML 响应
     */
    public static function html(string $html, int $statusCode = 200): self
    {
        $response = new self();
        $response->statusCode = $statusCode;
        $response->setHeader('Content-Type', 'text/html; charset=utf-8');
        $response->body = $html;
        return $response;
    }

    /**
     * 设置状态码
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * 设置响应头
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 设置响应体
     */
    public function setBody(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * 获取状态码
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取响应头
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 获取响应体
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * 发送响应（传统模式）
     */
    public function send(): void
    {
        // 设置状态码
        http_response_code($this->statusCode);

        // 设置响应头
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // 输出响应体
        echo $this->body;
    }

    /**
     * 转换为 Swoole 响应格式
     */
    public function toSwooleResponse(\Swoole\Http\Response $swooleResponse): void
    {
        $swooleResponse->status($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            $swooleResponse->header($name, $value);
        }
        
        $swooleResponse->end($this->body);
    }
}
