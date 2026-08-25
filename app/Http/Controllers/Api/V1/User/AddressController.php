<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\User\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 地址管理
 *
 * @author Tongle Xu <xutongle@msn.com>
 */
class AddressController extends Controller
{
    /**
     * AddressController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Address::class, 'address');
    }

    /**
     * 获取地址列表
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Address::forUser($request->user()->id)
            ->orderByDesc('id')
            ->paginate(per_page($request));

        return AddressResource::collection($items);
    }

    /**
     * 创建地址
     */
    public function store(StoreAddressRequest $request): AddressResource
    {
        $address = Address::create($request->validated());

        return new AddressResource($address);
    }

    /**
     * 取地址详情
     */
    public function show(Address $address): AddressResource
    {
        return new AddressResource($address);
    }

    /**
     * 更新地址
     */
    public function update(UpdateAddressRequest $request, Address $address): AddressResource
    {
        $address->update($request->validated());

        return new AddressResource($address);
    }

    /**
     * 设为默认地址
     */
    public function setDefault(Address $address): AddressResource
    {
        $address->markDefault();

        return new AddressResource($address);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address): Response
    {
        $address->delete();

        return response()->noContent();
    }
}
