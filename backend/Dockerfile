# 使用多阶段构建，支持 ARM 和 X86
FROM --platform=$BUILDPLATFORM php:8.2-cli AS builder

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /app

# 先复制 composer.json
COPY composer.json ./

# 创建必要的目录结构（确保 autoload 能正常生成）
RUN mkdir -p src app

# 复制源代码
COPY src/ ./src/
COPY app/ ./app/

# 安装依赖（忽略 dev 依赖）
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs || \
    (cat /root/.composer/logs/* 2>/dev/null; exit 1)

# 生产镜像
FROM --platform=$BUILDPLATFORM php:8.2-fpm

# 安装系统依赖和 PHP 扩展
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
    && rm -rf /var/lib/apt/lists/*

# 安装 Swoole（可选，如果需要）
RUN pecl install swoole && docker-php-ext-enable swoole || true

# 设置工作目录
WORKDIR /var/www/html

# 从构建阶段复制 vendor
COPY --from=builder /app/vendor ./vendor

# 复制项目文件
COPY src/ ./src/
COPY app/ ./app/
COPY config/ ./config/
COPY routes/ ./routes/
COPY public/ ./public/
COPY bootstrap/ ./bootstrap/
COPY schema.sql ./

# 创建存储目录
RUN mkdir -p storage/logs storage/cache \
    && chown -R www-data:www-data /var/www/html

# 暴露端口
EXPOSE 9000

# 启动 PHP-FPM
CMD ["php-fpm"]
