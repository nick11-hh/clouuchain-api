<?php
/**
 * @Author: h9471
 * @Created: 2019/11/10 11:37
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\LocalizationInfo;
use App\Models\Currency;
use App\Models\CurrencyList;
use App\Services\Admin\LocalizationService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocalizationController extends Controller
{
    protected LocalizationService $service;

    public function __construct(LocalizationService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取信息
     * @return LocalizationInfo
     */
    public function getInfo(): LocalizationInfo
    {
        $info = $this->service->getInfo();

        $data = array_merge(
            $info ? $info->toArray() : [],
            (new CompanyPropController())->getSettings(),
            ['currency' => Currency::code()]
        );

        return LocalizationInfo::make((object) $data)
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 更新信息
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function update(Request $request): JsonResponse|array
    {
        if ($this->service->updateInfo($request->all())) {
            (new CompanyPropController())->updateSettings($request->input('package_express_line', 0));

            if ($code = $request->input('currency', null)) {
                Currency::setDefaultCurrency($code);
            }

            return ApiResponseService::success();
        }


        return ApiResponseService::error();
    }

    /**
     * 获得本地化配置信息
     * @return array
     */
    public function getLocalizationConfiguration(): array
    {
        return ApiResponseService::success($this->service->getLocalizationList());
    }

    /**
     * @return array
     */
    public function currencyList(): array
    {
        return ApiResponseService::success(CurrencyList::enabledList());
    }
}
