<?php

/**
 * @Author: h9471
 * @Created: 2020/06/30 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HasBatchOperate;
use App\Jobs\UpdatePackageWarning;
use App\Models\AutomaticSignIn;
use App\Models\Company;
use App\Models\CompanyProp;
use App\Models\Package;
use App\Services\Admin\CompanyPropService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyPropController extends Controller
{
    use HasBatchOperate;

    protected $service;

    public function __construct()
    {
        $this->service = new CompanyPropService();
    }

    public function getSettings()
    {
        $status = CompanyProp::where('type', CompanyProp::PACKAGE_EXPRESS_LINE)
            ->get()
            ->first();

        $packageProp = CompanyProp::where('type', CompanyProp::PROP_TYPE)
            ->get()
            ->first();

        return ApiResponseService::success([
            'package_express_line' => (int) ($status !== null ? $status->prop : 0),
            'package_prop' => (int) ($packageProp !== null ? $packageProp->prop : 0),
        ]);
    }

    public function updateSettings(int $status)
    {
        return CompanyProp::updateOrCreate(
            [
                'type' => CompanyProp::PACKAGE_EXPRESS_LINE,
                'company_id' => auth('admin')->user()->company_id,
            ],
            [
                'prop' => $status,
            ]
        );
    }

    /**
     * @return array
     */
    public function getOrderInvoiceMode(): array
    {
        $status = CompanyProp::where('type', CompanyProp::ORDER_INVOICE_MODE)
            ->get()
            ->first();


        return ApiResponseService::success([
            'invoice_mode' => (int) ($status !== null ? $status->prop : 0),
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function updateOrderInvoiceMode(Request $request)
    {
        CompanyProp::updateOrCreate(
            [
                'type' => CompanyProp::ORDER_INVOICE_MODE,
                'company_id' => auth('admin')->user()->company_id,
            ],
            [
                'prop' => $request->input('invoice_mode', 0),
            ]
        );

        return ApiResponseService::success();
    }

    /**
     * @return array
     */
    public function getSpuSetting()
    {
        $status = CompanyProp::where('type', CompanyProp::ENABLE_PACKAGE_SPU)
            ->get()
            ->first();

        return ApiResponseService::success([
            'spu_enabled' => (int) ($status !== null ? $status->prop : 0),
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function updateSpuSetting(Request $request)
    {
        CompanyProp::updateOrCreate(
            [
                'type' => CompanyProp::ENABLE_PACKAGE_SPU,
                'company_id' => auth('admin')->user()->company_id,
            ],
            [
                'prop' => $request->input('spu_enabled', 0),
            ]
        );

        return ApiResponseService::success();
    }

    /**
     * @return array
     */
    public function getShipmentSetting(): array
    {
        $status = CompanyProp::where('type', CompanyProp::SHIPMENT_DISMISS_AMOUNT)
            ->get()
            ->first();

        return ApiResponseService::success([
            'dismiss_amount' => (int) ($status !== null ? $status->prop : 0),
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function updateShipmentSetting(Request $request):array
    {
        CompanyProp::updateOrCreate(
            [
                'type' => CompanyProp::SHIPMENT_DISMISS_AMOUNT,
                'company_id' => auth('admin')->user()->company_id,
            ],
            [
                'prop' => $request->input('dismiss_amount', 0),
            ]
        );

        return ApiResponseService::success();
    }

    /**
     * @return array
     */
    public function getInStorageSetting(): array
    {
        $setting = CompanyProp::query()->whereIn('type', [
                CompanyProp::PACKAGE_IN_STORAGE_SIZE,
                CompanyProp::PACKAGE_IN_STORAGE_LOCATION,
                CompanyProp::PACKAGE_WARNING,
                CompanyProp::PROP_TYPE,
                CompanyProp::PACKAGE_AUTO_CODE,
            ]
        )->get()->mapWithKeys(function ($value) {
            $name = 'FangDanSB';

            switch ($value->type) {
                case CompanyProp::PACKAGE_IN_STORAGE_SIZE:
                    $name = 'size';
                    break;
                case CompanyProp::PACKAGE_IN_STORAGE_LOCATION:
                    $name = 'location';
                    break;
                case CompanyProp::PACKAGE_WARNING:
                    $name = 'package_warning';
                    break;
                case CompanyProp::PROP_TYPE:
                    $name = 'prop_type';
                    break;
                case CompanyProp::PACKAGE_AUTO_CODE:
                    $name = 'package_auto_code';
                    break;
            }

            return [$name => (int) $value->prop,];
        })->all();

        $temp = [
            'size' => 0,
            'location' => 0,
            'package_warning' => 0,
            'prop_type' => 0,
            'package_auto_code' => 0,
        ];

        if (! $setting) {
            return ApiResponseService::success($temp);
        }

        return ApiResponseService::success(array_merge($temp, $setting));
    }

    /**
     * @param  Request  $request
     * @return JsonResponse|array
     */
    public function updateInStorageSetting(Request $request): JsonResponse|array
    {
        $requestData = $request->validate([
            'size' => 'required',
            'location' => 'required',
            'package_warning' => 'required',
            'prop_type' => 'required',
            'package_auto_code' => 'required|boolean',
        ]);

        foreach ($requestData as $key => $data) {
            switch ($key) {
                case 'size':
                    $type = CompanyProp::PACKAGE_IN_STORAGE_SIZE;
                    break;
                case 'location':
                    $type = CompanyProp::PACKAGE_IN_STORAGE_LOCATION;
                    break;
                case 'package_warning':
                    $type = CompanyProp::PACKAGE_WARNING;
                    self::removePackageWarning();
                   dispatch(new UpdatePackageWarning())->onQueue('notify');
                    break;
                case 'prop_type':
                    $type = CompanyProp::PROP_TYPE;
                    break;
                case 'package_auto_code':
                    $type = CompanyProp::PACKAGE_AUTO_CODE;
                    break;
                default:
                    return ApiResponseService::error();
            }

            CompanyProp::query()->updateOrCreate(
                [
                    'type' => $type,
                ],
                [
                    'prop' => $data,
                ]
            );
        }

        return ApiResponseService::success();
    }

    /**
     * 清除所有包裹预警信息
     *
     * @return int
     */
    protected static function removePackageWarning()
    {
        return Package::query()->where('is_warning', 1)
            ->update(['is_warning' => 0]);
    }

    public function getDeclareConfig()
    {
        $company = Company::query()->findOrFail(auth()->user()->company_id);

        return ApiResponseService::success([
            'declare_tax_number'=>$company->declare_tax_number,
            'declare_unit'=>$company->declare_unit,
            'declare_currency'=>$company->declare_currency,
        ]);
    }

    /**
     * 更新申报配置信息
     * @param Request $request
     * @return array
     */
    public function updateDeclareConfig(Request $request): array
    {
        $requestData = $request->validate([
            'declare_tax_number' => 'nullable|string|max:50',
            'declare_unit' => 'nullable|string|max:50',
            'declare_currency' => 'nullable|string|max:50',
        ]);

        Company::query()->where('id', auth()->user()->company_id)->update([
            'declare_tax_number' => $requestData['declare_tax_number'] ?? '',
            'declare_unit' => $requestData['declare_unit'] ?? '',
            'declare_currency' => $requestData['declare_currency'] ?? '',
        ]);

        return ApiResponseService::success();
    }

    /**
     * 更新或创建自动更新配置
     * @param Request $request
     * @return array
     */
    public function updateOrCreateAutomaticSignIn(Request $request): array
    {
        $data = $request->validate([
            'enabled' => 'required|int|in:0,1',
            'is_evaluate' => 'required|int|in:0,1',
            'trigger_days' => 'required|int',
            'evaluate_score' => 'required|int|between:0,6',
            'evaluate_content' => 'required_if:is_evaluate,1|nullable|string|max:180',
            'sign_before_station' => 'sometimes|nullable|integer|in:0,1',
        ]);

        $auto = AutomaticSignIn::query()->where('company_id', auth()->user()->company_id)->first();
        if (empty($auto)) {
            AutomaticSignIn::query()->create([
                'enabled' => $data['enabled'],
                'is_evaluate' => $data['is_evaluate'],
                'trigger_days' => $data['trigger_days'],
                'evaluate_score' => $data['evaluate_score'],
                'evaluate_content' => $data['evaluate_content'] ?? '',
            ]);
        } else {
            AutomaticSignIn::query()->where('id', $auto->id)->update([
                'enabled' => $data['enabled'],
                'is_evaluate' => $data['is_evaluate'],
                'trigger_days' => $data['trigger_days'],
                'evaluate_score' => $data['evaluate_score'],
                'evaluate_content' => $data['evaluate_content'] ?? '',
            ]);
        }

        CompanyProp::query()->updateOrCreate(
            [
                'type' => CompanyProp::DISABLE_SIGN_BEFORE_STATION,
            ],
            [
                'prop' => ($data['sign_before_station'] ?? 1) ? 0 : 1,
            ]
        );

        return ApiResponseService::success();
    }

    /**
     * 获取自动签收配置信息
     * @return array
     */
    public function getAutomaticSignIn(): array
    {
        $auto = AutomaticSignIn::query()->first();

        if (empty($auto)) {
            return ApiResponseService::success();
        }

        $sign = CompanyProp::query()
            ->where('type', CompanyProp::DISABLE_SIGN_BEFORE_STATION)
            ->first();

        $auto->sign_before_station = $sign ? ($sign->prop ? 0 : 1) : 1;

        return ApiResponseService::success($auto);
    }

    /**
     * 获取清点项目数据
     * @return array
     */
    public function getInventoryItems(): array
    {
        $data = DB::table('dsp_inventory_items')->where('status', 1)->get();
        if (empty($data)) {
            return ApiResponseService::success();
        }
        return ApiResponseService::success($data);
    }

    /**
     * 获取拆包清点配置
     * @return array
     */
    public function getUnpackingAndInventoryConfiguration(): array
    {
        return ApiResponseService::success($this->service->getUnpackingAndInventoryConfiguration());
    }

    /**
     * 更新或创建拆包清点配置
     * @param Request $request
     * @return JsonResponse|array
     */
    public function updateUnpackingAndInventoryConfig(Request $request): JsonResponse|array
    {
        if ($this->service->updateUnpackingAndInventoryConfig($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取违禁词配置
     * @return array
     */
    public function getProhibitedWordsConfig(): array
    {
        return ApiResponseService::success($this->service->getProhibitedWordsConfig());
    }

    /**
     * 更新或创建违禁词配置
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateProhibitedWordsConfig(Request $request): JsonResponse|array
    {
        if ($this->service->updateProhibitedWordsConfig($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取工单类型配置
     * @return array
     */
    public function getWordOrderTypeConfig(): array
    {
        return ApiResponseService::success($this->service->getWordOrderTypeConfig());
    }

    /**
     * 获取工单类型详情
     * @return array
     */
    public function getWordOrderTypeConfigById(int $id): array
    {
        return ApiResponseService::success($this->service->getWordOrderTypeConfigById($id));
    }

    /**
     * 创建工单类型配置
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function createWordOrderTypeConfig(Request $request): JsonResponse|array
    {
        if ($this->service->createWordOrderTypeConfig($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 修改工单类型
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws ValidationException
     */
    public function updateWordOrderTypeConfig(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateWordOrderTypeConfig($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 修改系统工单类型数据
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws ValidationException
     */
    public function updateSystemWordOrderTypeConfig(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateSystemWordOrderTypeConfig($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除工单类型
     * @param int $id
     * @return JsonResponse|array
     */
    public function delWordOrderTypeConfig(int $id): JsonResponse|array
    {
        if ($this->service->delWordOrderTypeConfig($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置工单类型状态
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     */
    public function setWordOrderTypeConfigStatus(int $id, int $status): JsonResponse|array
    {
        if ($this->service->setWordOrderTypeConfigStatus($id, $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置高货值创建工单配置
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function setHighValueInsuranceConfig(Request $request): JsonResponse|array
    {
        if ($this->service->setHighValueInsuranceConfig($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getHighValueInsuranceConfig(): array
    {
        return ApiResponseService::success($this->service->getHighValueInsuranceConfig());
    }
}
