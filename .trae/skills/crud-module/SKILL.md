---
name: crud-module
description: Generate a full-stack admin CRUD module with Laravel backend and Vue admin page following repo conventions. Use when adding a management page, CRUD feature, or business module. Not for API-only or frontend-only tasks.
---

# Full-stack CRUD Module Generator

Create one admin CRUD module end to end across `laravel-skel-forge` (Laravel 13 backend) and `laravel-skel-admin` (Vue 3 admin). Follow the existing Announcement module as the reference shape. Do not invent new patterns — mirror sibling files.

## Before writing

1. Read one existing module at every layer to copy structure exactly. Default reference: Announcement (`app/Models/Operation/Announcement.php`, `app/Http/Controllers/Admin/AnnouncementController.php`, `src/views/operation/announcement/`).
2. Confirm the entity name (singular, PascalCase) and domain folder (e.g. `Operation`, `System`, `User`, `Wallet`).
3. Use `php artisan make:* --no-interaction` for new files; never hand-write migration/controller headers when a generator exists.

## Backend deliverables (laravel-skel-forge)

Create in this order:

1. **Migration** — `php artisan make:migration create_{table}_table --no-interaction`. Add comments on every column; add indexes for FKs and common filter combinations.
2. **Model** in `app/Models/{Domain}/{Entity}.php`:
   - Extends `App\Models\Model`; declare table and fillable with PHP 8.4 attributes `#[Table('table_name')]` / `#[Fillable([...])]`.
   - `use HasFactory`; add `SoftDeletes` when the design needs trash.
   - Full PHPDoc property list with `@property` typings; typed relationships (`BelongsTo`, `HasMany`, etc.); query scopes for filters.
   - Cast enum fields to their `Enum` class; cast dates to `datetime`.
   - Model `casts()` method must explicitly cast every column.
3. **Factory** in `database/factories/{Domain}/{Entity}Factory.php` — namespace must match the model domain folder or `HasFactory` resolution fails at runtime. Add named state methods for key enum values (e.g. `draft()`, `published()`).
4. **Requests** in `app/Http/Requests/Admin/{Domain}/`:
   - `{Entity}IndexRequest` for list filters (validates query params like `keyword`, `status`).
   - `{Entity}SaveRequest` for create/update — use `prepareForValidation()` to inject system fields (e.g. `admin_id` from `$this->user('admin')?->id`).
   - Never allow counters, heat, rating, or lifecycle status on save; those are system-maintained.
   - FK validation via `Rule::exists(...)`; enum validation via `Rule::enum(EnumClass::class)`.
   - All requests extend `Illuminate\Foundation\Http\FormRequest`; add `@property-read` PHPDoc for each validated field.
5. **Resource** in `app/Http/Resources/Admin/{Entity}Resource.php` (flat directory, no subdirectories):
   - Extends `JsonResource`; use `whenLoaded` for relations.
   - Expose enum `value` plus `label()` (and `labelType()` if applicable).
   - Use `$this->when($request->routeIs('admin.{entity}.show'), ...)` to include heavy fields only in detail responses.
   - Format dates via `?->toDateTimeString()`.
6. **Controller** in `app/Http/Controllers/Admin/{Entity}Controller.php` (flat directory):
   - Constructor: `$this->middleware('auth:admin')` + per-action `permission:{entities}.index/create/edit/delete` middleware.
   - Inject FormRequest for write operations; use bare `Request` or `{Entity}IndexRequest` for simple reads.
   - Returns `Resource` (single), `Resource::collection` (list/paginate), or `JsonResponse` (204 for delete).
   - Use `per_page($request)` helper for pagination.
   - Complex state changes go through a Service in `app/Services/` (flat or `app/Services/{Domain}/`), never inline in the controller.
   - Multi-table writes wrapped in `DB::transaction`.
7. **Routes** in `routes/admin.php`:
   - Standard CRUD: `Route::apiResource('{entities}', {Entity}Controller::class)`.
   - Custom actions: `Route::group(['prefix' => '{entities}', 'as' => '{entities}.'], ...)` **before** the `apiResource` line (option routes before `{id}` routes).
8. **Menu and permissions** — edit the existing `database/migrations/2026_10_31_111934_seed_initial_admin_data.php` migration. Do not create a new migration.
   - Add a menu item under the appropriate parent directory (e.g. `Operation` for announcements/banners, `System` for admins/roles).
   - Menu item fields: `key`, `path`, `name`, `component` (points to frontend `src/views/...`), `title`, `icon`, `type` (`MenuType::MENU`), `sort`, `permission`, `buttons` array.
   - Each button: `['title' => '新增{entity}', 'permission' => '{entities}.create']`.
   - Permissions auto-sync to `super_admin` role at the end of `up()`.
