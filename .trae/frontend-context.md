# Frontend Architecture Context (记忆文件)

> 自动生成于代码调研阶段，供 AI/开发者快速理解前端架构。
> 前端项目路径：`/Users/xutongle/Skel/laravel-art-admin`（独立 Vue SPA，与 laravel-skel-pro 后端分离）

---

## 一、项目定位

- **架构模式**：前后端分离 SPA
- **前端**：`laravel-art-admin`（独立 Vue 3 + TS + Vite 项目，pnpm workspace，node 22.x）
- **后端**：`laravel-skel-pro`（Laravel 13，仅提供 `/admin/*` 和 `/api/*` 接口，前端通过 Vite proxy 访问）
- **开发代理**：Vite dev server 将 `/api`、`/admin` 代理到 `http://laravel-skel-pro.test`
- **路由模式**：Hash (`createWebHashHistory()`)
- **部署**：SPA 独立构建/部署；后端 `resources/` 仅保留 Blade 默认欢迎页、邮件模板、Pulse 面板、Echo 初始化

---

## 二、技术栈

| 类别 | 技术 | 版本 |
|---|---|---|
| 框架 | Vue 3 | ^3.5.41 |
| 语言 | TypeScript | ~5.6.3 |
| 构建 | Vite | ^7.3.6 |
| UI | Element Plus | ^2.14.4（按需自动导入） |
| 状态 | Pinia | ^4.0.2 + pinia-plugin-persistedstate ^4.7.1 |
| 路由 | Vue Router | ^4.6.4 |
| HTTP | Axios | ^1.19.0 |
| 图表 | ECharts | ^6.0.0（按需 Tree Shaking） |
| CSS | Tailwind CSS v4 | ^4.3.3 + SCSS |
| 图标 | @iconify/vue | ^5.0.1 |
| 富文本 | wangEditor | ^5.1.23 |
| i18n | vue-i18n | ^11.4.8 |
| WebSocket | laravel-echo | ^2.4.0（Reverb） |
| 工具 | @vueuse/core, mitt, ohash, crypto-js, nprogress, xlsx, file-saver, qrcode.vue, vue-draggable-plus, highlight.js, xgplayer | — |

工程化：ESLint 9 flat config、Prettier、Stylelint、Husky + lint-staged、Commitlint（Conventional Commits）、cz-git、unplugin-auto-import、unplugin-vue-components、unplugin-element-plus、vite-plugin-compression（gzip）、terser、vite-plugin-vue-devtools、rollup-plugin-visualizer。

路径别名：`@` → src，`@views` → src/views，`@imgs` → src/assets/images，`@icons` → src/assets/icons，`@utils` → src/utils，`@stores` → src/store，`@styles` → src/assets/styles。

---

## 三、目录结构 (src/)

```
src/
├── api/              # RESTful API（auth、system-manage、user-manage、chat）
├── assets/
│   ├── images/       # 图片（头像、登录、设置预览、SVG错误页、节日等）
│   ├── styles/       # SCSS（tailwind、reset、dark、el-ui、mixin、动画、过渡）
│   └── svg/          # SVG loading
├── components/core/  # 全局公共组件（art- 前缀，unplugin 自动按需注册）
│   ├── base/         # ArtLogo、ArtSvgIcon、ArtBackToTop
│   ├── layouts/      # 布局骨架（最复杂，settings-panel 自带 composables 体系）
│   ├── forms/        # ArtForm(配置驱动)、ArtSearchBar、ArtButtonMore/Table、ArtDragVerify、ArtExcelImport/Export、ArtWangEditor
│   ├── tables/       # ArtTable（el-table+分页+自适应）、ArtTableHeader（列设置+拖拽）
│   ├── charts/       # 8种ECharts：Line/Bar/HBar/DualBarCompare/Ring/Radar/Scatter/KLine
│   ├── cards/        # Stats/Progress/DataList/TimelineList/Image/图表卡片
│   ├── banners/      # BasicBanner、CardBanner
│   ├── views/        # ArtException、ArtResultPage、登录视图组件
│   ├── media/        # ArtCutterImg、ArtVideoPlayer
│   ├── others/       # ArtWatermark、ArtMenuRight（右键菜单）
│   ├── text-effect/  # ArtCountTo、ArtTextScroll、ArtFestivalTextScroll
│   ├── theme/        # ThemeSvg
│   └── widget/       # ArtIconButton
├── config/           # 静态配置（主题色/布局/菜单/快速入口/节日/图片）
├── directives/       # 指令：v-auth、v-roles、v-highlight、v-ripple
├── enums/            # appEnum、formEnum
├── hooks/core/       # 12个核心 Composable（useTable 约730行）
├── locales/          # vue-i18n（zh/en JSON）
├── plugins/          # echarts 按需注册
├── router/           # 路由：core(RouteRegistry/MenuProcessor/RoutePermissionValidator/RouteTransformer/ComponentLoader/IframeRouteManager)、guards(beforeEach/afterEach)、modules、routes(staticRoutes/asyncRoutes)、routesAlias
├── store/modules/    # Pinia：user、setting、menu、worktab、table（全部持久化，StorageKeyManager版本化key）
├── types/            # TS类型，全局 Api 命名空间(api.d.ts)
├── utils/
│   ├── http/         # Axios 实例（适配Laravel原生响应、401防抖、204处理、重试）
│   ├── echo/         # Laravel Echo + Reverb 单例
│   ├── socket/       # 原生 WebSocket 客户端类（心跳/指数退避重连/消息队列）
│   ├── storage/      # StorageKeyManager、StorageCompatibilityManager（迁移/损坏清理）
│   ├── table/        # LRU缓存（ohash）、响应适配器、防抖
│   ├── form/         # 响应式列跨度、校验器
│   ├── navigation/   # 路由跳转、worktab
│   ├── sys/          # mittBus事件总线、全局错误、控制台、升级检测
│   ├── ui/           # 颜色处理/主题CSS变量/动画/loading/tabs
│   └── constants/    # 外部链接
└── views/            # 业务页面
    ├── auth/login、dashboard/console、exception/403|404|500、result/success|fail、outside/Iframe、index/
    ├── system/       # admin、role、menu、setting、config、area、mail-code、phone-code、user-center
    └── user/list/    # C端用户（余额/VIP/密码/联系方式重置）
```

