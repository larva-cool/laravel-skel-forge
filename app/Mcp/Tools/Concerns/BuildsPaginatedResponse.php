<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 构建统一的分页工具响应
 *
 * 响应结构：
 * {
 *   "items": [...],
 *   "pagination": {
 *     "total": 100,
 *     "per_page": 15,
 *     "current_page": 1,
 *     "last_page": 7,
 *     "has_more": true
 *   }
 * }
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait BuildsPaginatedResponse
{
    /**
     * 分页相关的校验规则（供 Tool 内 $request->validate 复用）
     *
     * @return array<string, array<int, mixed>>
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * 分页相关的 JSON Schema（供 Tool 的 schema() 复用）
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function paginationSchema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->description('分页页码，默认 1')->min(1),
            'per_page' => $schema->integer()->description('每页条数，默认 15，最大 100')->min(1)->max(100),
        ];
    }

    /**
     * 将分页器封装为统一的结构化响应
     *
     * @param  LengthAwarePaginator  $paginator  分页器
     * @param  class-string<JsonResource>  $resourceClass  资源类
     * @return array<string, mixed>
     */
    protected function paginatedResult(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'items' => $resourceClass::collection($paginator->getCollection())->resolve(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * 从工具入参中取每页条数（1-100，默认 15）
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function resolvePerPage(array $arguments): int
    {
        return clamp((int) ($arguments['per_page'] ?? 15), 1, 100);
    }

    /**
     * 从工具入参中取页码（>=1，默认 1）
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function resolvePage(array $arguments): int
    {
        return max(1, (int) ($arguments['page'] ?? 1));
    }
}
