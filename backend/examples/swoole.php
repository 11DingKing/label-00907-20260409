<?php

/**
 * Swoole 服务器入口文件（示例）
 * 
 * 注意：生产环境请使用 bin/server 启动脚本
 * 
 * 用法：
 *   php bin/server --port=9501
 * 
 * 此文件仅作为示例参考
 */

// 加载 Composer 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 加载环境变量
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? 3306;
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'framework_test';
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'root';
$_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? 'root';
$_ENV['DB_CHARSET'] = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// 创建应用实例
use Framework\Core\Application;
use Framework\Exception\Handler;

$app = new Application(__DIR__);

// 设置异常处理器
$app->setExceptionHandler(new Handler());

// 加载路由
require_once __DIR__ . '/routes.php';

// 启用 Swoole（端口 9501）
// 生产环境建议使用 bin/server 启动
$app->enableSwoole(9501, '0.0.0.0');