---

## 四、核心架构设计

### 4.1 路由
- Hash 模式；静态路由：Login、403、404、500、Outside/Iframe
- 登录后后端 `fetchGetMenuList()` 返回 `AppRouteRecord[]`，`RouteRegistry.register()` 动态 addRoute
- **双权限模式**：`VITE_ACCESS_MODE` 切换
  - 前端模式：权限查 `userStore.adminInfo.buttons`
  - 后端模式：权限查当前路由 `route.meta.auth_list`
- 守卫流程（src/router/guards/beforeEach.ts）：
  1. 检查登录态 → 未登录跳 Login 带 redirect
  2. 防并发/防死循环（routeInitInProgress/routeInitFailed）
  3. 首次进入 fetchGetUserInfo + fetchGetMenuList
  4. 注册动态路由 → 保存 menuStore → 保存 iframe → 校验 worktab
  5. RoutePermissionValidator.validatePath 校验权限
  6. 有权限 replace 到目标路径；无权限跳 homePath
  7. 根路径 `/` 重定向到 homePath
  8. 401 由 axios 拦截器防抖自动 logOut

### 4.2 状态管理（Pinia）
全部使用 Composition API（defineStore + ref/computed），pinia-plugin-persistedstate 持久化到 localStorage，key 通过 StorageKeyManager 版本化：

| Store | key | 核心状态 |
|---|---|---|
| user | userStore | accessToken、adminInfo(含buttons)、isLogin、isLock、lockPassword、language、searchHistory |
| setting | settingStore | 29项UI设置(菜单类型/宽度/主题/主色/圆角/容器/各开关/过渡) |
| menu | menuStore | menuList、折叠/激活、homePath、动态路由 remove fns |
| worktab | worktabStore | opened 标签、keepAliveExclude、当前标签 |
| table | tableStore | 边框/斑马纹/尺寸/全屏/表头背景 |

### 4.3 HTTP 层（适配 Laravel 原生响应，不使用统一 code/msg/data 包装）
- 请求拦截：注入 `Authorization: Bearer ***`（userStore.accessToken）；非 FormData 自动 JSON 序列化
- 响应拦截：成功直接 return；401 防抖（3秒一次）+ logOut + ElMessage
- 204 No Content → null；422 验证错误优先取 errors[field][0]；showSuccessMessage 由前端传入
- 默认重试 MAX_RETRIES=0；可对 408/5xx 指数退避
- 错误类 HttpError（code/data/timestamp/url/method）

### 4.4 useTable（企业级，hooks/core/useTable.ts ~730行）
- 泛型自动推导 API 参数/响应类型
- 4配置块：core(apiFn/columnsFactory/pagination)/transform(数据转换)/performance(缓存/防抖)/hooks(回调)
- 5种刷新策略：refreshCreate/refreshUpdate/refreshRemove/refreshData/refreshSoft
- AbortController 取消竞态；LRU 缓存（ohash key + 4种清理策略 CLEAR_ALL/CURRENT/PAGINATION/KEEP_ALL）
- 移动端分页适配、列配置（useTableColumns）、防抖搜索

