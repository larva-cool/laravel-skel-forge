---
name: Test Generator
description: 按照项目规则生成高质量的测试代码，包括单元测试、功能测试等。
---

# Test Generator

## Description
按照项目规则生成高质量的测试代码，包括单元测试、功能测试等。

## When to use
- 需要为新创建的类生成测试代码时
- 需要为现有类补充测试覆盖时
- 需要按照项目规则规范测试代码时
- 需要确保测试覆盖所有核心功能和边界情况时

## Instructions
1. **测试环境准备**
   - 使用 RefreshDatabase 特性确保测试环境的数据库状态在每次测试前得到重置
   - 优先采用真实数据库交互而非模拟对象(Mocks)进行测试
   - 确保测试能够独立运行且不依赖外部资源或其他测试的执行顺序

2. **测试代码结构**
   - 为每个测试类使用 #[CoversClass] 属性指定测试覆盖的类
   - 为每个测试方法使用 #[Test] 属性标记
   - 为每个测试方法使用 #[TestDox] 属性添加描述
   - 编写清晰的测试方法命名，格式遵循 "test[方法名][场景][预期结果]"

3. **测试覆盖范围**
   - 确保测试覆盖类的所有核心功能
   - 测试各种边界情况和异常情况
   - 测试方法的输入输出
   - 测试类的属性和方法

4. **测试数据准备**
   - 在必要时创建真实的测试数据而非使用模拟数据
   - 在 setUp() 方法中准备测试环境和测试数据
   - 在 tearDown() 方法中清理测试环境

5. **断言使用**
   - 为每个测试方法添加适当的断言以验证功能正确性
   - 使用 PHPUnit 提供的各种断言方法
   - 确保断言的准确性和完整性

6. **代码规范**
   - 保持测试代码的可读性和可维护性
   - 遵循项目的测试编码规范
   - 使用清晰的注释和描述

## Output Format
```php
<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace Tests\Unit\[Namespace];

use App\[Namespace]\[Class];
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * [类名]测试
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[CoversClass([Class]::class)]
class [Class]Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 准备测试数据
    }

    /**
     * 测试[方法名]
     */
    #[Test]
    #[TestDox('测试[方法名]')]
    public function test_[method_name]()
    {
        // 测试代码
        // 断言
    }
}
```

## Examples

### 模型测试示例
```php
<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户模型测试
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[CoversClass(User::class)]
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试用户创建
     */
    #[Test]
    #[TestDox('测试用户创建')]
    public function test_user_creation()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
    }
}
```

### 服务测试示例
```php
<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UserService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * 用户服务测试
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
#[CoversClass(UserService::class)]
class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试获取用户信息
     */
    #[Test]
    #[TestDox('测试获取用户信息')]
    public function test_get_user_info()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $userService = new UserService();
        $userInfo = $userService->getUserInfo($user->id);

        $this->assertEquals($user->id, $userInfo->id);
        $this->assertEquals($user->name, $userInfo->name);
        $this->assertEquals($user->email, $userInfo->email);
    }
}
```
