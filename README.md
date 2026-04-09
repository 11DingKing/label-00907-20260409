# 轻量级高性能 PHP 框架

## 项目介绍

这是一个基于 ThinkPHP 易用性优势开发的轻量级 PHP 框架，**原生适配高并发**，核心特性包括：

- **架构严谨**：遵循 PSR 规范 + DI 容器 + 接口化设计
- **易用性**：约定优于配置 + 简洁 API + 中文注释
- **开发效率**：内置 CRUD / 验证 / 分页
- **高性能**：轻量级核心 + 路由缓存 + 懒加载
- **原生高并发**：Swoole 协程 HTTP Server + 数据库连接池（Channel 非阻塞）

## How to Run

### 前置要求

- Docker 和 Docker Compose
- Git

### 🚀 模式一：Swoole 高性能模式（推荐）

这是框架的**核心运行模式**，原生支持高并发，适合生产环境。

```bash
# 克隆项目
git clone <repository-url>

# 启动 Swoole 服务（默认模式，无需指定 profile）
docker-compose up --build -d

# 查看服务状态
docker-compose ps

# 查看 Swoole 日志
docker-compose logs -f swoole
```

**访问地址**：http://localhost:9501

**性能特点**：
- 协程并发处理请求
- 数据库连接池（Channel 非阻塞等待）
- 常驻内存，无需重复加载
- 单机可支撑数万并发连接

### 🔧 模式二：传统 PHP-FPM 模式（开发调试）

适合本地开发和调试，支持热更新。

```bash
# 启动 FPM 服务
docker-compose --profile fpm up --build -d

# 查看服务状态
docker-compose ps
```

**访问地址**：http://localhost:9527

### 本地运行 Swoole（无 Docker）

如果本地已安装 PHP 8.0+ 和 Swoole 扩展：

```bash
cd backend

# 安装依赖
composer install

# 启动 Swoole 服务器
php bin/server

# 或指定端口和 Worker 数
php bin/server --port=8080 --workers=4

# 后台运行
php bin/server --daemon
```

### 停止服务

```bash
# 停止 Swoole 模式
docker-compose down

# 停止 FPM 模式
docker-compose --profile fpm down

# 清理数据
docker-compose down -v
```

## Services

### Swoole 模式（推荐）

| 服务 | 端口 | 说明 |
|------|------|------|
| Swoole | 9501 | 高性能 HTTP Server，协程模式 |
| MySQL | 33067 | 数据库服务器 |

### FPM 模式（开发）

| 服务 | 端口 | 说明 |
|------|------|------|
| Nginx | 9527 | Web 服务器 |
| PHP-FPM | 9000 | PHP 应用服务器（内部） |
| MySQL | 33067 | 数据库服务器 |

### 数据库连接信息

- **主机**：mysql（容器内）或 localhost:33067（宿主机）
- **数据库名**：framework_test
- **用户名**：root
- **密码**：root

## 测试账号

系统已预置以下测试账号：

| 用户名 | 邮箱 | 密码 |
|--------|------|------|
| admin | admin@example.com | password |
| test | test@example.com | password |

> 注意：实际密码已加密存储，上述为示例说明。

## 题目内容

### 需求描述

基于 ThinkPHP 的易用性优势，开发一个架构更严谨、性能更优且原生适配高并发的轻量级 PHP 框架。

### 核心要求

1. **架构严谨**
   - 遵循 PSR 规范（PSR-4 自动加载、PSR-7 HTTP 消息、PSR-11 容器接口）
   - DI 容器（基于反射的依赖注入）
   - 接口化设计（所有核心组件基于接口）

2. **易用性**
   - 约定优于配置（默认路由规则、默认目录结构）
   - 简洁 API（链式调用、流畅接口）
   - 中文注释（所有代码包含详细中文注释）

3. **开发效率**
   - 内置 CRUD（Model 提供 save/delete/find 等快捷方法）
   - 验证器（内置常用验证规则，支持自定义）
   - 分页器（自动分页，支持多种分页样式）

