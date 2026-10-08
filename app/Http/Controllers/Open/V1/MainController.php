<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Open\V1;

use App\Http\Controllers\Controller;

/**
 * 默认控制器（Open API V1）
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class MainController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('abilities:open');
    }

    /**
     * 默认接口（Open API V1）
     */
    public function index()
    {
        return ['hello' => 'world'];
    }
}
