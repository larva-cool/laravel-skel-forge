<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\SocialProvider;
use App\Models\System\Social;
use App\Models\User;
use App\Services\WechatService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * 微信公众号登录请求
 *
 * @property-read string $code 微信回调的Code
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class WechatMpLoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?User
    {
        $oauth = WechatService::make()->getOAuth();
        try {
            $wUser = $oauth->userFromCode($this->code);
        } catch (\Exception  $e) {
            Log::warning($e->getMessage(), $e->getTrace());
            validation_exception('code', trans('system.server_busy'));
        }
        $social = Social::query()->with('user')
            ->ofUserType(User::class)
            ->ofOpenid(SocialProvider::WECHAT_MP, $wUser->getId())
            ->first();
        if (! $social || ! $social->user) {
            Log::warning('登录失败，该用户未注册：'.$wUser->getId());
            validation_exception('code', trans('auth.account_does_not_exist'));
        }
        if ($social->user->status->isFrozen()) {// 禁止掉的用户不允许登录
            $social->user->tokens()->delete();
            validation_exception('code', trans('user.blocked'));
        }
        $social->updateCredentials($wUser);

        return $social->user;
    }
}
