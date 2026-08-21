<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * 积分不足异常
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class InsufficientPointsException extends Exception
{
    /**
     * 构造函数
     *
     * @param  string  $message  异常消息
     * @param  int  $code  异常代码
     * @param  \Throwable|null  $previous  前一个异常
     */
    public function __construct(string $message = 'Insufficient points', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
