#!/bin/bash

# 测试运行脚本

echo "🧪 运行 PHP 框架测试套件..."
echo ""

# 检查是否在容器中
if [ -f /.dockerenv ]; then
    echo "📦 在 Docker 容器中运行测试"
else
    echo "💻 在本地环境运行测试"
fi

# 检查 Composer 依赖
if [ ! -d "vendor" ]; then
    echo "📥 安装 Composer 依赖..."
    composer install
fi

# 运行测试
echo ""
echo "🚀 开始运行测试..."
echo ""

vendor/bin/phpunit "$@"

# 显示测试结果
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ 所有测试通过！"
else
    echo ""
    echo "❌ 部分测试失败，请检查输出"
    exit 1
fi
