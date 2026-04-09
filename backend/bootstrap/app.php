<?php

/**
 * 应用引导文件
 * 初始化框架核心组件
 */

use Framework\Core\Application;
use Framework\Core\Container;
use Framework\Config\Config;
use Framework\Log\Logger;
use Framework\Exception\Handler;
use Framework\Database\ConnectionPool;
use Dotenv\Dotenv;

// 定义基础路径（如果未定义）
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// 加载 .env 文件
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// 初始化配置
$config = Config::getInstance();
$config->setBasePath(BASE_PATH);
$config->setPath(BASE_PATH . '/config');
$config->load();

// 设置时区
date_default_timezone_set($config->get('app.timezone', 'Asia/Shanghai'));

// 初始化容器
$container = Container::getInstance();

// 注册配置服务
$container->instance(Config::class, $config);

// 注册日志服务
$logger = Logger::getInstance();
$logger->setPath(BASE_PATH . '/storage/logs');
$logger->setLevel($config->get('app.debug') ? 'debug' : 'warning');
$container->instance(Logger::class, $logger);

// 注册数据库连接池
$dbConfig = $config->get('database.connections.mysql', []);
$poolConfig = $config->get('database.pool', []);
$pool = ConnectionPool::getInstance();
$pool->setConfig(
    $dbConfig,
    $poolConfig['max_connections'] ?? 10,
    $poolConfig['wait_timeout'] ?? 3.0
);
$container->instance(ConnectionPool::class, $pool);

// 创建应用实例
$app = new Application(BASE_PATH);

// 注册配置到应用
$app->setConfig($config);

// 设置异常处理器
$exceptionHandler = new Handler($logger, $config->get('app.debug', false));
$app->setExceptionHandler($exceptionHandler);

// 加载路由
$app->loadRoutes(function ($router) {
    require_once BASE_PATH . '/routes/api.php';
    require_once BASE_PATH . '/routes/web.php';
});

// 运行应用
$app->run();
