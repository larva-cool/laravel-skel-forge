<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\SocialProvider;
use App\Models\System\Social;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * 苹果账号登录请求
 *
 * @property string $user_id 唯一标识（永久不变，用户级唯一）
 * @property string $authorizationCode 苹果账号登录凭证
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AppleLoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string'],
            'authorizationCode' => ['required', 'string'],
            'identityToken' => ['required', 'string'],
            'fullName' => ['nullable', 'array'],
            'email' => ['nullable', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?User
    {
        $response = Http::post('https://appleid.apple.com/auth/token', [
            'client_id' => config('services.apple.client_id'),
            'client_secret' => config('services.apple.client_secret'),
            'code' => $this->authorizationCode,
            'grant_type' => 'authorization_code',
        ])->json();
        // 检查ping是否成功
        if (! isset($response['id_token'])) {
            validation_exception('authorizationCode', trans('auth.invalid_apple_code'));
        }
        $social = Social::query()->with('user')
            ->ofUserType(User::class)
            ->ofOpenid(SocialProvider::APPLE, $this->user_id)
            ->first();

        if (! $social || ! $social->user) {
            validation_exception('phone', trans('auth.account_does_not_exist'));
        }
        if ($social->user->stats->isFrozen()) {// 禁止掉的用户不允许登录
            $social->user->tokens()->delete();
            validation_exception('account', trans('user.blocked'));
        }

        return $social->user;
    }
}
