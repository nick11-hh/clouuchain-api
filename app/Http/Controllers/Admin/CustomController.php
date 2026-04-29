<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CustomInfo;
use App\Http\Resources\Admin\CustomList;
use App\Http\Resources\Admin\CustomListWithShopList;
use App\Http\Resources\Admin\CustomSimpleList;
use App\Lib\Code;
use App\Models\CustomsQuoteConfig;
use App\Services\Admin\CustomService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomController extends Controller
{
    public $service;

    public function __construct(CustomService $userService)
    {
        $this->service = $userService;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $list = $this->service->index();

        $list->each(function ($item) {
            $sd = CustomsQuoteConfig::query()->where('customer_id', $item->id)->value('id');
            if (!$sd){
                CustomsQuoteConfig::query()->create([
                    'customer_id' => $item->id,
                    'product_quote_default_profit_rate' => 20,
                    'freight_quote_default_profit_rate' => 20,
                    'product_quote_review_profit_rate' => 20,
                    'freight_quote_review_profit_rate' => 20,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'admin_id' => 1
                ]);
            }
        });
        return CustomList::collection($list)
            ->additional(ApiResponseService::success());
    }

    public function simple()
    {
        $list = $this->service->simple();
        return CustomSimpleList::collection($list)
            ->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        return CustomList::make($this->service->show($id))
            ->additional(['code' => Code::SUCCESS, 'msg' => 'success']);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('添加客户成功');
        }
        return ApiResponseService::errorMessage('添加客户失败');
    }

    /**
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /** 更新用户状态
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request)
    {
        if ($this->service->updateStatus($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /** 修改密码
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword($id, Request $request)
    {
        if ($this->service->changePassword($id, $request->all())) {
            return ApiResponseService::successMessage('修改成功');
        }
        return ApiResponseService::errorMessage('修改失败');
    }

    /**
     * 调整信用额度
     */
    public function updateCreditLine(Request $request)
    {
        if ($this->service->updateCreditLine($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * 调整冻结额度
     */
    public function updateFrozenLimit(Request $request)
    {
        if ($this->service->updateFrozenLimit($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * 获取客户钱包详情
     */
    public function getWalletShow($id)
    {
        $data = $this->service->getWalletShow($id);

        return CustomInfo::make($data)
            ->additional(ApiResponseService::success());
    }

    /**
     * 钱包金额统计
     */
    public function getWalletCount()
    {
        $data = $this->service->getWalletCount();

        return ApiResponseService::success($data);
    }

    /**
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateAutoPayment(Request $request)
    {
        if ($this->service->updateAutoPayment($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    public function assignStaff()
    {
        return ApiResponseService::success($this->service->assignStaff());
    }

    public function export()
    {

        if (($this->service->export())) {
            return ApiResponseService::successMessage('导出任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }
        return ApiResponseService::errorMessage('导出失败');
    }

    /**
     * 获取邀请码
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/28 11:18
     */
    public function getInviteUrl()
    {
        return ApiResponseService::success($this->service->getInviteUrl());
    }

    /**
     * 获取客户列表携带店铺列表数据
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/10 22:12
     */
    public function getCustomWithShopList(Request $request)
    {
        $list = $this->service->getCustomWithShopList($request->all());
        return CustomListWithShopList::collection($list)
            ->additional(ApiResponseService::success());
    }

    /**
     * 登录客户端
     * @param Request $request
     * @return array
     * @throws \Throwable
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/22 11:25
     */
    public function loginClient(Request $request)
    {
        $data = $this->service->loginClient($request->all());
        return ApiResponseService::success($data);
    }

    public function getQuoteConfig($id)
    {
        $data = $this->service->getQuoteConfig($id);
        if($data){
            return ApiResponseService::successMessage(data: $data);
        }else{

            return ApiResponseService::errorMessage('此会员没有配置报价信息');
        }
    }

    public function OpenPlatformAuth($id)
    {

        $data = $this->service->OpenPlatformAuth($id);
        return ApiResponseService::success($data);
    }
}
