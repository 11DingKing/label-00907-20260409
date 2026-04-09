<?php

/**
 * 数据库配置
 * 
 * 环境变量优先级：$_ENV > $_SERVER > getenv()
 * Docker 环境变量通常在 $_SERVER 和 getenv() 中
 */

// 辅助函数：获取环境变量
$getEnv = function(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
};

return [
    // 默认连接
    'default' => 'mysql',

    // 连接配置
    'connections' => [
        'mysql' => [
            'host' => $getEnv('DB_HOST', 'localhost'),
            'port' => (int) $getEnv('DB_PORT', 3306),
            'database' => $getEnv('DB_DATABASE', ''),
            'username' => $getEnv('DB_USERNAME', 'root'),
            'password' => $getEnv('DB_PASSWORD', ''),
            'charset' => $getEnv('DB_CHARSET', 'utf8mb4'),
        ],
    ],

    // 连接池配置
    'pool' => [
        'max_connections' => (int) $getEnv('DB_POOL_MAX', 10),
        'wait_timeout' => (float) $getEnv('DB_POOL_TIMEOUT', 3.0),
    ],
];
