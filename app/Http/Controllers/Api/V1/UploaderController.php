<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

/**
 * 上传控制器
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class UploaderController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
}
