<?php

/**
 * Web 路由定义
 */

use Framework\Core\Router;
use Framework\Http\Response;

/** @var Router $router */

// 健康检查
$router->get('/health', function () {
    return Response::success([
        'status' => 'healthy',
        'timestamp' => date('c'),
    ], 'OK');
});

// 调试：检查环境变量（仅开发环境）
$router->get('/debug/env', function () {
    // 生产环境禁用
    $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
    if ($appEnv === 'production') {
        return Response::error('Debug endpoint disabled in production', 403, null, 403);
    }

    $testConfig = \Framework\Database\Model::getDatabaseConfigStatic();
    
    return Response::success([
        'app_env' => $appEnv,
        'db_host' => $testConfig['host'] ?? 'not set',
        'db_database' => $testConfig['database'] ?? 'not set',
    ], 'Debug Info');
});

// 首页
$router->get('/', function () {
    return Response::success([
        'name' => 'Lightweight PHP Framework',
        'version' => '1.0.0',
        'documentation' => '/api',
    ], '欢迎使用轻量级 PHP 框架');
});
