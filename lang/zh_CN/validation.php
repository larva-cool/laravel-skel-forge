<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => '您必须接受:attribute。',
    'accepted_if' => '当:other为:value时，必须接受:attribute。',
    'active_url' => ':attribute不是一个有效的网址。',
    'after' => ':attribute必须要晚于:date。',
    'after_or_equal' => ':attribute必须要等于:date或更晚。',
    'alpha' => ':attribute只能由字母组成。',
    'alpha_dash' => ':attribute只能由字母、数字、短划线(-)和下划线(_)组成。',
    'alpha_num' => ':attribute只能由字母和数字组成。',
    'any_of' => ':attribute字段无效。',
    'array' => ':attribute必须是一个数组。',
    'ascii' => ':attribute必须仅包含单字节字母数字字符和符号。',
    'base64' => ':attribute 必须是有效的 Base64 字符串。',
    'before' => ':attribute必须要早于:date。',
    'before_or_equal' => ':attribute必须要等于:date或更早。',
    'between' => [
        'array' => ':attribute必须只有:min - :max个单元。',
        'file' => ':attribute必须介于:min - :maxKB之间。',
        'numeric' => ':attribute必须介于:min - :max之间。',
        'string' => ':attribute必须介于:min - :max个字符之间。',
    ],
    'boolean' => ':attribute必须为布尔值。',
    'can' => ':attribute字段包含未经授权的值。',
    'confirmed' => ':attribute两次输入不一致。',
    'contains' => ':attribute字段缺少必填值。',
    'current_password' => '密码错误。',
    'date' => ':attribute不是一个有效的日期。',
    'date_equals' => ':attribute必须要等于:date。',
    'date_format' => ':attribute的格式必须为:format。',
    'decimal' => ':attribute必须有:decimal位小数。',
    'declined' => ':attribute必须是拒绝的。',
    'declined_if' => '当:other为:value时字段:attribute必须是拒绝的。',
    'different' => ':attribute和:other必须不同。',
    'digits' => ':attribute必须是:digits位数字。',
    'digits_between' => ':attribute必须是介于:min和:max位的数字。',
    'dimensions' => ':attribute图片尺寸不正确。',
    'distinct' => ':attribute已经存在。',
    'doesnt_contain' => ':attribute不能包含以下任意内容：:values。',
    'doesnt_end_with' => ':attribute不能以以下之一结尾::values。',
    'doesnt_start_with' => ':attribute不能以下列之一开头::values。',
    'email' => ':attribute不是一个合法的邮箱。',
    'encoding' => ':attribute 字段必须使用 :encoding 编码。',
    'ends_with' => ':attribute 必须以以下之一结尾：:values。',
    'enum' => ':attribute值不正确。',
    'exists' => '所选的:attribute无效。',
    'extensions' => ':attribute 必须具有以下扩展名之一：:values。',
    'file' => ':attribute 必须是一个文件。',
    'filled' => ':attribute 字段必须有值。',
    'gt' => [
        'array' => ':attribute必须多于:value个元素。',
        'file' => ':attribute必须大于:valueKB。',
        'numeric' => ':attribute必须大于:value。',
        'string' => ':attribute必须多于:value个字符。',
    ],
    'gte' => [
        'array' => ':attribute必须多于或等于:value个元素。',
        'file' => ':attribute必须大于或等于:valueKB。',
        'numeric' => ':attribute必须大于或等于:value。',
        'string' => ':attribute必须多于或等于:value个字符。',
    ],
    'hex_color' => ':attribute字段必须是有效的十六进制颜色。',
    'image' => ':attribute必须是图片。',
    'in' => '已选的属性:attribute无效。',
    'in_array' => ':attribute必须在:other中。',
    'in_array_keys' => ':attribute必须至少包含以下任意一个键：:values。',
    'integer' => ':attribute必须是整数。',
    'ip' => ':attribute必须是有效的 IP 地址。',
    'ipv4' => ':attribute必须是有效的 IPv4 地址。',
    'ipv6' => ':attribute必须是有效的 IPv6 地址。',
    'json' => ':attribute必须是正确的 JSON 格式。',
    'list' => ':attribute字段必须是一个列表。',
    'lowercase' => ':attribute必须小写。',
    'lt' => [
        'array' => ':attribute必须少于:value个元素。',
        'file' => ':attribute必须小于:valueKB。',
        'numeric' => ':attribute必须小于:value。',
        'string' => ':attribute必须少于:value个字符。',
    ],
    'lte' => [
        'array' => ':attribute必须少于或等于:value个元素。',
        'file' => ':attribute必须小于或等于:valueKB。',
        'numeric' => ':attribute必须小于或等于:value。',
        'string' => ':attribute必须少于或等于:value个字符。',
    ],
    'mac_address' => ':attribute必须是一个有效的 MAC 地址。',
    'max' => [
        'array' => ':attribute最多只有:max个单元。',
        'file' => ':attribute不能大于:maxKB。',
        'numeric' => ':attribute不能大于:max。',
        'string' => ':attribute不能大于:max个字符。',
    ],
    'max_digits' => ':attribute不能超过:max位数。',
    'mimes' => ':attribute必须是一个:values类型的文件。',
    'mimetypes' => ':attribute必须是一个:values类型的文件。',
    'min' => [
        'array' => ':attribute至少有:min个单元。',
        'file' => ':attribute大小不能小于:min KB。',
        'numeric' => ':attribute必须大于等于:min。',
        'string' => ':attribute至少为:min个字符。',
    ],
    'min_digits' => ':attribute必须至少有:min位数。',
    'missing' => '必须缺少:attribute字段。',
    'missing_if' => '当:other为:value时，必须缺少:attribute字段。',
    'missing_unless' => '必须缺少:attribute字段，除非:other为:value。',
    'missing_with' => '存在:values时，必须缺少:attribute字段。',
    'missing_with_all' => '存在:values时，必须缺少:attribute字段。',
    'multiple_of' => ':attribute必须是:value的多个值。',
    'not_in' => '已选的属性:attribute非法。',
    'not_regex' => ':attribute的格式错误。',
    'numeric' => ':attribute必须是一个数字。',
    'password' => [
        'letters' => ':attribute必须至少包含一个字母。',
        'mixed' => ':attribute必须至少包含一个大写字母和一个小写字母。',
        'numbers' => ':attribute必须至少包含一个数字。',
        'symbols' => ':attribute必须至少包含一个符号。',
        'uncompromised' => '给定的:attribute出现在已经泄漏的密码中。请选择不同的:attribute。',
    ],
    'present' => ':attribute必须存在。',
    'present_if' => '当:other等于 :value时，必须存在:attribute字段。',
    'present_unless' => '除非:other等于 :value，否则:attribute字段必须存在。',
    'present_with' => '当:values存在时，:attribute字段必须存在。',
    'present_with_all' => '当存在:values时，必须存在:attribute字段。',
    'prohibited' => ':attribute字段被禁止。',
    'prohibited_if' => '当:other为 :value时，禁止:attribute字段。',
    'prohibited_if_accepted' => ':attribute字段在:other被接受时禁止。',
    'prohibited_if_declined' => ':attribute字段在:other被拒绝时禁止。',
    'prohibited_unless' => ':attribute字段被禁止，除非:other位于:values中。',
    'prohibits' => ':attribute字段禁止出现:other。',
    'regex' => ':attribute格式不正确。',
    'required' => ':attribute不能为空。',
    'required_array_keys' => ':attribute必须至少包含指定的键：:values。',
    'required_if' => '当:other为:value时，:attribute不能为空。',
    'required_if_accepted' => '当:other存在时，:attribute不能为空。',
    'required_if_declined' => '当:other不存在时，:attribute不能为空。',
    'required_unless' => '当:other不为:values时，:attribute不能为空。',
    'required_with' => '当:values存在时，:attribute不能为空。',
    'required_with_all' => '当:values存在时，:attribute不能为空。',
    'required_without' => '当:values不存在时，:attribute不能为空。',
    'required_without_all' => '当:values都不存在时，:attribute不能为空。',
    'same' => ':attribute和:other必须相同。',
    'size' => [
        'array' => ':attribute必须为:size个单元。',
        'file' => ':attribute大小必须为:size KB。',
        'numeric' => ':attribute大小必须为:size。',
        'string' => ':attribute必须是:size个字符。',
    ],
    'starts_with' => ':attribute必须以:values为开头。',
    'string' => ':attribute必须是一个字符串。',
    'timezone' => ':attribute必须是一个合法的时区值。',
    'unique' => ':attribute已经存在。',
    'uploaded' => ':attribute上传失败。',
    'uppercase' => ':attribute必须大写',
    'url' => ':attribute格式不正确。',
    'ulid' => ':attribute必须是有效的 ULID。',
    'uuid' => ':attribute必须是有效的 UUID。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'username' => ':attribute格式不正确，请重新输入。',
    'phone' => ':attribute不正确，请重新输入。',
    'verify_code' => ':attribute不正确，请重新输入。',

    'custom' => [
        'phone' => [
            'sms_captcha_send_check' => ':attribute短信发送次数已达上限，请稍后再试。',
        ],
        'source_type' => [
            'incorrect' => '内容类型不正确。',
        ],
        'username' => [
            'change_count' => ':attribute已超过允许修改的次数。',
            'username' => ':attribute格式不正确，请重新输入。',
        ],
        'verify_code' => [
            'required' => '请输入:attribute。',
        ],
        'id_card_no' => [
            'regex' => '身份证号格式不正确',
        ],
        'credit_code' => [
            'regex' => '统一社会信用代码格式不正确',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'account' => '账号',
        'active_path' => '激活路径',
        'area_code' => '地区编码',
        'avatar' => '头像',
        'city_code' => '城市区号',
        'component' => '前端组件',
        'count' => '数量',
        'device' => '设备',
        'email' => '邮箱',
        'fixed_tab' => '固定标签页',
        'gender' => '性别',
        'icon' => '图标',
        'invite_code' => '邀请码',
        'is_enable' => '是否启用',
        'is_full_page' => '是否全屏',
        'is_hide' => '是否隐藏',
        'is_hide_tab' => '是否隐藏标签页',
        'is_iframe' => '是否内嵌',
        'keep_alive' => '页面缓存',
        'link' => '外链地址',
        'name' => '名称',
        'parent_id' => '父级ID',
        'password' => '密码',
        'path' => '路由路径',
        'permission' => '权限标识',
        'permissions' => '权限',
        'phone' => '手机',
        'redirect' => '重定向',
        'roles' => '角色',
        'show_badge' => '显示徽章',
        'show_text_badge' => '文本徽章',
        'sort' => '排序',
        'status' => '状态',
        'title' => '标题',
        'type' => '类型',
        'user_id' => '用户ID',
        'username' => '用户名',
        'verify_code' => '验证码',
        'real_name' => '真实姓名',
        'id_card_no' => '身份证号',
        'id_card_front_id' => '身份证人像面附件',
        'id_card_back_id' => '身份证国徽面附件',
        'handheld_id' => '手持身份证附件',
        'company_name' => '企业名称',
        'credit_code' => '统一社会信用代码',
        'legal_person_name' => '法人姓名',
        'legal_person_id_card_no' => '法人身份证号',
        'business_license_id' => '营业执照附件',
        'opening_permit_id' => '开户许可证附件',
        'bank_account' => '银行账户',
        'bank_name' => '开户行',
        'reason' => '驳回原因',
        'chat_reason' => '拒绝原因',
        'remark' => '驳回备注',
        'conversation_id' => '会话ID',
        'approval_id' => '审批ID',
        'approved' => '审批结果',
        'prompt' => '对话内容',
    ],

];
