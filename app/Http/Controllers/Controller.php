<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * 控制器基类
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