4. **性能优化**
   - 轻量级核心（核心代码精简，按需加载）
   - 路由缓存（支持路由规则缓存）
   - 懒加载（组件按需实例化）

5. **高并发支持**
   - Swoole 协程（原生支持 Swoole HTTP Server）
   - 连接池（数据库连接池，Swoole Channel 实现非阻塞等待）

### 实现特性

✅ **PSR 规范支持**
- PSR-4：自动加载规范
- PSR-3：日志接口（Logger 实现 LoggerInterface）
- PSR-11：容器接口（Container 实现 ContainerInterface）

✅ **核心组件**
- Container（DI 容器）：实现 PSR-11，支持单例、工厂模式、懒加载、自动解析依赖
- Router（路由管理器）：支持 RESTful 路由、路由分组、路由参数、路由缓存
- Request/Response（HTTP 消息）：遵循 PSR-7 简化版
- Application（应用核心）：请求处理、路由分发、中间件执行

✅ **数据库层**
- Connection（数据库连接）：封装 PDO，支持事务
- ConnectionPool（连接池）：Swoole 协程环境使用 Channel 实现非阻塞等待
- Model（ORM 模型）：通过 Config 服务获取配置，支持依赖注入
- Query（查询构建器）：链式调用构建 SQL
- Paginator（分页器）：自动分页，支持多种样式

✅ **日志与异常**
- Logger（日志服务）：实现 PSR-3 LoggerInterface
- Exception Handler（异常处理器）：通过依赖注入使用 Logger 服务

✅ **验证器**
- Validator：内置常用验证规则，支持自定义

✅ **原生 Swoole 支持**
- `bin/server` 命令行启动脚本
- 协程 HTTP Server
- 可配置 Worker 进程数
- 支持守护进程模式
- 数据库连接池（Channel 非阻塞）

## 项目结构

```
.
├── backend/                    # 项目根目录
│   ├── app/                   # 应用代码（用户开发目录）
│   │   ├── Controllers/       # 控制器
│   │   └── Models/            # 模型
│   ├── bin/                   # 可执行脚本
│   │   └── server             # Swoole 服务器启动脚本
│   ├── bootstrap/             # 引导文件
│   │   └── app.php            # 应用初始化
│   ├── config/                # 配置文件
│   │   ├── app.php            # 应用配置
│   │   └── database.php       # 数据库配置
│   ├── public/                # Web 入口目录
│   │   └── index.php          # 入口文件（FPM 模式）
│   ├── routes/                # 路由定义
│   │   ├── api.php            # API 路由
│   │   └── web.php            # Web 路由
│   ├── src/                   # 框架核心库
│   │   ├── Core/              # 核心类（Application, Container, Router）
│   │   ├── Http/              # HTTP 相关（Request, Response, Middleware）
│   │   ├── Database/          # 数据库层（Connection, ConnectionPool, Model）
│   │   ├── Log/               # 日志服务（PSR-3 实现）
│   │   ├── Validation/        # 验证器
│   │   └── Exception/         # 异常处理
│   ├── storage/               # 存储目录
│   │   ├── cache/             # 缓存文件
│   │   └── logs/              # 日志文件
│   ├── tests/                 # 测试用例
│   ├── schema.sql             # 数据库建表语句
│   ├── composer.json          # Composer 配置
│   ├── Dockerfile             # Docker 镜像（FPM 模式）
│   └── Dockerfile.swoole      # Docker 镜像（Swoole 模式）
├── docs/                      # 文档
│   └── project_design.md      # 项目设计文档
├── docker-compose.yml         # Docker Compose 配置
├── .gitignore                 # Git 忽略文件
└── README.md                  # 项目说明
```

## API 文档

### 用户管理 API

#### 1. 获取用户列表（分页）

```http
GET /api/users?page=1&page_size=15
```

