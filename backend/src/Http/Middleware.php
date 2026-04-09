<?php

namespace Framework\Http;

/**
 * 中间件接口
 * 所有中间件必须实现此接口
 */
interface Middleware
{
    /**
     * 处理请求
     * 
     * @param Request $request 请求对象
     * @param callable $next 下一个中间件或控制器
     * @return Response 响应对象
     */
    public function handle(Request $request, callable $next): Response;
}
