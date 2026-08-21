<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Model 基类单元测试
 */
#[CoversClass(Model::class)]
#[Group('models')]
class ModelTest extends TestCase
{
    #[Test]
    #[TestDox('继承自 Illuminate Eloquent Model')]
    public function extends_eloquent_model(): void
    {
        $this->assertTrue(is_subclass_of(Model::class, EloquentModel::class));
    }

    #[Test]
    #[TestDox('使用 DateTimeFormatter trait')]
    public function uses_date_time_formatter_trait(): void
    {
        $this->assertContains(
            \App\Models\Traits\DateTimeFormatter::class,
            class_uses(Model::class)
        );
    }
}
