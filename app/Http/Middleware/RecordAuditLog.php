<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use App\Models\System\AuditLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 操作审计日志中间件
 *
 * 自动记录后台写操作（POST/PUT/PATCH/DELETE），在响应发送后异步落库，
 * 不阻塞请求响应。审计写入失败仅记录警告日志，不影响业务流程。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class RecordAuditLog
{
    /** @var string 敏感字段掩码 */
    public const string MASK = '******';

    /** @var string 请求开始时间在 Request 上的存储键 */
    private const string STARTED_AT_KEY = 'audit.started_at';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::STARTED_AT_KEY, microtime(true));

        return $next($request);
    }

    /**
     * 响应发送后写入审计日志
     *
     * 认证失败等场景下 handle() 可能未执行（认证中间件优先级更高并抛出异常），
     * 因此这里不依赖 handle() 留下的上下文，独立完成判定与采集。
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldRecord($request)) {
            return;
        }

        try {
            $operator = $request->user('admin');
            $startedAt = (float) $request->attributes->get(
                self::STARTED_AT_KEY,
                $request->server('REQUEST_TIME_FLOAT') ?: microtime(true)
            );

            AuditLog::create([
                'operator_id' => $operator instanceof Admin ? $operator->id : null,
                'operator_name' => $operator instanceof Admin ? ($operator->name ?: $operator->username) : null,
                'route' => $request->route()?->getName(),
                'method' => $request->getMethod(),
                'uri' => Str::limit($request->path(), 255, ''),
                'title' => $this->resolveTitle($request),
                'payload' => $this->resolvePayload($request),
                'status_code' => $response->getStatusCode(),
                'error' => $this->resolveError($response),
                'runtime' => round(microtime(true) - $startedAt, 3),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), AuditLog::USER_AGENT_MAX_LENGTH, ''),
            ]);
        } catch (Throwable $e) {
            Log::warning('审计日志写入失败：'.$e->getMessage());
        }
    }

    /**
     * 判断当前请求是否需要记录
     */
    protected function shouldRecord(Request $request): bool
    {
        if (! config('audit.enabled', true)) {
            return false;
        }

        if (! in_array($request->getMethod(), (array) config('audit.methods', []), true)) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return false;
        }

        return ! Str::is((array) config('audit.except', []), $routeName);
    }

    /**
     * 解析操作描述，未配置时回退为「方法 + 路径」
     */
    protected function resolveTitle(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();

        foreach ((array) config('audit.titles', []) as $pattern => $title) {
            if (Str::is($pattern, $routeName)) {
                return $title;
            }
        }

        return Str::limit($request->getMethod().' '.$request->path(), 128, '');
    }

    /**
     * 解析并脱敏请求参数
     *
     * @return array<string, mixed>|null
     */
    protected function resolvePayload(Request $request): ?array
    {
        $payload = $this->sanitize($request->all());

        if ($payload === []) {
            return null;
        }

        $maxLength = (int) config('audit.max_payload_length', 5000);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($encoded !== false && strlen($encoded) > $maxLength) {
            return ['__truncated' => Str::limit($encoded, $maxLength, '...')];
        }

        return $payload;
    }

    /**
     * 递归脱敏：敏感字段替换为掩码，上传文件替换为文件描述
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitize(array $input): array
    {
        /** @var array<int, string> $sensitiveKeys */
        $sensitiveKeys = (array) config('audit.sensitive_keys', []);

        foreach ($input as $key => $value) {
            if (is_string($key) && Str::is($sensitiveKeys, $key)) {
                $input[$key] = self::MASK;

                continue;
            }

            if ($value instanceof UploadedFile) {
                $input[$key] = sprintf('[file] %s (%d bytes)', $value->getClientOriginalName(), $value->getSize());

                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->sanitize($value);
            }
        }

        return $input;
    }

    /**
     * 从失败响应中提取错误消息
     */
    protected function resolveError(Response $response): ?string
    {
        if ($response->getStatusCode() < 400) {
            return null;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
                return AuditLog::formatError($data['message']);
            }
        }

        return AuditLog::formatError((string) $response->getContent());
    }
}