**响应示例**：
```json
{
  "code": 0,
  "message": "获取成功",
  "data": {
    "items": [
      {
        "id": 1,
        "username": "admin",
        "email": "admin@example.com",
        "created_at": "2026-01-25 10:00:00",
        "updated_at": "2026-01-25 10:00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 2,
      "total_pages": 1,
      "has_previous": false,
      "has_next": false
    }
  }
}
```

#### 2. 获取单个用户

```http
GET /api/users/{id}
```

#### 3. 创建用户

```http
POST /api/users
Content-Type: application/json

{
  "username": "newuser",
  "email": "newuser@example.com",
  "password": "password123"
}
```

**成功响应**（HTTP 201）：
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": 3,
    "username": "newuser",
    "email": "newuser@example.com",
    "created_at": "2026-03-16 12:00:00",
    "updated_at": "2026-03-16 12:00:00"
  }
}
```

**验证失败**（HTTP 422）：
```json
{
  "code": 422,
  "message": "验证失败",
  "data": {
    "username": ["username 字段是必填的"],
    "email": ["email 必须是有效的邮箱地址"]
  }
}
```

**用户名或邮箱重复**（HTTP 409）：
```json
{
  "code": 409,
  "message": "用户名或邮箱已存在",
  "data": null
}
```

#### 4. 更新用户

```http
PUT /api/users/{id}
Content-Type: application/json

{
  "username": "updateduser",
  "email": "updated@example.com"
}
```

**用户不存在**（HTTP 404）：
```json
{
  "code": 404,
  "message": "用户不存在",
  "data": null
}
```

#### 5. 删除用户

```http
DELETE /api/users/{id}
```

## 测试

### 运行测试

项目包含完整的单元测试和集成测试，确保所有功能正常工作。

#### 方式1：在容器中运行测试

```bash
# 进入 PHP 容器
docker-compose exec php bash

# 安装测试依赖（如果还未安装）
composer install

# 运行所有测试
vendor/bin/phpunit

# 运行单元测试
vendor/bin/phpunit tests/Unit

# 运行集成测试
vendor/bin/phpunit tests/Integration

# 生成测试覆盖率报告
vendor/bin/phpunit --coverage-html coverage/
```

#### 方式2：在本地运行测试

```bash
cd backend

# 安装依赖
composer install

# 运行测试
vendor/bin/phpunit
```

### 测试覆盖

测试用例覆盖以下核心功能：

#### 单元测试（Unit Tests）

- ✅ **Container 测试**：DI 容器绑定、解析、单例模式、依赖注入
- ✅ **Router 测试**：路由注册、匹配、参数提取、分组、中间件、缓存
- ✅ **Request 测试**：请求创建、参数获取、JSON 解析、路由参数
- ✅ **Response 测试**：JSON/HTML 响应、成功/错误响应、状态码和头部设置
- ✅ **Query 测试**：查询构建器、WHERE 条件、排序、分页
- ✅ **ConnectionPool 测试**：连接池获取、释放、状态管理
- ✅ **Validator 测试**：所有验证规则（required、email、min、max、regex 等）
- ✅ **UserController 测试**：全部 CRUD 接口、参数验证、异常捕获、响应结构一致性、友好错误提示

#### 集成测试（Integration Tests）

- ✅ **Application 测试**：应用启动、路由处理、中间件、异常处理
- ✅ **Model 测试**：ORM 模型操作（需要数据库连接）

### 测试文件结构

```
backend/tests/
├── bootstrap.php              # 测试引导文件
├── Unit/                      # 单元测试
│   ├── Controllers/
│   │   └── UserControllerTest.php
│   ├── Core/
│   │   ├── ContainerTest.php
│   │   └── RouterTest.php
│   ├── Http/
│   │   ├── RequestTest.php
│   │   └── ResponseTest.php
│   ├── Database/
│   │   ├── QueryTest.php
│   │   └── ConnectionPoolTest.php
│   └── Validation/
│       └── ValidatorTest.php
└── Integration/               # 集成测试
    ├── ApplicationTest.php
    └── Database/
        └── ModelTest.php
