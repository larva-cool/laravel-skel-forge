<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\Attachment\AttachmentDestroyRequest;
use App\Http\Requests\Admin\Attachment\AttachmentIndexRequest;
use App\Http\Requests\Admin\Attachment\AttachmentMoveRequest;
use App\Http\Requests\Admin\Attachment\AttachmentRegisterRequest;
use App\Http\Requests\Admin\Attachment\AttachmentRenameRequest;
use App\Http\Resources\Admin\AttachmentResource;
use App\Models\System\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 后台附件管理控制器
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class AttachmentController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct(protected AttachmentService $attachmentService)
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:attachments.index')->only(['index', 'show', 'download', 'temporaryUrl', 'disks']);
        $this->middleware('permission:attachments.create')->only(['register']);
        $this->middleware('permission:attachments.edit')->only(['rename', 'move']);
        $this->middleware('permission:attachments.delete')->only(['destroy', 'batchDestroy']);
    }

    /**
     * 获取所有可用的存储磁盘（供前端下拉选择使用）
     */
    public function disks(): JsonResponse
    {
        $default = (string) config('filesystems.default');

        $data = [];
        foreach (array_keys((array) config('filesystems.disks', [])) as $disk) {
            $data[] = [
                'label' => (string) $disk,
                'value' => (string) $disk,
                'driver' => (string) config("filesystems.disks.{$disk}.driver"),
                'is_default' => $disk === $default,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * 获取附件列表
     */
    public function index(AttachmentIndexRequest $request): AnonymousResourceCollection
    {
        $attachments = Attachment::query()
            ->with('uploader')
            ->filter($request->filters())
            ->latest('id')
            ->paginate(per_page($request));

        return AttachmentResource::collection($attachments);
    }

    /**
     * 获取附件详情
     */
    public function show(int $id): AttachmentResource
    {
        $attachment = Attachment::query()->with('uploader')->findOrFail($id);

        return (new AttachmentResource($attachment))->withExists();
    }

    /**
     * 删除单个附件
     */
    public function destroy(int $id): JsonResponse
    {
        $attachment = Attachment::query()->findOrFail($id);
        $this->attachmentService->delete($attachment);

        return response()->json(['message' => __('admin.attachment_delete_success')]);
    }

    /**
     * 批量删除附件
     */
    public function batchDestroy(AttachmentDestroyRequest $request): JsonResponse
    {
        $count = $this->attachmentService->deleteMany($request->validated('ids'));

        return response()->json([
            'message' => __('admin.attachment_batch_delete_success'),
            'count' => $count,
        ]);
    }

    /**
     * 流式下载附件
     */
    public function download(int $id): StreamedResponse
    {
        $attachment = Attachment::query()->findOrFail($id);

        return $this->attachmentService->download($attachment);
    }

    /**
     * 获取附件临时签名访问地址
     */
    public function temporaryUrl(int $id): JsonResponse
    {
        $attachment = Attachment::query()->findOrFail($id);

        return response()->json(['url' => $this->attachmentService->temporaryUrl($attachment)]);
    }

    /**
     * 重命名附件显示名
     */
    public function rename(AttachmentRenameRequest $request, int $id): AttachmentResource
    {
        $attachment = Attachment::query()->findOrFail($id);
        $attachment = $this->attachmentService->rename($attachment, $request->validated('name'));

        return new AttachmentResource($attachment);
    }

    /**
     * 移动附件物理路径
     */
    public function move(AttachmentMoveRequest $request, int $id): AttachmentResource
    {
        $attachment = Attachment::query()->findOrFail($id);
        $attachment = $this->attachmentService->move($attachment, $request->validated('path'));

        return new AttachmentResource($attachment);
    }

    /**
     * 直传完成后登记附件元数据
     */
    public function register(AttachmentRegisterRequest $request): JsonResponse
    {
        $attachment = $this->attachmentService->record($request->toMeta(), $request->user());

        return response()->json(new AttachmentResource($attachment), 201);
    }
}
