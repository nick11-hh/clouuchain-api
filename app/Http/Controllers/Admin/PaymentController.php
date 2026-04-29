<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasTranslateOperator;
use App\Http\Resources\Admin\DefaultRechargeAmountList;
use App\Http\Resources\Admin\PaymentSettingConnectInfo;
use App\Http\Resources\Admin\PaymentSettingInfo;
use App\Http\Resources\Admin\PaymentSettingList;
use App\Http\Resources\Admin\PaypalPaymentInfo;
use App\Models\DefaultRechargeAmount;
use App\Services\Admin\PaymentService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

class PaymentController extends Controller
{
    use HasTranslateOperator;

    protected $service;

    public function __construct(PaymentService $service)
    {
        $this->service = $service;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return PaymentSettingList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function show(int $id)
    {
        return PaymentSettingInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 新建
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        if ($this->service->add($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 更新
     * @param int $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(int $id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除
     * @param int $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        if ($this->service->delete(Arr::wrap($id))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexAccount(int $id)
    {
        return PaymentSettingConnectInfo::collection($this->service->indexAccount($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return PaymentSettingConnectInfo
     */
    public function showAccount(int $id)
    {
        return PaymentSettingConnectInfo::make($this->service->showAccount($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeAccount(Request $request)
    {
        if ($this->service->storeAccount($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function destroyAccount(int $id)
    {
        if ($this->service->destroyAccount($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateAccount(int $id, Request $request)
    {
        if ($this->service->updateAccount($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateAccountTranslateData(int $id)
    {
        if ($this->service->updateAccountTranslateData($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateTranslateData(int $id)
    {
        if ($this->service->updateTranslateData($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置状态
     * @param int $id
     * @param int $status
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function setStatus(int $id, int $status)
    {
        if ($this->service->setStatus($id, (bool)$status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getPaymentStatus()
    {
        return ApiResponseService::success([
            [
                'type' => 1,
                'name' => 'paypal',
            ] + $this->service->getPaypalPaymentStatus(),
        ]);
    }

    /**
     * @param int $status
     * @param string $type
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function setPaymentStatus(int $status, string $type)
    {
        switch ($type) {
            case '1':
            case 'paypal':
                if ($this->service->setPaypalPaymentStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '2':
            case 'wechat':
                if ($this->service->setWechatPaymentStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '3':
                if ($this->service->setPayOnDeliveryStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '4':
            case 'alipay':
                if ($this->service->setAlipayPaymentStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '5':
            case 'ottpay':
                if ($this->service->setOttPayStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '6':
            case 'omipay':
                if ($this->service->setOmiPayStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '7':
            case 'iotpay':
                if ($this->service->setIOTPayStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '8':
            case 'iPay88':
                if ($this->service->setIPay88Status((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '9':
            case 'qfpay':
                if ($this->service->setQfPayStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            case '10':
            case 'linepay':
                if ($this->service->setLinePayStatus((bool)$status)) {
                    return ApiResponseService::success();
                }
                break;
            default:
                # code...
                break;
        }

        return ApiResponseService::error();
    }

    /**
     * 获得paypal支付配置信息
     * @return PaypalPaymentInfo
     */
    public function getPaypalConfiguration()
    {
        return PaypalPaymentInfo::make($this->service->getPaypalPaymentConfiguration())
            ->additional(ApiResponseService::success());
    }

    /**
     * 更新paypal支付配置信息
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updatePaypalConfiguration(Request $request)
    {
        if ($this->service->updatePaypalPaymentConfiguration($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getPaypalPaymentStatus()
    {
        return ApiResponseService::success($this->service->getPaypalPaymentStatus());
    }

    public function setPaypalPaymentStatus(int $status)
    {
        if ($this->service->setPaypalPaymentStatus((bool)$status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getDefaultAmounts()
    {
        return DefaultRechargeAmountList::collection(DefaultRechargeAmount::query()->orderBy('amount')->get())
            ->additional(ApiResponseService::success());
    }

    public function addDefaultAmount(Request $request)
    {
        $cAmount = $request->input('complimentary_amount', 0);
        $amount = $request->input('amount', 0);

        DefaultRechargeAmount::query()->firstOrCreate(['amount' => $amount, 'complimentary_amount' => $cAmount ?: 0]);

        return ApiResponseService::success();
    }

    public function removeDefaultAmount($id)
    {
        if (DefaultRechargeAmount::query()->where('id', $id)->delete()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }
}
