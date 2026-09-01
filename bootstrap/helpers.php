<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Laravel\Telescope\Telescope;
use Larva\Agent\Agent;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;

if (! function_exists('cpu_count')) {
    /**
     * 获取 CPU 核心数
     *
     * @return int CPU 核心数
     */
    function cpu_count(): int
    {
        // Windows does not support the number of processes setting.
        if (DIRECTORY_SEPARATOR === '\\') {
            return 1;
        }
        $count = 4;
        if (is_callable('shell_exec')) {
            if (strtolower(PHP_OS) === 'darwin') {
                $count = (int) shell_exec('sysctl -n machdep.cpu.core_count');
            } else {
                try {
                    $count = (int) shell_exec('nproc');
                } catch (Throwable $ex) {
                    // Do nothing
                }
            }
        }

        return $count > 0 ? $count : 4;
    }
}

if (! function_exists('per_page')) {
    /**
     * 获取分页每页条数
     *
     * @param  Request  $request  HTTP 请求实例
     * @param  int  $limit  默认每页条数
     * @return int 每页条数（1-100之间）
     */
    function per_page($request, int $limit = 15)
    {
        return clamp($request->input('per_page', $limit), 1, 100);
    }
}

if (! function_exists('mobile_replace')) {
    /**
     * 手机号脱敏处理
     *
     * @param  string|null  $value  原始手机号
     * @param  string  $character  替换字符，默认为 *
     * @param  int  $index  开始替换的位置
     * @param  int  $length  替换的长度
     * @return string 脱敏后的手机号
     */
    function mobile_replace(?string $value, $character = '*', int $index = 3, int $length = 4): string
    {
        if (! $value) {
            return '';
        }

        return Str::mask($value, $character, $index, $length);
    }
}

if (! function_exists('parse_mentioned_usernames')) {
    /**
     * 解析内容中被 @ 提及的用户名
     *
     * @param  string  $content  原始内容
     * @return array<string> 被提及的用户名列表
     */
    function parse_mentioned_usernames(string $content): array
    {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);

        return $matches[1] ?? [];
    }
}

if (! function_exists('sms')) {

    /**
     * 发送短信
     *
     * @param  \Overtrue\EasySms\PhoneNumber|string  $mobile
     * @param  string|\Overtrue\EasySms\Message  $message
     * @return array|EasySms
     *
     * @throws NoGatewayAvailableException|InvalidArgumentException
     */
    function sms()
    {
        $arguments = func_get_args();
        /** @var EasySms $sms */
        $sms = app(EasySms::class);
        if (empty($arguments)) {
            return $sms;
        }

        return $sms->send($arguments[0], $arguments[1]);
    }
}

if (! function_exists('disable_telescope')) {
    /**
     * 禁用 Telescope 数据记录，防止内存溢出
     */
    function disable_telescope(): void
    {
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }
    }
}

if (! function_exists('settings')) {
    /**
     * Get setting value or object.
     *
     * @param  mixed|null  $default
     * @return \App\Services\SettingManagerService|mixed
     */
    function settings(string $key = '', $default = null)
    {
        if (empty($key)) {
            return app(\App\Services\SettingManagerService::class);
        }

        return app(\App\Services\SettingManagerService::class)->get($key, $default);
    }
}

if (! function_exists('validation_exception')) {
    /**
     * Create a new validation exception from a plain array of messages.
     */
    function validation_exception($field, $message)
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}

if (! function_exists('parse_user_agent')) {
    /**
     * 解析UA
     */
    function parse_user_agent(string $userAgent): array
    {
        $userAgent = trim($userAgent);
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        return [
            'platform' => $agent->platform(),
            'device' => $agent->device(),
            'browser' => $agent->browser(),
            'isMobile' => $agent->isMobile(),
            'isTablet' => $agent->isTablet(),
            'isDesktop' => $agent->isDesktop(),
            'isPhone' => $agent->isPhone(),
        ];
    }
}

if (! function_exists('get_morph_maps')) {
    /**
     * get the morph map for polymorphic relations.
     */
    function get_morph_maps(): array
    {
        $maps = Relation::morphMap();
        foreach ($maps as $key => $val) {
            $maps[$key] = Lang::get('morph_maps.'.$val);
        }

        return $maps;
    }
}

if (! function_exists('source_types')) {
    /**
     * 获取源类型
     */
    function source_types(): array
    {
        $maps = Relation::morphMap();

        return array_keys($maps);
    }
}

if (! function_exists('base64_urlencode')) {
    /**
     * URL 安全的 Base64 编码
     *
     * 将标准 Base64 的 + 替换为 -、/ 替换为 _，并去除末尾填充符 =
     */
    function base64_urlencode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (! function_exists('base64_urldecode')) {
    /**
     * URL 安全的 Base64 解码
     *
     * base64_urlencode 的逆操作
     */
    function base64_urldecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

/**
 * 解析IP归属地
 */
if (! function_exists('ip_location')) {
    function ip_location(string $ip): ?string
    {
        $searcher = new Ip2Region();

        return $searcher->simple($ip);
    }
}
