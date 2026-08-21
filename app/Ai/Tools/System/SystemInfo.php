<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Ai\Tools\System;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * 系统运行信息查询工具。
 *
 * 返回应用版本、运行环境、数据库连通性、缓存/队列驱动、存储盘等运维信息。只读操作。
 */
class SystemInfo implements Tool
{
    /**
     * 工具描述。
     */
    public function description(): Stringable|string
    {
        return '查询当前系统运行信息，包括 Laravel/PHP 版本、环境、时区、数据库连接状态、默认缓存/队列/会话驱动、可用存储盘、PHP 资源限制等。用于快速了解部署环境与排查基础问题。只读操作，不泄露密钥。';
    }

    /**
     * 参数 Schema。
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * 执行查询。
     */
    public function handle(Request $request): Stringable|string
    {
        return json_encode([
            'application' => [
                'name' => config('app.name'),
                'env' => app()->environment(),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => app()->getLocale(),
                'laravel_version' => app()->version(),
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'database' => $this->databaseInfo(),
            'drivers' => [
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
                'filesystem' => config('filesystems.default'),
                'broadcast' => config('broadcasting.default'),
            ],
            'storage_disks' => array_keys(config('filesystems.disks', [])),
            'server_time' => now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 数据库连接信息（脱敏，仅返回连接状态与数据库名）。
     *
     * @return array<string, mixed>
     */
    protected function databaseInfo(): array
    {
        $default = config('database.default');
        $connection = config("database.connections.{$default}", []);

        $info = [
            'default' => $default,
            'driver' => $connection['driver'] ?? null,
            'database' => $connection['database'] ?? null,
        ];

        try {
            DB::connection()->getPdo();
            $info['status'] = 'connected';
        } catch (Throwable $e) {
            $info['status'] = 'disconnected';
            $info['error'] = $e->getMessage();
        }

        return $info;
    }
}
