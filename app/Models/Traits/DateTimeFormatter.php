<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models\Traits;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 默认日期格式
 *
 * @mixin Model
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
trait DateTimeFormatter
{
    /**
     * 为数组 / JSON 序列化准备日期。
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format($this->getDateFormat());
    }
}
