<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Mcp\Tools\Common;

use App\Http\Resources\Api\V1\AgreementResource;
use App\Models\Operation\Agreement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * 获取最新已发布的协议
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Name('common.agreement.get')]
#[Description('按类别编码获取该类别的最新已发布协议（如 user_agreement、privacy_policy）。')]
#[IsReadOnly]
#[IsIdempotent]
class GetAgreementTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $arguments = $request->validate([
            'category' => ['required', 'string', 'max:64'],
        ]);

        $agreement = Agreement::getPublishedByCategory($arguments['category']);

        if (! $agreement) {
            return Response::error('Agreement not found.');
        }

        return Response::structured((new AgreementResource($agreement))->resolve());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('协议类别编码，如 user_agreement、privacy_policy')->max(64)->required(),
        ];
    }
}
