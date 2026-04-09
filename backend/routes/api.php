<?php

/**
 * API 路由定义
 */

use Framework\Core\Router;

/** @var Router $router */

// API 路由组
$router->group('/api', function ($router) {
    // 用户相关路由
    $router->get('/users', 'App\Controllers\UserController@index');
    $router->get('/users/{id}', 'App\Controllers\UserController@show');
    $router->post('/users', 'App\Controllers\UserController@store');
    $router->put('/users/{id}', 'App\Controllers\UserController@update');
    $router->delete('/users/{id}', 'App\Controllers\UserController@destroy');
});
