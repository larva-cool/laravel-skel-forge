<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\ListAttachments;
use App\Enums\AdminStatus;
use App\Enums\AttachmentType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 附件列表查询工具测试
 */
#[CoversClass(ListAttachments::class)]
#[Group('ai')]
#[Group('attachment')]
class ListAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListAttachments)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 attachments.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListAttachments)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页附件数据')]
    public function handle_returns_paginated_attachments(): void
    {
        $admin = $this->createAdminWithPermissions(['attachments.index']);
        Attachment::factory()->create(['name' => '单元测试附件', 'original_name' => '单元测试原始文件.jpg']);

        $result = (new ListAttachments)->forAdmin($admin)->handle(new Request(['keyword' => '单元测试附件']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertArrayHasKey('size_text', $payload['data'][0]);
        $this->assertArrayHasKey('type_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('type 与 extension 组合筛选仅返回匹配附件')]
    public function handle_filters_by_type_and_extension(): void
    {
        $admin = $this->createAdminWithPermissions(['attachments.index']);
        Attachment::factory()->create([
            'name' => '单元测试图片',
            'type' => AttachmentType::IMAGE,
            'extension' => 'jpg',
        ]);
        Attachment::factory()->create([
            'name' => '单元测试文档',
            'type' => AttachmentType::DOCUMENT,
            'extension' => 'pdf',
        ]);

        $result = (new ListAttachments)->forAdmin($admin)->handle(new Request([
            'keyword' => '单元测试',
            'type' => 'image',
            'extension' => 'jpg',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('image', $payload['data'][0]['type']);
    }
}
