<?php

namespace Tests\Unit\Http;

use Framework\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Response（HTTP 响应）测试用例
 */
class ResponseTest extends TestCase
{
    /**
     * 测试创建 JSON 响应
     */
    public function testJsonResponse(): void
    {
        $data = ['name' => 'test', 'age' => 25];
        $response = Response::json($data, 200);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaders()['Content-Type']);
        $this->assertJson($response->getBody());
        
        $decoded = json_decode($response->getBody(), true);
        $this->assertEquals($data, $decoded);
    }

    /**
     * 测试创建成功响应
     */
    public function testSuccessResponse(): void
    {
        $data = ['id' => 1];
        $response = Response::success($data, '操作成功');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode($response->getBody(), true);
        $this->assertEquals(0, $body['code']);
        $this->assertEquals('操作成功', $body['message']);
        $this->assertEquals($data, $body['data']);
    }

    /**
     * 测试创建错误响应
     */
    public function testErrorResponse(): void
    {
        $response = Response::error('操作失败', 1);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode($response->getBody(), true);
        $this->assertEquals(1, $body['code']);
        $this->assertEquals('操作失败', $body['message']);
    }

    /**
     * 测试创建 HTML 响应
     */
    public function testHtmlResponse(): void
    {
        $html = '<html><body>Test</body></html>';
        $response = Response::html($html);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
        $this->assertEquals($html, $response->getBody());
    }

    /**
     * 测试设置状态码
     */
    public function testSetStatusCode(): void
    {
        $response = Response::json([]);
        $response->setStatusCode(404);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * 测试设置响应头
     */
    public function testSetHeader(): void
    {
        $response = Response::json([]);
        $response->setHeader('X-Custom-Header', 'custom-value');
        
        $headers = $response->getHeaders();
        $this->assertEquals('custom-value', $headers['X-Custom-Header']);
    }

    /**
     * 测试设置响应体
     */
    public function testSetBody(): void
    {
        $response = Response::json([]);
        $response->setBody('custom body');
        
        $this->assertEquals('custom body', $response->getBody());
    }
}
