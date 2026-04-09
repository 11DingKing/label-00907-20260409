<?php

/**
 * 应用配置
 */
return [
    // 应用名称
    'name' => 'Lightweight PHP Framework',

    // 应用环境：development, production, testing
    'env' => $_ENV['APP_ENV'] ?? 'development',

    // 调试模式
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),

    // 路由缓存
    'route_cache' => filter_var($_ENV['ROUTE_CACHE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),

    // 时区
    'timezone' => 'Asia/Shanghai',
];
