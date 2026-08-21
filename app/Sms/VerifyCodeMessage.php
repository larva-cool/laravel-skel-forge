<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Sms;

use Overtrue\EasySms\Contracts\GatewayInterface;
use Overtrue\EasySms\Message;

/**
 * 短信验证码
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class VerifyCodeMessage extends Message
{
    /**
     * 可用网关
     */
    protected array $gateways = ['volcengine'];

    /**
     * 验证码
     */
    protected string $code;

    /**
     * 验证码有效期
     */
    protected int $duration;

    /**
     * 验证码场景
     */
    protected string $scene;

    /**
     * 短信模板ID
     */
    protected array $templateCodes = [
        'aliyun' => [
            'default' => 'SMS_176526437', // 默认验证码
            'register' => 'SMS_157965369', // 注册验证码
            'login' => 'SMS_157965371', // 登录验证码
            'resetPassword' => 'SMS_157965368', // 修改密码
        ],
        'volcengine' => [
            'default' => 'ST_84db0ca7', // 默认验证码
            'register' => 'ST_84db0ca7', // 注册验证码
            'login' => 'ST_84db0ca7', // 登录验证码
            'resetPassword' => 'ST_84db0ca7', // 修改密码
        ],
    ];

    /**
     * 定义使用模板发送方式平台所需要的模板 ID
     */
    public function getTemplate(?GatewayInterface $gateway = null): string
    {
        if ($gateway->getName() == 'aliyun') {
            return settings('sms.aliyun_template_id');
        } elseif ($gateway->getName() == 'volcengine') {
            return settings('sms.template_id');
        }
        $templates = $this->templateCodes[$gateway->getName()] ?? [];

        return $templates[$this->scene] ?? $templates['default'];
    }

    /**
     * 模板参数
     */
    public function getData(?GatewayInterface $gateway = null): array
    {
        if (! is_null($gateway) && $gateway->getName() == 'aliyun') {
            return ['code' => $this->code];
        } elseif (! is_null($gateway) && $gateway->getName() == 'volcengine') {
            return ['code' => $this->code];
        }

        return [];
    }

    /**
     * 定义直接使用内容发送平台的内容
     */
    public function getContent(?GatewayInterface $gateway = null): ?string
    {
        if (! is_null($gateway) && $gateway->getName() == 'aliyun') {
            return sprintf('验证码：%s，如非本人操作，请忽略此短信。', $this->code);
        } elseif (! is_null($gateway) && $gateway->getName() == 'volcengine') {
            return sprintf('您的验证码是%s，有效期为10分钟，请尽快验证。', $this->code);
        }

        return '';
    }
}
