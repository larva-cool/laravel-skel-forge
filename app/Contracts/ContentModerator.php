<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Contracts;

/**
 * 云内容审核接口（预留扩展点）
 *
 * 实现此接口并在 AppServiceProvider 中绑定，即可接入腾讯云 TMS / 阿里云绿网等云服务。
 * 当 config('sensitive.default') 设为 'cloud' 时，SensitiveWordService 会通过此接口做二次审核。
 */
interface ContentModerator
{
    /**
     * 审核一段文本
     */
    public function moderate(string $text): ModerationResult;
}
