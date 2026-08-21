<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

/**
 * 后台控制器基类
 *
 * 遵循 RESTful 规范，直接返回数据，
 * 业务错误通过 abort() 抛出对应 HTTP 状态码。
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
abstract class Controller extends \App\Http\Controllers\Controller {}