```

### 编写新测试

添加新功能时，请同时编写对应的测试用例：

```php
<?php

namespace Tests\Unit\YourNamespace;

use PHPUnit\Framework\TestCase;

class YourClassTest extends TestCase
{
    public function testYourMethod(): void
    {
        // 测试代码
        $this->assertTrue(true);
    }
}
```

## 开发指南

### 快速开始

项目采用标准骨架结构，开发者可以直接在以下目录进行开发：

- `app/Controllers/` - 控制器
- `app/Models/` - 模型
- `config/` - 配置文件
- `routes/` - 路由定义

### 添加新路由

在 `backend/routes/api.php` 中添加 API 路由：

```php
$router->get('/api/posts', 'App\Controllers\PostController@index');
$router->post('/api/posts', 'App\Controllers\PostController@store');
```

在 `backend/routes/web.php` 中添加 Web 路由：

```php
$router->get('/about', function () {
    return Response::success(['page' => 'about']);
});
```

### 创建新控制器

在 `backend/app/Controllers/` 目录下创建：

```php
<?php

namespace App\Controllers;

use Framework\Http\Request;
use Framework\Http\Response;

class PostController
{
    public function index(Request $request): Response
    {
        return Response::success(['posts' => []], '获取成功');
    }
}
```

### 创建新模型

在 `backend/app/Models/` 目录下创建：

```php
<?php

namespace App\Models;

use Framework\Database\Model;

class Post extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['title', 'content', 'user_id'];
    protected array $hidden = [];
}
```

### 使用验证器

```php
use Framework\Validation\Validator;

$validator = new Validator($data, [
    'title' => ['required', 'string', 'min:3', 'max:100'],
    'email' => ['required', 'email'],
]);

if (!$validator->validate()) {
    return Response::error('验证失败', 422, $validator->errors());
}
```

### 使用日志服务

```php
use Framework\Log\Logger;

$logger = Logger::getInstance();
$logger->info('用户登录成功', ['user_id' => 1]);
$logger->error('数据库连接失败', ['host' => 'localhost']);
```

### 配置文件

配置文件位于 `backend/config/` 目录：

- `app.php` - 应用配置（环境、调试模式、时区）
- `database.php` - 数据库配置（连接信息、连接池）

配置会自动从环境变量加载，也可以直接修改配置文件。

## 技术栈

- **PHP**: 8.0+（推荐 8.2）
- **Swoole**: 5.0+（高性能协程模式，**核心运行时**）
- **数据库**: MySQL 8.0
- **Web 服务器**: Swoole HTTP Server（生产）/ Nginx（开发）
- **容器化**: Docker + Docker Compose
- **PSR 规范**: PSR-3（日志）、PSR-4（自动加载）、PSR-11（容器）

## 环境变量配置

框架支持通过环境变量进行配置，以下是可用的环境变量：

### 应用配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `APP_ENV` | `development` | 应用环境（`development`/`production`） |
| `APP_DEBUG` | `true` | 是否开启调试模式 |
| `ROUTE_CACHE_ENABLED` | 生产环境自动启用 | 是否启用路由缓存 |
| `ROUTE_CACHE_DIR` | `storage/cache` | 路由缓存目录 |

### Swoole 配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `SWOOLE_HOST` | `0.0.0.0` | 监听地址 |
| `SWOOLE_PORT` | `9501` | 监听端口 |
| `SWOOLE_WORKERS` | CPU 核心数 | Worker 进程数 |

### 数据库配置

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `DB_HOST` | `localhost` | 数据库主机 |
| `DB_PORT` | `3306` | 数据库端口 |
| `DB_DATABASE` | - | 数据库名称 |
| `DB_USERNAME` | `root` | 数据库用户名 |
| `DB_PASSWORD` | - | 数据库密码 |
| `DB_POOL_MAX` | `10` | 连接池最大连接数 |
| `DB_POOL_TIMEOUT` | `3.0` | 连接池等待超时（秒） |

### Redis 配置（可选）

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `REDIS_HOST` | `127.0.0.1` | Redis 主机 |
| `REDIS_PORT` | `6379` | Redis 端口 |
| `REDIS_PASSWORD` | - | Redis 密码 |
| `REDIS_DATABASE` | `0` | Redis 数据库索引 |

## Swoole 高并发说明

### bin/server 命令

框架提供 `bin/server` 作为 Swoole 服务器的标准启动入口：

```bash
# 查看帮助
php bin/server --help

