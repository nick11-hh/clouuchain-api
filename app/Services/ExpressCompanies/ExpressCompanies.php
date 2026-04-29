<?php

namespace App\Services\ExpressCompanies;

use App\Jobs\FulfillmentOrderJob;
use App\Jobs\FulfillmentOrderJobV2;
use App\Lib\Code;
use App\Models\Country;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsChannelModel;
use App\Models\LogisticsTrackInfoModel;
use App\Models\Order;
use App\Models\ShopOrderExpressOrderMappingsModel;
use App\Models\ShopTax;
use App\Models\SystemConfig;
use App\Services\Base\PackageBaseService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;

class ExpressCompanies
{
    public $providers;
    public function __construct($provider)
    {
        $this->providers = Factory::create($provider);
    }

    /**
     * 运单申请
     * @return void
     */
    public function place($package, $logisticsApply)
    {
        $this->providers->place($package, $logisticsApply);
        $sync_waybill_number = SystemConfigBaseService::getConfigValue('sync_waybill_number');
        info('运单申请结果_执行履单', ['id' => $package->id, 'sync_waybill_number' => $sync_waybill_number, 'order_status' => $package->status]);
        if($sync_waybill_number == 1 && $package->logistics_status === Order::LOGISTICS_APPLY_SUCCESS) {
            (new PackageBaseService($package))->packagePlatformDelivery();
        }
    }

    /**
     * 获取运输方式
     * @return void
     */
    public function channels($id)
    {
        try {
            $res = $this->providers->channels();

            foreach ($res as $item) {
                LogisticsChannelModel::query()
                                     ->updateOrCreate([
                                         'express_companies_id' => $id,
                                         'code' => $item['code'],
                                     ], [
                                         'name' => $item['name'],
//                                         'enable' => LogisticsChannelModel::ENABLE, //默认启用
                                         'enable' => LogisticsChannelModel::DISABLE, //默认禁用
                                     ]);
            }

            return $res;
        } catch (Exception $e) {
            throw new AccidentException($e->getMessage());
        }
    }

    /**
     * 获取面单
     * @return bool
     */
    public function getLabel($sn, LogisticsApplyModel $logisticsApply)
    {
        $url = $this->providers->getLabel($sn, $logisticsApply);
        info('获取面单', [$url]);

        if(!$url) {
            return false;
        }

        //判断标签是否为url
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $label = file_get_contents($url);
        } else {
            $label = base64_decode($url);
        }

        $time = date('ymdHis');
        $fileName = "/{$sn}_{$time}.pdf";


        Storage::disk()->put('admin'.$fileName, $label);

        $labelUrl = config('app.url').'/storage/admin'.$fileName;

        info('面单url', [$labelUrl]);

        $logisticsApply->update(['label_url' => $labelUrl, 'remark' => '']);

        return true;
    }

    /**
     * 轨迹查询
     * @return void
     */
    public function tracking($id)
    {
        $order = Order::with('logisticsApply')->where('id', $id)->first();

        if(!$order) {
            throw new AccidentException('操作失败，订单不存在', Code::OPERATE_FAIL);
        }

        if($order->logistics_status !== Order::LOGISTICS_APPLY_SUCCESS) {
            throw new AccidentException('操作失败，请先申请运单号', Code::OPERATE_FAIL);
        }

        $sn = $order->logisticsApply->way_bill_number;
        $res = $this->providers->tracking($sn);

        if(!empty($res['Item'])) {
            $record = [
                'order_id'               => $order->order_id,
                'country_code'           => $res['Item']['CountryCode'],
                'waybill_number'         => $res['Item']['WayBillNumber'],
                'tracking_number'        => $res['Item']['TrackingNumber'],
                'provider_name'          => $res['Item']['ProviderName'],
                'provider_telephone'     => $res['Item']['ProviderTelephone'],
                'provider_site'          => $res['Item']['ProviderSite'],
                'pod'                    => $res['Item']['POD'],
                'created_by'             => $res['Item']['CreatedBy'],
                'package_state'          => $res['Item']['PackageState'],
                'interval_days'          => $res['Item']['IntervalDays'],
                'order_tracking_details' => $res['Item']['OrderTrackingDetails'],
            ];

            $trackInfo = LogisticsTrackInfoModel::where('order_id', $order->order_id)->first();
            if($trackInfo) { // 记录已存在，则进行更新
                LogisticsTrackInfoModel::where('id', $trackInfo->id)->update($record);
            } else {
                LogisticsTrackInfoModel::create($record);
            }
        }

        return true;
    }

    public function getDsConsignment($way_bill_number, $order, $order_id)
    {
        return $this->providers->getDsConsignment($way_bill_number, $order);
    }

    public function getShopTax($order)
    {
        //使用客户税号
        $useClientTax = SystemConfigBaseService::getConfigValue(SystemConfig::USE_CLIENT_TAX);
        if ($useClientTax) {
            return $order->shop->tax ?? '';
        }

        $countryCode = strtoupper($order->shippingAddress->country_code);
        $tax = '';

        //欧盟
        if (in_array($countryCode, Country::EuropeanUnionMemberStates())) {
            $tax = $order->shop->european_union_tax ?? '';
        }

        //英国
        if ($countryCode === 'GB') {
            $tax = $order->shop->united_kingdom_tax ?? '';
        }

        //挪威
        if ($countryCode === 'NO') {
            $tax = $order->shop->norway_tax ?? '';
        }

        return $tax;
    }

    public static function getShopTaxV2($order)
    {
        $countryCode = strtoupper($order->shippingAddress->country_code);
        if (in_array($countryCode, Country::EuropeanUnionMemberStates())) {
            $tax = ShopTax::query()->where('shop_id', $order->shop->id)->where('tax_region', ShopTax::REGION_EUROPEAN)->first();
        } elseif ($countryCode === 'GB') {
            $tax = ShopTax::query()->where('shop_id', $order->shop->id)->where('tax_region', ShopTax::REGION_ENGLAND)->first();
        } else {
            $tax = ShopTax::query()->where('shop_id', $order->shop->id)->where('country_code', ShopTax::REGION_OTHER)
                ->where('country_code', $countryCode)->first();
        }
        return $tax;
    }

}