### 4.5 AI 聊天（api/chat.ts）
- 原生 fetch + ReadableStream 解析 SSE（绕过 axios）
- 13种事件：stream_start/text_start/text_delta/text_end/reasoning_start/delta/end/tool_call/tool_result/tool_approval_request/citation/error/stream_end
- 支持 reasoning 思考流、tool_call 可视化、**tool_approval_request 人工审批按钮**→ POST /admin/chat/approve 继续流
- 会话 CRUD 走 axios

### 4.6 实时通信双方案
- utils/echo：Laravel Echo + Reverb 单例；自定义 authorizer fetch 带 Bearer + X-Socket-ID；登出 destroyEcho
- utils/socket：原生 WebSocketClient 单例；心跳5s、ping 10s、超时10s；指数退避+抖动重连（最多10次）；连接前消息队列 flush

### 4.7 主题
- LIGHT/DARK/AUTO（usePreferredDark 跟随系统）
- 9级主色阶 CSS 变量动态生成；切换时临时禁用 transition 防闪烁；Element Plus 主题同步
- 配套色弱、水印、圆角、容器宽度、页面过渡全套配置；ArtSettingsPanel 独立 composables

### 4.8 组件自动注册
- unplugin-vue-components 自动扫描；components.d.ts 生成类型（含 Element Plus 组件如 ElTreeSelect）
- 业务组件 `defineOptions({ name: 'ArtXxx' })`，以 `art-` 前缀在模板使用

### 4.9 配置驱动表单
- ArtForm：JSON schema 配置（type=input/select/checkboxgroup/radiogroup…），component :is 动态渲染，响应式栅格，自定义slot
- ArtSearchBar：继承 ArtForm，折叠/展开

### 4.10 跨组件通信
- mitt 事件总线（utils/sys/mittBus.ts）：triggerFireworks / openSetting / openSearchDialog / openChat / openLockScreen

---

## 五、API 接口清单（对接后端 /admin 前缀）

| 文件 | 前缀 | 功能 |
|---|---|---|
| api/auth.ts | `/admin/auth` | login(POST)、info(GET)、logout(POST) |
| api/system-manage.ts | `/admin/admins` `/admin/roles` `/admin/menus` `/admin/routes` `/admin/settings` `/admin/areas` `/admin/phone-codes` `/admin/mail-codes` | 管理员CRUD+角色分配+状态+重置密码+登录历史；角色CRUD+权限树分配；菜单树CRUD+路由列表；设置CRUD+分组+批量保存；地区树CRUD；验证码只读列表 |
| api/user-manage.ts | `/admin/users` | 前台用户：列表/详情/更新/软删除/冻结启用/重置密码/重置邮箱手机/调整余额(积分金币)/延长VIP/登录历史 |
| api/chat.ts | `/admin/chat` `/admin/chat/approve` | SSE 聊天流、工具审批；会话 CRUD |

权限标识按钮示例：`users.edit`、`users.delete`（按钮通过 ArtButtonMore 的 auth 字段 + v-auth 指令/useAuth().hasAuth() 控制）。

---

## 六、环境变量

| 变量 | dev | prod |
|---|---|---|
| VITE_BASE_URL | `/` | 按部署子目录 |
| VITE_API_URL | `/`（走代理） | 完整后端地址 |
| VITE_API_PROXY_URL | `http://laravel-skel-pro.test` | — |
| VITE_REVERB_HOST | `laravel-skel-pro.test` | 生产主机 |
| VITE_REVERB_PORT | `8080` | — |
| VITE_REVERB_SCHEME | `http` | — |
| VITE_ACCESS_MODE | （前端/后端权限模式） | — |

---

## 七、Laravel 后端侧（laravel-skel-pro/resources）

- `views/main/index.blade.php`：Laravel 默认欢迎页（未替换为 SPA 入口，SPA 独立运行）
- `views/main/redirect.blade.php`：通用重定向中转页
- `views/emails/verify_code.blade.php`：邮件验证码模板
- `views/vendor/pulse/dashboard.blade.php`：Pulse 面板
- `js/app.js`：仅 `import './echo'`
- `js/echo.js`：Echo 初始化
- `css/app.css`：Tailwind 入口

---

## 八、业务视图模块（views/）