# 默认启动（监听 0.0.0.0:9501）
php bin/server

# 指定端口
php bin/server --port=8080

# 指定 Worker 数量
php bin/server --workers=8

# 后台运行（守护进程）
php bin/server --daemon

# 组合使用
php bin/server --port=9501 --workers=4 --daemon
```

### 连接池高并发

数据库连接池在不同运行环境下有不同的行为：

- **Swoole 协程环境**：使用 `Swoole\Coroutine\Channel` 实现非阻塞等待，当连接池无可用连接时，协程会自动挂起等待，超时后抛出异常
- **传统 PHP-FPM 环境**：使用数组队列管理连接，连接池满时直接抛出异常

### 路由缓存

路由缓存可显著提升路由解析性能，框架会根据环境自动配置：

- **生产环境**（`APP_ENV=production`）：自动启用路由缓存
- **开发环境**（`APP_ENV=development`）：默认禁用路由缓存

也可通过 `ROUTE_CACHE_ENABLED=true/false` 手动控制。

## 许可证

MIT License

---

## 🧪 质检测试指南

### 快速健康检查

启动服务后，执行以下命令验证服务是否正常运行：

#### Swoole 模式（端口 9501）

```bash
# 1. 健康检查
curl -s http://localhost:9501/health | jq

# 2. 获取用户列表
curl -s http://localhost:9501/api/users | jq

# 3. 获取单个用户
curl -s http://localhost:9501/api/users/1 | jq

# 4. 创建用户
curl -s -X POST http://localhost:9501/api/users \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"testuser@example.com","password":"test123"}' | jq

# 5. 更新用户
curl -s -X PUT http://localhost:9501/api/users/1 \
  -H "Content-Type: application/json" \
  -d '{"username":"admin_updated","email":"admin_new@example.com"}' | jq

# 6. 删除用户（谨慎操作）
curl -s -X DELETE http://localhost:9501/api/users/3 | jq

# 7. 分页测试
curl -s "http://localhost:9501/api/users?page=1&page_size=10" | jq
```

#### FPM 模式（端口 9527）

```bash
# 1. 健康检查
curl -s http://localhost:9527/health | jq

# 2. 获取用户列表
curl -s http://localhost:9527/api/users | jq

# 3. 获取单个用户
curl -s http://localhost:9527/api/users/1 | jq

# 4. 创建用户
curl -s -X POST http://localhost:9527/api/users \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"testuser@example.com","password":"test123"}' | jq

# 5. 更新用户
curl -s -X PUT http://localhost:9527/api/users/1 \
  -H "Content-Type: application/json" \
  -d '{"username":"admin_updated","email":"admin_new@example.com"}' | jq

# 6. 删除用户（谨慎操作）
curl -s -X DELETE http://localhost:9527/api/users/3 | jq