9. **Tests** in `tests/Feature/Admin/{Entity}ControllerTest.php`:
   - PHPUnit 12 attributes: `#[CoversClass({Entity}Controller::class)]`, `#[Group('admin')]`, `#[Group('{entity}')]` at class level.
   - `#[Test]` + `#[TestDox('描述')]` on every method; method names in `snake_case`.
   - `use RefreshDatabase`; extends `Tests\TestCase`.
   - `setUp()`: fetch the pre-seeded `admin` user via `Admin::query()->where('username', 'admin')->firstOrFail()`.
   - `actingAsAdmin()` helper: `$this->actingAs($this->admin, 'admin')`.
   - `validPayload()` helper with default fields + `$overrides` merge.
   - Cover: guest 401, list pagination, filter by status/keyword, create (201), validation 422, show detail, update, delete (204), and any custom actions (toggle-status, etc.).
   - Non-ASCII query strings must use `rawurlencode`.

## Frontend deliverables (laravel-skel-admin)

1. **Types** — add to the matching namespace in `src/types/api/api.d.ts` (e.g. `Api.OperationManage`, `Api.SystemManage`):
   - `type {Entity}List = Api.Common.PaginatedResponse<{Entity}ListItem>`
   - `interface {Entity}ListItem` — mirror backend Resource output fields.
   - `interface {Entity}SearchParams extends Api.Common.CommonSearchParams` — keyword, status, etc.
   - `interface {Entity}SaveParams` — fields for create/update payload.
2. **API** — add `fetch*` functions to the matching module file in `src/api/` (e.g. `operation-manage.ts`, `system-manage.ts`):
   - `fetchGet{Entity}List(params)`, `fetchGet{Entity}Detail(id)`, `fetchCreate{Entity}(data)`, `fetchUpdate{Entity}(id, data)`, `fetchDelete{Entity}(id)`.
   - Mutation operations pass `showSuccessMessage: true` and `successMessage: '...'`.
   - RESTful URLs: `/admin/{entities}`, `/admin/{entities}/${id}`.
3. **Views** in `src/views/{group}/{entity}/`:
   - `index.vue`: `useTable` + `ArtTableHeader` + `ArtTable`; dialogs only orchestrated here. No inline filter forms or table cells.
   - `modules/{entity}-search.vue`: declare items via `ArtSearchBar` `:items` prop, never hand-build `el-form-item`. Emit `search` and `resetSearchParams`.
   - `modules/{entity}-edit-dialog.vue`: create/edit in one dialog; `ElDialog` + `ElForm` with typed `FormInstance`/`FormRules`. Expose `open(row?)` via `defineExpose`.
   - Optional: `modules/{entity}-detail-dialog.vue`, `modules/{entity}-permission-dialog.vue` for specialized views.
4. Reuse `art-` components first (`ArtTable`, `ArtTableHeader`, `ArtSearchBar`, `ArtButtonTable`, `ArtButtonMore`, `ArtWangEditor`, etc.). Create page-private components in `modules/` only for unsupported interactions; promote to global only after reuse across 3+ pages.
5. Row actions: under 4 buttons use inline `ArtButtonTable`; 4 or more put secondary/danger items in `ArtButtonMore` (import `ButtonMoreItem` type). Wire permissions with `useAuth().hasAuth()` in render functions and `v-auth` in templates.

## Required conventions

- All PHP files start with the license header comment and `declare(strict_types=1);`.
- JSON columns: declare `->nullable()` with no DB default; the model casts to array and must return `[]` for null. MySQL rejects defaults on JSON columns.
- Tests with non-ASCII query strings must use `rawurlencode`.
- Store responses assert `201` (`assertCreated`), not 200. Delete responses assert `204` (`assertNoContent`).
- Counters/timestamps the system owns are excluded from save requests and fillable exposure.
- Destructive validation of the dev database (`migrate:fresh`) is forbidden; fix partial tables precisely (drop only the empty leftover, re-run `php artisan migrate`).

## Verification (run all, in order)

1. `vendor/bin/pint --dirty --format agent` in laravel-skel-forge.
2. `php artisan test --compact tests/Feature/Admin/{Entity}ControllerTest.php` plus related service tests.
3. `php artisan migrate --no-interaction` when migrations changed; verify seeded menu/permissions via `php artisan tinker --execute 'AdminMenu::query()->where("permission","{entities}.index")->exists();'`.
4. In laravel-skel-admin: `pnpm build` (vue-tsc type check must pass).
5. Run the automation workflow: publish new API endpoints to Apifox, update README.md, update PRD docs.

Only report done after backend tests, frontend build, and lint all pass.
