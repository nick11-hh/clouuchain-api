<?php

namespace App\Services\Client;

use App\Lib\Code;
use App\Models\ExpressOrderAddressModel;
use App\Models\ExpressOrderItemsModel;
use App\Models\ExpressOrderModel;
use App\Models\ExpressOrderTrackingModel;
use App\Models\HandMovementModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\ShopOrderExpressOrderMappingsModel;
use App\Models\ShopOrderLogs;
use App\Models\WarehouseAddress;
use App\Models\WorldCountries;
use App\Models\WorldCountriesLocale;
use App\Services\Admin\BaseService;
use App\Services\Tracking\TrackingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use \App\Services\Admin\ExpressOrderService as AdminExpressOrderService;

/**
 * Class ExpressOrderService
 * @package App\Services\Client
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/11/8 18:20
 */
class ExpressOrderService extends BaseService
{
    public const QUERY_EXPRESS_TRACK_INFO_KEY = 'express_track_info:';
    /**
     * 初始化
     * @param ExpressOrderModel $model
     */
    public function __construct(ExpressOrderModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
    }

    //首页
    public function index()
    {
        return parent::index();
    }

    /**
     * 获取物流轨迹信息(缓存1小时过期)
     * @param $params
     * @return array|mixed
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/9 14:14
     */
    public function getTrackInfo($params)
    {
        validator($params, [
            'order_id' => 'required|integer',
        ])->validate();

        $id = $params['order_id'];

        $cacheKey = self::QUERY_EXPRESS_TRACK_INFO_KEY . $id . ':'. request()->header('language');
        $data = Cache::get($cacheKey);
        if (!empty($data)) {
            return $data;
        }

        $authService = new AuthService();
        $adminExpressOrderService = new AdminExpressOrderService(new ExpressOrderModel());
        $order = Order::query()->with(['logisticsApply', 'expressOrders', 'shippingAddress', 'expressLine'])
            ->findOrFail($id);

        $expressOrders = $order->expressOrders;
        if (empty($expressOrders)) {
            $trackInfo = [];
        } else {
            //取最后一个包裹
            $packageSn = $order->expressOrders->sortByDesc('id')->value('package_sn');

            if ($packageSn) {
                //查询并且更新物流轨迹状态
                $trackListData = $adminExpressOrderService->queryTracking(['packages' => [$packageSn]], true);
            }

            $trackInfo = $trackListData[0] ?? [];
        }

        //处理发货国家
        $shipperCountryCode = $trackInfo->track_info['shipping_info']['shipper_address']['country'] ?? '';
        if ($shipperCountryCode) {
            $shipperCountry = WorldCountries::query()->with(['countryCn'])->where('code', strtolower($shipperCountryCode))->first();

            $shipperCountryName = !empty($shipperCountry) ? ($authService->getAreaCodeName($shipperCountry)) : __('中国');
        } else {
            $shipperCountryName = __('中国');
        }

        $data = [
            'id'                => $order->id,
            'order_id'          => $order->order_id,
            'way_bill_number'   => $order->logisticsApply->way_bill_number,
            'track_status'      => $trackInfo->status ?? 1,
            'track_status_name' => ExpressOrderTrackingModel::statusList()[$trackInfo->status ?? 1],
            'express_line_name' => $order->expressLine->name ?? '',
            'shipping_country'  => $shipperCountryName,
            'delivery_country'  => $order->shippingAddress->country ?? '',
            'latest_sync_time'  => $trackInfo->track_info['latest_sync_time'] ?? '-',
            'track_list'        => $trackInfo->track_info['track_list'] ?? [],
        ];

        Cache::put($cacheKey, $data, 3600);
        return $data;
    }


}
