<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Visit\MyVisitsIndexRequest;
use App\Http\Requests\Api\V1\Visit\RecordVisitRequest;
use App\Http\Resources\Api\V1\VisitResource;
use App\Models\Content\Visit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 访问记录管理
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class VisitController extends Controller
{
    /**
     * VisitController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 我的访问记录列表（分页）
     */
    public function myVisits(MyVisitsIndexRequest $request): AnonymousResourceCollection
    {
        $items = Visit::forUser($request->user()->id)
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->string('source_type')))
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return VisitResource::collection($items);
    }

    /**
     * 记录一次访问
     */
    public function record(RecordVisitRequest $request): VisitResource
    {
        return new VisitResource(Visit::create($request->validated()));
    }
}
