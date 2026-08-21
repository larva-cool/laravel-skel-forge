<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\User;

use App\Enums\Gender;
use App\Models\System\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 用户资料
 *
 * @property int $user_id 用户ID
 * @property int $gender 性别：0保密/1男/2女
 * @property Carbon $birthday 生日
 * @property int $province_id 省 ID
 * @property int $city_id 市 ID
 * @property int $district_id 区县ID
 * @property string $website 个人网站
 * @property string $intro 个人介绍
 * @property string $bio 个性签名
 *
 * 关系对象
 * @property User $user 用户实例
 * @property Area|null $province 省实例
 * @property Area|null $city 市实例
 * @property Area|null $district 区县实例
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[Table('user_profiles', 'user_id', null, false, false)]
#[Fillable(['gender', 'birthday', 'province_id',  'city_id', 'district_id', 'website', 'intro', 'bio'])]
#[Hidden(['user_id'])]
class UserProfile extends Model
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'gender' => Gender::GENDER_UNKNOWN->value,
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
            'gender' => Gender::class,
            'birthday' => 'date:Y-m-d',
            'province_id' => 'integer',
            'city_id' => 'integer',
            'district_id' => 'integer',
            'website' => 'string',
            'intro' => 'string',
            'bio' => 'string',
        ];
    }

    /**
     * Get the user relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the province relation.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'province_id');
    }

    /**
     * Get the city relation.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'city_id');
    }

    /**
     * Get the district relation.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'district_id');
    }
}
