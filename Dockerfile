# syntax=docker/dockerfile:1.7

# =============================================================================
# 基础镜像（PHP 运行时 + 应用代码 + 依赖）
# 说明：前端资源不在镜像内构建，PHP 侧仅提供 JSON API
# =============================================================================
FROM ghcr.io/larva-cool/php:8.4-alpine AS base

ARG ENV=prod

# 系统环境变量优先级高于 .env（Laravel 使用不可变 Dotenv），
# 因此即使回退到 .env.example，APP_ENV 仍为 production。
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack

WORKDIR /app

# 复制应用代码
COPY . .

# 确保 www-data 用户/组存在（部分精简镜像可能未创建）
RUN getent group www-data >/dev/null || addgroup -g 82 -S www-data; \
    getent passwd www-data >/dev/null || adduser -u 82 -D -S -G www-data www-data

# 安装生产依赖（跳过脚本，package:discover 延后到环境就绪后执行）
RUN composer install --no-dev --prefer-dist --no-progress --optimize-autoloader --no-scripts

# 创建运行时目录
RUN mkdir -p storage/app/public storage/logs \
        storage/framework/cache storage/framework/sessions storage/framework/views \
        bootstrap/cache

# 环境文件：优先使用 .env.<ENV>，否则回退到 .env.example
RUN if [ -f ".env.${ENV}" ]; then mv ".env.${ENV}" .env; else cp .env.example .env; fi

# 目录权限
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f bootstrap/cache/*.php

# 生成优化自动加载并发现包（此时环境已就绪）
RUN composer dump-autoload --optimize

# 存储软链接（失败不阻断构建，如 public/storage 已存在）
RUN php artisan storage:link || true

VOLUME ["/app/storage/app", "/app/storage/logs"]

# =============================================================================
# 镜像1：Api 入口（Octane）
# =============================================================================
FROM base AS api

EXPOSE 9501

# 健康检查：Octane 服务器状态
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD php artisan octane:status >/dev/null 2>&1 || exit 1

CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=9501"]

# =============================================================================
# 镜像2：队列 worker
# =============================================================================
FROM base AS worker

CMD ["php", "artisan", "queue:work", "--tries=3", "--timeout=300", "--sleep=3"]

# =============================================================================
# 镜像3：定时任务
# =============================================================================
FROM base AS scheduler

CMD ["php", "artisan", "schedule:work"]

# =============================================================================
# 镜像4：Reverb 广播服务
# =============================================================================
FROM base AS reverb

EXPOSE 9502

CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=9502"]

# =============================================================================
# 镜像5：Pulse 指标采集
# =============================================================================
FROM base AS pulse

CMD ["php", "artisan", "pulse:check"]
