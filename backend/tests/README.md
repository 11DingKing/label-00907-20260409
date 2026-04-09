# 测试文档

## 概述

本项目包含完整的单元测试和集成测试，确保框架的所有核心功能正常工作。

## 快速开始

### 运行所有测试

```bash
# 在容器中
docker-compose exec php bash
cd /var/www/html
./tests/run-tests.sh

# 或直接使用 PHPUnit
vendor/bin/phpunit
```

### 运行特定测试套件

```bash
# 只运行单元测试
vendor/bin/phpunit tests/Unit

# 只运行集成测试
vendor/bin/phpunit tests/Integration

# 运行特定测试类
vendor/bin/phpunit tests/Unit/Core/ContainerTest.php
```

### 生成覆盖率报告

```bash
vendor/bin/phpunit --coverage-html coverage/
```

然后在浏览器中打开 `coverage/index.html` 查看覆盖率报告。

## 测试结构

### 单元测试（Unit Tests）

单元测试位于 `tests/Unit/` 目录，测试各个组件的独立功能：

- **Core/** - 核心组件测试
  - `ContainerTest.php` - DI 容器测试
  - `RouterTest.php` - 路由系统测试

- **Http/** - HTTP 层测试
  - `RequestTest.php` - 请求对象测试
  - `ResponseTest.php` - 响应对象测试

- **Database/** - 数据库层测试
  - `QueryTest.php` - 查询构建器测试
  - `ConnectionPoolTest.php` - 连接池测试

- **Validation/** - 验证器测试
  - `ValidatorTest.php` - 数据验证测试

### 集成测试（Integration Tests）

集成测试位于 `tests/Integration/` 目录，测试组件之间的协作：

- `ApplicationTest.php` - 应用核心集成测试
- `Database/ModelTest.php` - ORM 模型集成测试（需要数据库）

## 测试覆盖的功能

### ✅ 已覆盖

1. **DI 容器**
   - 服务绑定和解析
   - 单例模式
   - 自动依赖注入
   - 参数注入

2. **路由系统**
   - 路由注册（GET、POST、PUT、DELETE）
   - 路由匹配
   - 路由参数提取
   - 路由分组
   - 路由中间件
   - 路由缓存

3. **HTTP 层**
   - 请求对象创建
   - 参数获取（GET、POST）
   - JSON 请求解析
   - 响应对象创建
   - 成功/错误响应

4. **数据库层**
   - 查询构建器
   - WHERE 条件
   - 排序和分页
   - 连接池管理

5. **验证器**
   - 所有内置验证规则
   - 错误信息获取
   - 多规则验证

6. **应用核心**
   - 请求处理流程
   - 中间件执行
   - 异常处理
   - 路由分发

## 编写新测试

### 单元测试示例

```php
<?php

namespace Tests\Unit\YourNamespace;

use PHPUnit\Framework\TestCase;

class YourClassTest extends TestCase
{
    public function testYourMethod(): void
    {
        // Arrange（准备）
        $instance = new YourClass();
        
        // Act（执行）
        $result = $instance->yourMethod();
        
        // Assert（断言）
        $this->assertEquals('expected', $result);
    }
}
```

### 集成测试示例

```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class YourIntegrationTest extends TestCase
{
    public function testComponentIntegration(): void
    {
        // 测试组件之间的协作
    }
}
```

## 测试最佳实践

1. **测试命名**：使用描述性的测试方法名，如 `testUserCanLogin()`
2. **AAA 模式**：Arrange（准备）、Act（执行）、Assert（断言）
3. **独立性**：每个测试应该独立，不依赖其他测试的执行顺序
4. **快速执行**：单元测试应该快速执行，避免慢速操作
5. **覆盖率**：保持较高的代码覆盖率（目标 80%+）

## 持续集成

测试可以在 CI/CD 流程中自动运行：

```yaml
# .github/workflows/tests.yml 示例
- name: Run Tests
  run: |
    cd backend
    composer install
    vendor/bin/phpunit
```

## 故障排除

### 测试失败

1. 检查 PHP 版本（需要 PHP 8.0+）
2. 确保所有依赖已安装：`composer install`
3. 检查测试环境配置：`phpunit.xml`

### 数据库相关测试

数据库集成测试需要实际的数据库连接。如果数据库未配置，这些测试会被跳过（使用 `markTestSkipped()`）。

## 贡献

添加新功能时，请：

1. 编写对应的测试用例
2. 确保所有测试通过
3. 保持或提高代码覆盖率
4. 更新本文档