# 7. 分页测试
curl -s "http://localhost:9527/api/users?page=1&page_size=10" | jq
```

### 预期响应示例

#### 健康检查成功
```json
{
  "code": 0,
  "message": "OK",
  "data": {
    "status": "healthy",
    "timestamp": "2026-01-25T10:00:00+08:00"
  }
}
```

#### 获取用户列表成功
```json
{
  "code": 0,
  "message": "获取成功",
  "data": {
    "items": [
      {
        "id": 1,
        "username": "admin",
        "email": "admin@example.com",
        "created_at": "2026-01-25 10:00:00",
        "updated_at": "2026-01-25 10:00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 2,
      "total_pages": 1
    }
  }
}
```

#### 创建用户成功
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": 3,
    "username": "testuser",
    "email": "testuser@example.com",
    "created_at": "2026-03-16 12:00:00",
    "updated_at": "2026-03-16 12:00:00"
  }
}
```

> 注意：创建成功返回 HTTP 201，密码字段不会出现在响应中。

#### 验证失败（HTTP 422）
```json
{
  "code": 422,
  "message": "验证失败",
  "data": {
    "username": ["username 字段是必填的"]
  }
}
```

#### 用户不存在（HTTP 404）
```json
{
  "code": 404,
  "message": "用户不存在",
  "data": null
}
```

#### 服务异常（HTTP 500）
```json
{
  "code": 500,
  "message": "创建用户失败，请稍后重试",
  "data": null
}
```

> 所有错误响应均返回友好中文提示，不会暴露系统内部错误信息。

### 一键测试脚本

将以下脚本保存为 `test.sh` 并执行：

```bash
#!/bin/bash

# 设置端口（Swoole: 9501, FPM: 9527）
PORT=${1:-9501}
BASE_URL="http://localhost:$PORT"

echo "=========================================="
echo "  质检测试 - 端口: $PORT"
echo "=========================================="

echo -e "\n[1/6] 健康检查..."
curl -s "$BASE_URL/health" | jq -r '.message // "FAILED"'

echo -e "\n[2/6] 获取用户列表..."
USERS=$(curl -s "$BASE_URL/api/users")
echo "$USERS" | jq -r '.data.pagination.total // "FAILED"' | xargs -I {} echo "用户总数: {}"

echo -e "\n[3/6] 获取单个用户..."
curl -s "$BASE_URL/api/users/1" | jq -r '.data.username // "FAILED"' | xargs -I {} echo "用户名: {}"

echo -e "\n[4/6] 创建用户..."
CREATE_RESULT=$(curl -s -X POST "$BASE_URL/api/users" \
  -H "Content-Type: application/json" \
  -d '{"username":"qa_test_'$(date +%s)'","email":"qa'$(date +%s)'@test.com","password":"test123"}')
echo "$CREATE_RESULT" | jq -r '.message // "FAILED"'

echo -e "\n[5/6] 更新用户..."
curl -s -X PUT "$BASE_URL/api/users/1" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin"}' | jq -r '.message // "FAILED"'

echo -e "\n[6/6] 分页测试..."
curl -s "$BASE_URL/api/users?page=1&page_size=5" | jq -r '.data.pagination.per_page // "FAILED"' | xargs -I {} echo "每页数量: {}"

echo -e "\n=========================================="
echo "  测试完成"
echo "=========================================="
```

运行测试：
```bash
# Swoole 模式
chmod +x test.sh
./test.sh 9501

# FPM 模式
./test.sh 9527
```

### 数据库连接测试

```bash
# 从宿主机连接 MySQL
mysql -h 127.0.0.1 -P 33067 -u root -proot framework_test -e "SELECT * FROM users;"

# 或使用 Docker
docker exec -it framework_mysql mysql -u root -proot framework_test -e "SELECT * FROM users;"
```

### 运行单元测试

```bash
# 进入容器运行测试
docker-compose --profile fpm exec php vendor/bin/phpunit

# 或本地运行（需要 PHP 环境）
cd backend && composer install && vendor/bin/phpunit
```

### 常见问题排查

| 问题 | 排查命令 |
|------|----------|
| 服务未启动 | `docker-compose ps` |
| 端口被占用 | `lsof -i :9501` 或 `lsof -i :9527` |
| 数据库连接失败 | `docker-compose logs mysql` |
| Swoole 日志 | `docker-compose logs swoole` |
| PHP 错误日志 | `docker-compose exec php tail -f /var/www/html/storage/logs/*.log`
