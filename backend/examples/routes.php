<?php

/**
 * 路由定义文件
 * 定义应用的所有路由
 */

use Framework\Core\Application;
use App\Controllers\UserController;

$app = Application::getInstance();
$router = $app->getRouter();

// API 路由组
$router->group('/api', function ($router) {
    // 用户相关路由
    $router->get('/users', 'App\Controllers\UserController@index');
    $router->get('/users/{id}', 'App\Controllers\UserController@show');
    $router->post('/users', 'App\Controllers\UserController@store');
    $router->put('/users/{id}', 'App\Controllers\UserController@update');
    $router->delete('/users/{id}', 'App\Controllers\UserController@destroy');
});

// 健康检查
$router->get('/health', function () {
    return \Framework\Http\Response::success(['status' => 'ok'], '服务正常');
});
