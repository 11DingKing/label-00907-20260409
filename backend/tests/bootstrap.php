<?php

/**
 * 测试引导文件
 * 初始化测试环境
 */

// 加载 Composer 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 设置时区
date_default_timezone_set('Asia/Shanghai');
