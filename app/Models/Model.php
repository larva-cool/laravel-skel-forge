<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\DateTimeFormatter;

/**
 * 模型基类
 *
 * 所有自定义模型应继承此类，自动包含 DateTimeFormatter trait。
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class Model extends \Illuminate\Database\Eloquent\Model
{
    use DateTimeFormatter;
}
