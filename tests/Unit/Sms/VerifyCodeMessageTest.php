<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Sms;

use App\Sms\VerifyCodeMessage;
use Overtrue\EasySms\Contracts\GatewayInterface;
use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\Support\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * VerifyCodeMessage 单元测试
 */
#[CoversClass(VerifyCodeMessage::class)]
#[Group('sms')]
class VerifyCodeMessageTest extends TestCase
{
    private function createGateway(string $name): GatewayInterface
    {
        return new class($name) implements GatewayInterface
        {
            public function __construct(private string $name) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
            {
                return [];
            }

            public function getConfig(): Config
            {
                return new Config([]);
            }

            public function setConfig(Config $config): static
            {
                return $this;
            }
        };
    }

    #[Test]
    #[TestDox('默认网关为 volcengine')]
    public function default_gateway_is_volcengine(): void
    {
        $message = new VerifyCodeMessage(['code' => '123456', 'duration' => 10, 'scene' => 'default']);

        $reflection = new \ReflectionProperty($message, 'gateways');
        $reflection->setAccessible(true);

        $this->assertSame(['volcengine'], $reflection->getValue($message));
    }

    #[Test]
    #[TestDox('getData 返回 aliyun 格式参数')]
    public function get_data_returns_aliyun_format(): void
    {
        $message = new VerifyCodeMessage(['code' => '123456', 'duration' => 10, 'scene' => 'default']);
        $gateway = $this->createGateway('aliyun');

        $data = $message->getData($gateway);

        $this->assertSame(['code' => '123456'], $data);
    }

    #[Test]
    #[TestDox('getData 返回 volcengine 格式参数')]
    public function get_data_returns_volcengine_format(): void
    {
        $message = new VerifyCodeMessage(['code' => '654321', 'duration' => 10, 'scene' => 'default']);
        $gateway = $this->createGateway('volcengine');

        $data = $message->getData($gateway);

        $this->assertSame(['code' => '654321'], $data);
    }

    #[Test]
    #[TestDox('getContent 返回 aliyun 内容')]
    public function get_content_returns_aliyun_content(): void
    {
        $message = new VerifyCodeMessage(['code' => '123456', 'duration' => 10, 'scene' => 'default']);
        $gateway = $this->createGateway('aliyun');

        $content = $message->getContent($gateway);

        $this->assertStringContainsString('123456', $content);
        $this->assertStringContainsString('验证码', $content);
    }

    #[Test]
    #[TestDox('getContent 返回 volcengine 内容')]
    public function get_content_returns_volcengine_content(): void
    {
        $message = new VerifyCodeMessage(['code' => '654321', 'duration' => 10, 'scene' => 'default']);
        $gateway = $this->createGateway('volcengine');

        $content = $message->getContent($gateway);

        $this->assertStringContainsString('654321', $content);
        $this->assertStringContainsString('验证码', $content);
    }

    #[Test]
    #[TestDox('getContent 网关为 null 时返回空字符串')]
    public function get_content_returns_empty_when_gateway_null(): void
    {
        $message = new VerifyCodeMessage(['code' => '123456', 'duration' => 10, 'scene' => 'default']);

        $content = $message->getContent(null);

        $this->assertSame('', $content);
    }
}
