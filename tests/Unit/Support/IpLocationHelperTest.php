<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

#[Group('support')]
class IpLocationHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.librespeed.key', 'test-key');
        Http::preventStrayRequests();
    }

    #[Test]
    #[TestDox('默认使用 Ip2Region 查询')]
    public function it_uses_ip2region_by_default(): void
    {
        $this->assertSame('回环地址', ip_location('127.0.0.1'));

        Http::assertNothingSent();
    }

    #[Test]
    #[TestDox('使用 LibreSpeed 查询并格式化归属地')]
    public function it_gets_location_from_librespeed(): void
    {
        Http::fake([
            'https://ip.librespeed.com/s/*' => Http::response([
                [
                    'country' => '中国',
                    'region' => '河南',
                    'city' => '焦作',
                    'isp' => 'chinaunicom.cn',
                ],
            ]),
        ]);

        $this->assertSame('中国河南焦作【chinaunicom.cn】', ip_location('218.28.54.163', 'librespeed'));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://ip.librespeed.com/s/?key=test-key&ip=218.28.54.163');
    }

    #[Test]
    #[TestDox('LibreSpeed 查询忽略重复及空字段')]
    public function it_ignores_duplicate_and_empty_location_fields(): void
    {
        Http::fake([
            'https://ip.librespeed.com/s/*' => Http::response([
                [
                    'country' => '中国',
                    'region' => '中国',
                    'city' => '0',
                    'isp' => '0',
                ],
            ]),
        ]);

        $this->assertSame('中国', ip_location('218.28.54.163', 'librespeed'));
    }

    #[Test]
    #[TestDox('LibreSpeed 请求失败时返回 null')]
    public function it_returns_null_when_librespeed_request_fails(): void
    {
        Http::fake([
            'https://ip.librespeed.com/s/*' => Http::response(status: 500),
        ]);

        $this->assertNull(ip_location('218.28.54.163', 'librespeed'));
    }

    #[Test]
    #[TestDox('LibreSpeed 连接异常时返回 null')]
    public function it_returns_null_when_librespeed_connection_fails(): void
    {
        Http::fake([
            'https://ip.librespeed.com/s/*' => Http::failedConnection(),
        ]);

        $this->assertNull(ip_location('218.28.54.163', 'librespeed'));
    }

    #[Test]
    #[TestDox('LibreSpeed 响应格式无效时返回 null')]
    public function it_returns_null_for_invalid_librespeed_response(): void
    {
        Http::fake([
            'https://ip.librespeed.com/s/*' => Http::response(['invalid' => true]),
        ]);

        $this->assertNull(ip_location('218.28.54.163', 'librespeed'));
    }

    #[Test]
    #[TestDox('未配置 LibreSpeed 密钥时不发送请求')]
    public function it_does_not_request_librespeed_without_api_key(): void
    {
        config()->set('services.librespeed.key', null);

        $this->assertNull(ip_location('218.28.54.163', 'librespeed'));

        Http::assertNothingSent();
    }
}
