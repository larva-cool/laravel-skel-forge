<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\HasLabel;

/**
 * 系统配置输入类型枚举
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
enum SettingInputType: string implements \JsonSerializable
{
    use HasLabel;

    case STRING = 'string'; // 单行文本
    case TEXTAREA = 'textarea'; // 多行文本
    case INT = 'int'; // 数字
    case BOOL = 'bool'; // 开关
    case SELECT = 'select'; // 下拉选择
    case RADIO = 'radio'; // 单选
    case CHECKBOX = 'checkbox'; // 多选
    case REMOTE_SELECT = 'remote_select'; // 远程下拉选择
    case REMOTE_RADIO = 'remote_radio'; // 远程单选
    case REMOTE_CHECKBOX = 'remote_checkbox'; // 远程多选
    case IMAGE = 'image'; // 图片

    /**
     * 获取配置输入类型标签
     */
    public function label(): string
    {
        return match ($this) {
            self::STRING => '单行文本',
            self::TEXTAREA => '多行文本',
            self::INT => '数字',
            self::BOOL => '开关',
            self::SELECT => '下拉选择',
            self::RADIO => '单选',
            self::CHECKBOX => '多选',
            self::REMOTE_SELECT => '远程下拉选择',
            self::REMOTE_RADIO => '远程单选',
            self::REMOTE_CHECKBOX => '远程多选',
            self::IMAGE => '图片',
        };
    }

    /**
     * 是否为远程数据源类型（配置参数需包含 url）
     */
    public function isRemote(): bool
    {
        return in_array($this, [self::REMOTE_SELECT, self::REMOTE_RADIO, self::REMOTE_CHECKBOX], true);
    }

    /**
     * 是否为需要 options 配置参数的选项类型
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::SELECT, self::RADIO, self::CHECKBOX], true);
    }
}
