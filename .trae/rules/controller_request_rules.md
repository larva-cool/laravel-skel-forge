# HTTP 控制器与 Request 职责划分规范

## 核心原则

**禁止在 HTTP 控制器中编写赋值逻辑**，所有从请求中提取参数、组装数据模型、默认值填充、类型转换等赋值操作都必须在 `FormRequest` 中完成。

## 职责划分

控制器只负责：调用 Request 获取数据 → 调用 Service/Action 处理业务 → 返回响应

### 设计好处

- 控制器保持精简，职责单一（编排流程而非处理数据）
- Request 可独立单元测试，无需通过 HTTP 调用即可验证数据组装逻辑
- 数据组装规则集中管理，便于维护和复用
- 验证规则（`rules()`）与赋值规则（`validated()` / 自定义方法）放在一起，逻辑内聚

## 示例

```php
// ✅ 正确：在 Request 中完成赋值
class CreateUserRequest extends FormRequest
{

   /**
   * 准备验证数据
   */
  protected function prepareForValidation(): void
  {
      $this->merge([
          'user_id' => $this->user()->id,
          'country' => $this->post('country') ?? 'CN',
      ]);
  }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'country' => ['required', 'string'],
            'name'  => ['required', 'string', 'max:50'],
            'email' => ['required', 'email'],
            'age'   => ['nullable', 'integer', 'min:0'],
        ];
    }
}

class UserController extends Controller
{
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());
        return UserResource::make($user)->response();
    }
}

// ❌ 错误：控制器中直接做赋值
class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = [
            'name'       => $request->input('name'),
            'email'      => $request->input('email'),
            'age'        => $request->integer('age') ?? 0,
            'status'     => UserStatus::Active,
            'created_by' => $request->user()?->id,
        ];
        $user = $this->userService->create($data);
        return UserResource::make($user)->response();
    }
}
```

## 特殊场景

- 列表筛选、分页参数组装等，同样应在对应的 `IndexXxxRequest` / `SearchXxxRequest` 中提供 `filters()` / `pageParams()` 等方法，控制器不直接拼装查询条件
- **例外**：简单的查询接口、简单的数据返回（如裸 `Request` + `repository_class()` 的组合、单字段直传等）可以直接处理，无需附加表单或自定义方法，但应附以注释说明原因
- **核心范围**：`@define`原则：**针对**需要表单验证（`FormRequest`）的接口必须严格遵守以上规则
