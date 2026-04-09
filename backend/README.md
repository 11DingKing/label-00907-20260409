# 轻量级高性能 PHP 框架

原生适配高并发的轻量级 PHP 框架，支持 Swoole 协程模式。

## 快速开始

### 1. 安装依赖

```bash
composer install
```

### 2. 配置环境

复制环境配置文件并修改：

```bash
cp .env.example .env
```

编辑 `.env` 文件配置数据库等信息：

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. 启动服务

#### Swoole 模式（推荐）

```bash
# 默认启动（监听 0.0.0.0:9501）
php bin/server

# 指定端口
php bin/server --port=8080

# 后台运行
php bin/server --daemon

# 查看帮助
php bin/server --help
```

#### 传统 PHP 内置服务器（开发）

```bash
php -S localhost:8000 -t public
```

## 项目结构

```
.
├── app/                    # 应用代码
│   ├── Controllers/        # 控制器
│   └── Models/             # 模型
├── bin/                    # 可执行脚本
│   └── server              # Swoole 启动脚本
├── bootstrap/              # 引导文件
│   └── app.php             # 应用初始化
├── config/                 # 配置文件
│   ├── app.php             # 应用配置
│   └── database.php        # 数据库配置
├── public/                 # Web 入口
│   └── index.php           # 入口文件
├── routes/                 # 路由定义
│   ├── api.php             # API 路由
│   └── web.php             # Web 路由
├── src/                    # 框架核心
│   ├── Core/               # 核心组件
│   ├── Database/           # 数据库层
│   ├── Http/               # HTTP 处理
│   └── ...
├── storage/                # 存储目录
│   ├── cache/              # 缓存
│   └── logs/               # 日志
├── tests/                  # 测试用例
├── .env                    # 环境配置
├── .env.example            # 环境配置示例
└── composer.json           # Composer 配置
```

## 开发指南

### 添加路由

编辑 `routes/api.php`：

```php
$router->get('/api/posts', 'App\Controllers\PostController@index');
$router->post('/api/posts', 'App\Controllers\PostController@store');
```

### 创建控制器

在 `app/Controllers/` 下创建：

```php
<?php

namespace App\Controllers;

use Framework\Http\Request;
use Framework\Http\Response;

class PostController
{
    public function index(Request $request): Response
    {
        return Response::success(['posts' => []]);
    }
}
```

### 创建模型

在 `app/Models/` 下创建：

```php
<?php

namespace App\Models;

use Framework\Database\Model;

class Post extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['title', 'content'];
}
```

### 使用验证器

```php
use Framework\Validation\Validator;

$validator = new Validator($data, [
    'title' => ['required', 'min:3'],
    'email' => ['required', 'email'],
]);

if (!$validator->validate()) {
    return Response::error('验证失败', 422, $validator->errors());
}
```

## 运行测试

```bash
# 运行所有测试
vendor/bin/phpunit

# 运行单元测试
vendor/bin/phpunit tests/Unit

# 运行集成测试
vendor/bin/phpunit tests/Integration

# 生成覆盖率报告
vendor/bin/phpunit --coverage-html coverage/
```

## 环境变量

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `APP_ENV` | `development` | 环境（development/production） |
| `APP_DEBUG` | `true` | 调试模式 |
| `DB_HOST` | `localhost` | 数据库主机 |
| `DB_PORT` | `3306` | 数据库端口 |
| `DB_DATABASE` | - | 数据库名 |
| `DB_USERNAME` | `root` | 数据库用户 |
| `DB_PASSWORD` | - | 数据库密码 |
| `SWOOLE_PORT` | `9501` | Swoole 端口 |
| `SWOOLE_WORKERS` | CPU 核心数 | Worker 进程数 |

## Docker 运行

如需使用 Docker，请参考项目根目录的 `docker-compose.yml`：

```bash
# Swoole 模式
docker-compose --profile swoole up -d

# FPM 模式
docker-compose --profile fpm up -d
```

## 技术栈

- PHP 8.0+
- Swoole 5.0+（可选，高并发模式）
- PSR-3 / PSR-4 / PSR-11

## 许可证

MIT License
