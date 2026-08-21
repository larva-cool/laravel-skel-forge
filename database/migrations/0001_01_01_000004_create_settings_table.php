<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

use App\Enums\SettingType;
use App\Models\System\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id()->from(10000000)->comment('配置ID');
            $table->string('name', 255)->nullable()->comment('配置名称');
            $table->string('key', 100)->index()->comment('配置键名');
            $table->text('value')->nullable()->comment('配置值');
            $table->string('cast_type', 20)->nullable()->default('string')->comment('变量类型');
            $table->string('input_type')->nullable()->default('text')->comment('输入类型');
            $table->mediumText('param')->nullable()->comment('配置参数');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，越小越靠前');
            $table->string('remark')->nullable()->comment('备注');
            $table->timestamp('updated_at')->nullable()->comment('最后更新时间');
            $table->unique(['key']);

            $table->comment('参数配置信息表');
        });

        Setting::batchSet([
            // 基本设置
            [
                'name' => '网站URL',
                'key' => 'system.url',
                'value' => 'https://www.xxx.com',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '网站URL（一般为https://www.xxx.com）。',
            ],
            [
                'name' => '移动网站URL',
                'key' => 'system.m_url',
                'value' => 'https://m.xxx.com',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 500}',
                'remark' => '移动网站URL（一般为https://m.xxx.com）。',
            ],
            [
                'name' => '网站标题',
                'key' => 'system.title',
                'value' => 'Laravel 13',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '网站标题（一般不超过80个字符）。',
            ],
            [
                'name' => '网站关键词',
                'key' => 'system.keywords',
                'value' => 'Laravel 13',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '网站关键词（一般不超过100个字符）。',
            ],
            [
                'name' => '网站描述',
                'key' => 'system.description',
                'value' => 'Laravel 13',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'textarea',
                'param' => '{"maxlength": 500}',
                'remark' => '网站描述（一般不超过200个字符）。',
            ],
            [
                'name' => 'ICP备案号',
                'key' => 'system.icp_beian',
                'value' => 'ICP备XXXX号',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '信产部备案号。',
            ],
            [
                'name' => '公安备案号',
                'key' => 'system.police_beian',
                'value' => '公安备XXXX号',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '公安部备案号。',
            ],
            [
                'name' => '服务邮箱',
                'key' => 'system.support_email',
                'value' => 'support@xxx.com',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '服务邮箱（一般为support@xxx.com）。',
            ],
            [
                'name' => '服务电话',
                'key' => 'system.support_phone',
                'value' => '13800000000',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '服务电话（一般为13800000000）。',
            ],
            [
                'name' => '法律邮箱',
                'key' => 'system.lawyer_email',
                'value' => 'lawyer@xxx.com',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'param' => '{"maxlength": 255}',
                'remark' => '法律邮箱（一般为lawyer@xxx.com）。',
            ],

            // SMS 配置
            [
                'name' => '短信验证码有效期',
                'key' => 'sms_captcha.duration',
                'value' => '10',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 60}',
                'remark' => '短信验证码有效期，单位为分钟。',
            ],
            [
                'name' => '短信验证码清理时间',
                'key' => 'sms_captcha.prunable_days',
                'value' => '10',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 30}',
                'remark' => '短信验证码清理时间，单位为天。',
            ],
            [
                'name' => '短信验证码长度',
                'key' => 'sms_captcha.length',
                'value' => '6',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'remark' => '短信验证码长度，单位为数字。',
            ],
            [
                'name' => '短信验证码等待时间',
                'key' => 'sms_captcha.wait_time',
                'value' => '60',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 60}',
                'remark' => '两次获取验证码间隔，单位为秒。',
            ],
            [
                'name' => '短信验证码测试次数',
                'key' => 'sms_captcha.test_limit',
                'value' => '3',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 1000}',
                'remark' => '验证码最大允许错误次数，超过后验证码失效。',
            ],
            [
                'name' => '短信验证码IP次数',
                'key' => 'sms_captcha.ip_count',
                'value' => '20',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 100}',
                'remark' => '每个IP地址每小时最大获取验证码次数。',
            ],
            [
                'name' => '短信验证码手机号次数',
                'key' => 'sms_captcha.phone_count',
                'value' => '10',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 1000}',
                'remark' => '每个手机号每小时最大获取验证码次数。',
            ],

            // 邮件配置
            [
                'name' => '邮件验证码有效期',
                'key' => 'email_captcha.duration',
                'value' => '10',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'remark' => '邮件验证码有效期，单位为分钟。',
            ],
            [
                'name' => '邮件验证码清理时间',
                'key' => 'email_captcha.prunable_days',
                'value' => '10',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'remark' => '邮件验证码清理时间，单位为天。',
            ],
            [
                'name' => '邮件验证码长度',
                'key' => 'email_captcha.length',
                'value' => '6',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 4, "max": 10}',
                'remark' => '邮件验证码长度，单位为数字。',
            ],
            [
                'name' => '邮件验证码等待时间',
                'key' => 'email_captcha.wait_time',
                'value' => '60',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 300}',
                'remark' => '两次获取验证码间隔，单位为秒。',
            ],
            [
                'name' => '邮件验证码测试次数',
                'key' => 'email_captcha.test_limit',
                'value' => '3',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 1000}',
                'remark' => '验证码最大允许错误次数，超过后验证码失效。',
            ],

            // 用户配置
            [
                'name' => '用户注册',
                'key' => 'user.enable_register',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法注册。',
            ],
            [
                'name' => '手机注册',
                'key' => 'user.enable_phone_register',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法通过手机号注册。',
            ],
            [
                'name' => '邮箱注册',
                'key' => 'user.enable_email_register',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法通过邮箱注册。',
            ],

            [
                'name' => '用户邀请注册',
                'key' => 'user.enable_invite_register',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，邀请注册时的邀请关系无法建立。',
            ],
            [
                'name' => '用户登录仅允许一个设备',
                'key' => 'user.only_one_device_login',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '开启后，用户在其他设备的登录将会退出。',
            ],
            [
                'name' => '用户名变更次数',
                'key' => 'user.username_change',
                'value' => 3,
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 0, "max": 1000}',
                'remark' => '用户名最大允许修改的次数，0表示不允许修改。',
            ],
            [
                'name' => '用户注册节流',
                'key' => 'user.register_throttle',
                'value' => '10,3',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'remark' => '1分钟5次，则输入5,1',
            ],
            [
                'name' => '用户登录节流',
                'key' => 'user.login_throttle',
                'value' => '10,3',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'string',
                'remark' => '1分钟5次，则输入5,1',
            ],
            [
                'name' => '用户token有效期',
                'key' => 'user.token_expiration',
                'value' => 525600,
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'remark' => '令牌有效期，单位为分钟。',
            ],
            [
                'name' => '允许手机号登录',
                'key' => 'user.enable_phone_login',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法通过手机号登录。',
            ],
            [
                'name' => '允许密码登录',
                'key' => 'user.enable_password_login',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法通过密码登录。',
            ],
            [
                'name' => '允许微信登录',
                'key' => 'user.enable_wechat_login',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法通过微信登录。',
            ],
            [
                'name' => '允许Apple登录',
                'key' => 'user.enable_apple_login',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '关闭后，用户将无法通过苹果登录。',
            ],
            [
                'name' => '用户积分有效期',
                'key' => 'user.point_expiration',
                'value' => 365,
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 3650}',
                'remark' => '积分过期后，自动回收',
            ],
            [
                'name' => '默认头像',
                'key' => 'user.default_avatar',
                'value' => 'img/avatar.png',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'image',
                'remark' => '',
            ],
            // 上传配置
            [
                'name' => '上传存储',
                'key' => 'upload.storage',
                'value' => config('filesystems.default'),
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'remote_select',
                'param' => '{"url": "/admin/attachments/disks"}',
                'remark' => '上传文件默认驱动',
            ],
            [
                'name' => '上传允许文件扩展名',
                'key' => 'upload.allow_extension',
                'value' => 'jpg,png,gif,jpeg,doc,docx,md,txt,pdf,7z,zip,rar,xls,ppt,pptx,wps,mp3,mp4,gz,tar,bz,psd,csv',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'textarea',
                'param' => '',
                'remark' => '允许上传的文件类型，多个类型用逗号分隔。',
            ],
            [
                'name' => '上传允许图片扩展名',
                'key' => 'upload.allow_image_extension',
                'value' => 'jpg,png,gif,jpeg',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'textarea',
                'param' => '',
                'remark' => '允许上传的图片类型，多个类型用逗号分隔。',
            ],
            [
                'name' => '上传允许视频扩展名',
                'key' => 'upload.allow_video_extension',
                'value' => 'mp4,mov,flv,mkv,rm,rmvb,3gp,m4v,mpg,wmv,avi',
                'cast_type' => SettingType::CAST_TYPE_STRING,
                'input_type' => 'textarea',
                'remark' => '允许上传的视频类型，多个类型用逗号分隔。',
            ],
            [
                'name' => '自动优化上传的图片',
                'key' => 'upload.image_optimize',
                'value' => 1,
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => 'bool',
                'remark' => '自动优化上传的图片。',
            ],
            [
                'name' => '临时访问地址有效期',
                'key' => 'upload.temporary_url_minutes',
                'value' => 10,
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 1440}',
                'remark' => '私有磁盘临时签名访问地址的有效期（分钟）。',
            ],
            [
                'name' => '孤儿附件清理天数',
                'key' => 'upload.prune_orphan_days',
                'value' => 0,
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 0, "max": 3650}',
                'remark' => '孤儿附件自动清理天数，0 表示不清理。',
            ],

            // 调度配置
            [
                'name' => '记录调度任务日志',
                'key' => 'schedule.log_enabled',
                'value' => '1',
                'cast_type' => SettingType::CAST_TYPE_BOOL,
                'input_type' => SettingType::CAST_TYPE_BOOL,
                'remark' => '是否记录调度任务的执行日志。',
            ],
            [
                'name' => '调度日志清理时间',
                'key' => 'schedule.log_prunable_days',
                'value' => '30',
                'cast_type' => SettingType::CAST_TYPE_INT,
                'input_type' => SettingType::CAST_TYPE_INT,
                'param' => '{"min": 1, "max": 365}',
                'remark' => '调度任务日志保留天数，超期由 model:prune 命令清理。',
                'sort' => 1,
            ],

        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
