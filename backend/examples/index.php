<?php

/**
 * 应用入口文件
 * 传统 PHP-FPM 模式
 */

// 加载 Composer 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 加载环境变量（简化处理，实际应使用 dotenv）
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

// 运行应用
$app->run();
