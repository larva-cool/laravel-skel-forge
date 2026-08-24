<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Web;

use App\Ai\Tools\Web\WebFetch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionMethod;
use Tests\TestCase;

/**
 * WebFetch SSRF 防护单元测试
 */
#[CoversClass(WebFetch::class)]
#[Group('ai')]
#[Group('security')]
class WebFetchTest extends TestCase
{
    private WebFetch $tool;

    private ReflectionMethod $isValidUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new WebFetch;
        $this->isValidUrl = new ReflectionMethod(WebFetch::class, 'isValidUrl');
    }

    #[Test]
    #[TestDox('公网 https URL 校验通过')]
    public function public_https_url_passes(): void
    {
        $this->assertTrue($this->isValidUrl->invoke($this->tool, 'https://example.com/page'));
    }

    #[Test]
    #[TestDox('公网 http URL 校验通过')]
    public function public_http_url_passes(): void
    {
        $this->assertTrue($this->isValidUrl->invoke($this->tool, 'http://example.com/'));
    }

    #[Test]
    #[TestDox('ftp/非白名单协议被拒绝')]
    #[DataProvider('blockedSchemeProvider')]
    public function non_http_scheme_rejected(string $url): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, $url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedSchemeProvider(): array
    {
        return [
            'ftp' => ['ftp://example.com/file'],
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://127.0.0.1:25/'],
            'dict' => ['dict://127.0.0.1:11211/'],
            'javascript' => ['javascript:alert(1)'],
            'no-scheme' => ['example.com'],
        ];
    }

    #[Test]
    #[TestDox('127.0.0.1 等环回地址被拒绝')]
    #[DataProvider('loopbackProvider')]
    public function loopback_rejected(string $url): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, $url), "URL 应被拦截: {$url}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function loopbackProvider(): array
    {
        return [
            '127.0.0.1' => ['http://127.0.0.1/'],
            '127.255.255.255' => ['https://127.255.255.255/'],
            'localhost' => ['http://localhost/admin'],
            '[::1]' => ['http://[::1]/'],
            '0.0.0.0' => ['http://0.0.0.0/'],
        ];
    }

    #[Test]
    #[TestDox('10/8、172.16/12、192.168/16 内网地址被拒绝')]
    #[DataProvider('privateNetworkProvider')]
    public function private_network_rejected(string $url): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, $url), "URL 应被拦截: {$url}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privateNetworkProvider(): array
    {
        return [
            '10.0.0.1' => ['http://10.0.0.1/'],
            '10.255.255.255' => ['https://10.255.255.255/'],
            '172.16.0.1' => ['http://172.16.0.1/'],
            '172.31.255.255' => ['https://172.31.255.255/'],
            '192.168.0.1' => ['http://192.168.0.1/'],
            '192.168.255.255' => ['https://192.168.255.255/'],
        ];
    }

    #[Test]
    #[TestDox('169.254.169.254 链路本地 / 云元数据地址被拒绝')]
    #[DataProvider('linkLocalProvider')]
    public function link_local_and_metadata_rejected(string $url): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, $url), "URL 应被拦截: {$url}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function linkLocalProvider(): array
    {
        return [
            'aws-metadata-ip' => ['http://169.254.169.254/latest/meta-data/'],
            'tencent-metadata' => ['http://169.254.0.23/latest/meta-data/'],
            'aliyun-metadata' => ['http://100.100.100.200/latest/meta-data/'],
            'volc-metadata' => ['http://100.96.0.96/volcstack/latest/meta-data/'],
            'gcp-metadata-host' => ['http://metadata.google.internal/computeMetadata/v1/'],
            'tencent-metadata-host' => ['http://metadata.tencentyun.com/latest/meta-data/'],
            'link-local-host' => ['http://metadata/'],
        ];
    }

    #[Test]
    #[TestDox('100.64.0.0/10 CGNAT 段被拒绝')]
    public function cgnat_range_rejected(): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'http://100.64.0.1/'));
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'http://100.127.255.255/'));
    }

    #[Test]
    #[TestDox('URL 携带 user:pass@ 凭据被拒绝')]
    public function userinfo_rejected(): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'http://admin:password@example.com/'));
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'http://root@127.0.0.1/'));
    }

    #[Test]
    #[TestDox('.local / .internal mDNS / 内部后缀被拒绝')]
    public function internal_suffix_rejected(): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'http://myhost.local/'));
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'http://db.internal/'));
    }

    #[Test]
    #[TestDox('空串 / 超长 / 格式错误 URL 被拒绝')]
    public function malformed_url_rejected(): void
    {
        $this->assertFalse($this->isValidUrl->invoke($this->tool, ''));
        $this->assertFalse($this->isValidUrl->invoke($this->tool, 'not a url'));
    }

    #[Test]
    #[TestDox('DNS 解析失败的主机名被拒绝')]
    public function unresolvable_host_rejected(): void
    {
        $this->assertFalse(
            $this->isValidUrl->invoke($this->tool, 'http://this-host-definitely-does-not-exist-xyz123.invalid/')
        );
    }
}
