<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * 默认控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class MainController extends Controller
{
    /**
     * 显示首页视图。
     *
     * @return View|Factory
     */
    public function index()
    {
        return view('main.index');
    }

    /**
     * 显示重定向页面。
     *
     * @param  Request  $request  HTTP 请求实例，包含重定向 URL
     * @return View|Factory
     */
    public function redirect(Request $request)
    {
        return view('main.redirect', ['url' => $request->query('url')]);
    }
}
