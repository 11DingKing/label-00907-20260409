<?php

/**
 * 应用入口文件
 * 传统 PHP-FPM 模式
 */

// 定义根目录
define('BASE_PATH', dirname(__DIR__));

// 加载 Composer 自动加载
require_once BASE_PATH . '/vendor/autoload.php';

// 加载引导文件
require_once BASE_PATH . '/bootstrap/app.php';
