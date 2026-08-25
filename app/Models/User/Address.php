<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\User;

use App\Models\Model;
use App\Models\Traits\HasUser;
use App\Policies\AddressPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * 收货地址
 *
 * @property int $id 地址ID
 * @property int $user_id 用户ID
 * @property string $name 收货人
 * @property string $country ISO 3166 国家码
 * @property string $province 省
 * @property string $city 市
 * @property string $district 县区
 * @property string $address 详细地址
 * @property string $zipcode 邮编
 * @property string $phone 手机
 * @property bool $is_default 是否默认
 * @property-read string $full_address 长地址
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
#[Table('addresses')]
#[Fillable([
    'user_id', 'name', 'country', 'province', 'city', 'district', 'address', 'zipcode', 'phone',
    'is_default',
])]
#[Hidden(['user_id'])]
#[UsePolicy(AddressPolicy::class)]
class Address extends Model
{
    use HasUser;
    use SoftDeletes;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'country' => 'CN',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'name' => 'string',
            'country' => 'string',
            'province' => 'string',
            'city' => 'string',
            'district' => 'string',
            'address' => 'string',
            'zipcode' => 'string',
            'phone' => 'string',
            'is_default' => 'bool',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * 获取长地址
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, $attributes) => $attributes['province'].$attributes['city'].$attributes['district'].$attributes['address']
        )->shouldCache();
    }

    /**
     * 将当前地址设为默认
     */
    public function markDefault(): bool
    {
        static::query()->where('user_id', $this->user_id)->update(['is_default' => false]);

        return $this->updateQuietly(['is_default' => true]);
    }

    /**
     * 获取默认地址
     */
    public static function getDefaultAddress(int|string $userId): ?array
    {
        return static::query()->where('user_id', $userId)->where('is_default', true)->first()?->toArray();
    }
}