| 模块 | 路径 | 功能 |
|---|---|---|
| 登录 | auth/login | 登录（拖拽验证/主题/语言切换） |
| 工作台 | dashboard/console | 仪表盘（7个子模块组件） |
| 异常 | exception/403,404,500 | 复用 ArtException |
| 结果 | result/success,fail | 复用 ArtResultPage |
| iframe | outside/Iframe | 内嵌外部页面 |
| 首页 | index/ | 根路径重定向 |
| 管理员 | system/admin | 管理员 CRUD+角色分配+状态+重置密码+登录历史 |
| 角色 | system/role | 角色 CRUD+权限树分配 |
| 菜单 | system/menu | 目录/菜单/按钮三级树 CRUD |
| 设置 | system/setting | 配置项按分组+批量保存 |
| 配置 | system/config | 系统配置页 |
| 地区 | system/area | 地区树 CRUD |
| 邮件验证码 | system/mail-code | 只读列表 |
| 短信验证码 | system/phone-code | 只读列表 |
| 个人中心 | system/user-center | 当前管理员资料/密码 |
| **前台用户** | **user/list** | **列表/详情/编辑/冻结/重置密码/重置邮箱手机/调整余额(积分+金币)/延长VIP/软删除/登录历史（新增模块）** |

---

## 九、新增模块：前台用户管理（user/list）

- 视图：`src/views/user/list/index.vue`
- 子模块（modules/）：
  - user-search.vue（搜索栏）
  - user-dialog.vue（编辑）
  - user-detail-dialog.vue（详情）
  - user-reset-password-dialog.vue（重置密码）
  - user-reset-contact-dialog.vue（重置邮箱/手机号）
  - user-adjust-balance-dialog.vue（调整积分/金币）
  - user-extend-vip-dialog.vue（延长 VIP）
- API：`src/api/user-manage.ts`（fetchGetUserList/GetUserDetail/UpdateUser/DeleteUser/ToggleUserStatus/ResetUserPassword/ResetUserContact/AdjustUserBalance/ExtendUserVip/UserLoginHistories）
- 典型页面范式：
  ```
  UserSearch + ElCard(ArtTableHeader + ArtTable) + 多个 Dialog ref
  使用 useTable({ core: { apiFn: fetchGetUserList, apiParams: { page:1, per_page:20 }, columnsFactory: () => [...] } })
  columns 中用 h(ElAvatar)、h(ElTag)、h(ArtButtonMore, { list, onClick: handleAction }) 渲染
  ArtButtonMore list 项带 auth: 'users.edit' | 'users.delete' 按钮权限
  ```

---

## 十、关键文件绝对路径索引

- HTTP 核心：`/Users/xutongle/Skel/laravel-art-admin/src/utils/http/index.ts`
- HTTP 错误：`/Users/xutongle/Skel/laravel-art-admin/src/utils/http/error.ts`
- 路由守卫：`/Users/xutongle/Skel/laravel-art-admin/src/router/guards/beforeEach.ts`
- 路由核心：`/Users/xutongle/Skel/laravel-art-admin/src/router/core/`
- useTable Hook：`/Users/xutongle/Skel/laravel-art-admin/src/hooks/core/useTable.ts`
- useAuth Hook：`/Users/xutongle/Skel/laravel-art-admin/src/hooks/core/useAuth.ts`
- 主题 Hook：`/Users/xutongle/Skel/laravel-art-admin/src/hooks/core/useTheme.ts`
- Echo 封装：`/Users/xutongle/Skel/laravel-art-admin/src/utils/echo/index.ts`
- WebSocket 类：`/Users/xutongle/Skel/laravel-art-admin/src/utils/socket/index.ts`
- mitt 事件总线：`/Users/xutongle/Skel/laravel-art-admin/src/utils/sys/mittBus.ts`
- Pinia 入口：`/Users/xutongle/Skel/laravel-art-admin/src/store/index.ts`
- 全局配置：`/Users/xutongle/Skel/laravel-art-admin/src/config/index.ts`
- 默认设置：`/Users/xutongle/Skel/laravel-art-admin/src/config/setting.ts`
- 国际化：`/Users/xutongle/Skel/laravel-art-admin/src/locales/index.ts`
- 指令注册：`/Users/xutongle/Skel/laravel-art-admin/src/directives/index.ts`
- ECharts 插件：`/Users/xutongle/Skel/laravel-art-admin/src/plugins/echarts.ts`
- Vite 配置：`/Users/xutongle/Skel/laravel-art-admin/vite.config.ts`
- package.json：`/Users/xutongle/Skel/laravel-art-admin/package.json`
- 用户列表页：`/Users/xutongle/Skel/laravel-art-admin/src/views/user/list/index.vue`
- 用户API：`/Users/xutongle/Skel/laravel-art-admin/src/api/user-manage.ts`
- 全局Api命名空间：`/Users/xutongle/Skel/laravel-art-admin/src/types/api/api.d.ts`
