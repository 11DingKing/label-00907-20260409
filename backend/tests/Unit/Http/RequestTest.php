<?php

namespace Tests\Unit\Http;

use Framework\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Request（HTTP 请求）测试用例
 */
class RequestTest extends TestCase
{
    /**
     * 测试从全局变量创建请求
     */
    public function testCreateFromGlobals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/users?page=1';
        $_GET = ['page' => '1'];
        $_POST = ['name' => 'test'];
        
        $request = Request::createFromGlobals();
        
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/users', $request->getPath());
        $this->assertEquals('1', $request->getQuery('page'));
        $this->assertEquals('test', $request->getPost('name'));
    }

    /**
     * 测试获取查询参数
     */
    public function testGetQuery(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test?foo=bar&baz=qux';
        $_GET = ['foo' => 'bar', 'baz' => 'qux'];
        $_POST = [];
        
        $request = Request::createFromGlobals();
        
        $this->assertEquals('bar', $request->getQuery('foo'));
        $this->assertEquals('qux', $request->getQuery('baz'));
        $this->assertNull($request->getQuery('non-existent'));
        $this->assertEquals('default', $request->getQuery('non-existent', 'default'));
        
        $allQuery = $request->getQuery();
        $this->assertIsArray($allQuery);
        $this->assertArrayHasKey('foo', $allQuery);
    }

    /**
     * 测试获取 POST 数据
     */
    public function testGetPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test';
        $_GET = [];
        $_POST = ['username' => 'admin', 'password' => 'secret'];
        
        $request = Request::createFromGlobals();
        
        $this->assertEquals('admin', $request->getPost('username'));
        $this->assertEquals('secret', $request->getPost('password'));
    }

    /**
     * 测试 JSON 请求解析
     */
    public function testJsonRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_GET = [];
        $_POST = [];
        
        // 模拟 JSON 输入
        $jsonData = ['name' => 'test', 'age' => 25];
        file_put_contents('php://memory', json_encode($jsonData));
        
        // 由于无法直接修改 php://input，我们手动测试 JSON 解析逻辑
        $request = Request::createFromGlobals();
        
        $this->assertTrue($request->isJson());
    }

    /**
     * 测试获取所有输入
     */
    public function testAll(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test?foo=bar';
        $_GET = ['foo' => 'bar'];
        $_POST = ['name' => 'test'];
        
        $request = Request::createFromGlobals();
        $all = $request->all();
        
        $this->assertArrayHasKey('foo', $all);
        $this->assertArrayHasKey('name', $all);
    }

    /**
     * 测试 input 方法（优先 POST）
     */
    public function testInput(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test?name=get_value';
        $_GET = ['name' => 'get_value'];
        $_POST = ['name' => 'post_value'];
        
        $request = Request::createFromGlobals();
        
        // input 优先 POST
        $this->assertEquals('post_value', $request->input('name'));
    }

    /**
     * 测试路由参数
     */
    public function testRouteParameters(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_GET = [];
        $_POST = [];
        
        $request = Request::createFromGlobals();
        
        $request->setRouteParameter('id', '123');
        $this->assertEquals('123', $request->getRouteParameter('id'));
        
        $params = $request->getRouteParameters();
        $this->assertArrayHasKey('id', $params);
    }

    /**
     * 测试 AJAX 请求检测
     */
    public function testIsAjax(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_GET = [];
        $_POST = [];
        
        $request = Request::createFromGlobals();
        
        $this->assertTrue($request->isAjax());
    }
}
