<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MainController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * MainController 功能测试
 */
#[CoversClass(MainController::class)]
#[Group('controllers')]
class MainControllerTest extends TestCase
{
    /**
     * 测试首页路由返回成功
     */
    #[Test]
    #[TestDox('首页路由返回 200 并渲染 main.index 视图')]
    public function index_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('main.index');
    }

    /**
     * 测试重定向页面带 url 参数
     */
    #[Test]
    #[TestDox('重定向页面带 url 参数返回 200 并渲染 main.redirect 视图')]
    public function redirect_returns_successful_response_with_url(): void
    {
        $url = 'https://example.com';

        $response = $this->get('/redirect?url='.urlencode($url));

        $response->assertOk();
        $response->assertViewIs('main.redirect');
        $response->assertViewHas('url', $url);
    }

    /**
     * 测试重定向页面无 url 参数时 url 为 null
     */
    #[Test]
    #[TestDox('重定向页面无 url 参数时 url 为 null')]
    public function redirect_returns_null_url_when_not_provided(): void
    {
        $response = $this->get('/redirect');

        $response->assertOk();
        $response->assertViewHas('url', null);
    }
}
