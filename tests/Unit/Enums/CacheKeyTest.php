<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CacheKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * CacheKey 单元测试
 */
#[CoversClass(CacheKey::class)]
#[Group('enums')]
class CacheKeyTest extends TestCase
{
    #[Test]
    #[TestDox('SETTINGS 常量值正确')]
    public function settings_constant(): void
    {
        $this->assertSame('system:settings', CacheKey::SETTINGS);
    }

    #[Test]
    #[TestDox('DICT_TYPE 常量值正确')]
    public function dict_type_constant(): void
    {
        $this->assertSame('system:dicts:%s', CacheKey::DICT_TYPE);
    }

    #[Test]
    #[TestDox('AREA_TREE 常量值正确')]
    public function area_tree_constant(): void
    {
        $this->assertSame('system:areas:%s', CacheKey::AREA_TREE);
    }

    #[Test]
    #[TestDox('key 方法用参数替换占位符')]
    public function key_method_replaces_placeholders(): void
    {
        $this->assertSame('system:dicts:user', CacheKey::key(CacheKey::DICT_TYPE, 'user'));
        $this->assertSame('system:areas:tree', CacheKey::key(CacheKey::AREA_TREE, 'tree'));
    }

    #[Test]
    #[TestDox('key 方法支持多个参数')]
    public function key_method_supports_multiple_args(): void
    {
        $this->assertSame('prefix:a:b', CacheKey::key('prefix:%s:%s', 'a', 'b'));
    }

    #[Test]
    #[TestDox('key 方法无参数时原样返回')]
    public function key_method_without_args(): void
    {
        $this->assertSame('system:settings', CacheKey::key(CacheKey::SETTINGS));
    }
}
