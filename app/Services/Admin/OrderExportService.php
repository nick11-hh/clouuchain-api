<?php

/**
 * @Author: h9471
 * @Created: 2020/2/14 11:47
 */

namespace App\Services\Admin;

use App\Helper\CurrencyConverter;
use App\Jobs\Export\OrderExport;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\ExcelExport;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\OrderLineItem;
use App\Models\Shipment;
use App\Services\Traits\OrderTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exceptions\AccidentException;

class OrderExportService extends BaseService
{
    use OrderTrait;

    public array $orderIds = [];

    protected int $withUserData = 1;

    protected int $withDetailData = 1;

    public $filterRules = [
        'allLineItems:title'            => ['like', 'product_name'], //订单商品名称
        'allLineItems:sku'              => ['=', 'sku'], //订单商品sku
        'shippingAddress:country_code'  => ['=', 'country'], //收件人国家
        'shop_id'                       => ['=', 'shop_id'], //店铺ID
        //'created_at'                    => ['between', ['begin_date', 'end_date']], //订单创建时间
        //'order_status'                  => ['in', 'status'], //订单状态
        'platform'                      => ['=', 'platform'], //平台
        'sku_status'                    => ['=', 'sku_status'], //sku状态：0-单sku单数 1-单sku多数 2-多sku
        'logistics_provider_code'       => ['=', 'logistics'], //物流公司代码 yuntu
        'is_change'                     => ['=', 'is_change'], //是否更换物流
        'change_status'                 => ['in', 'change_status'], //换单状态：0-获取新单号 1-待打单 2-发货失败 3-发货成功
        'order_type'                    => ['=', 'order_type'], //订单类型 1-代发 2-备货
        'customer_id'                   => ['=', 'customer_id'], //订单所属客户
        'warehouse_id'                  => ['=', 'warehouse_id'], //仓库ID
        'express_line_id'               => ['=', 'express_line_id'], //物流渠道
        'is_shipping'                   => ['=', 'is_shipping'], //是否发货
        'staff_id'                      => ['=', 'staff_id'], //员工
        'expressOrders.tracking:status' => ['=', 'tracking_status'], //轨迹状态
        'financial_status'              => ['=', 'financial_status'], //财务状态 0-未支付 1-已支付 2-补收费用 3-部分退款 4-全额退款
        'is_disable'                    => ['=', 'is_disable'], //禁止/恢复 处理
        'fulfillment_platform'          => ['=', 'fulfillment_platform'], //履单系统
        'fulfillment_push_status'       => ['=', 'fulfillment_push_status'], //履单推送状态
        'custom:customer_number'        => ['=', 'customer_number'], //客户编号
        'custom:group_id'               => ['=', 'customer_group_id'], //客户分组
        'refund_type_str'               => ['find', 'refund_type'], //退款类型
        'logistics_status'              => ['=', 'logistics_status'],
        'stock_status'                  => ['=', 'stock_status']
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(OrderModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function export(): bool
    {
        validator($this->formData, [
            'export_type' => 'required|int',
            'ids' => 'sometimes|array',
        ], [], [
            'export_type' => '订单导出类型',
            'ids' => '订单ID',
        ])->validate();

        $fileName = 'Orders_' . Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.xlsx';

        $data = $this->getExportData();

        self::checkCount($data, 'orderExport');

        $detailData = $this->withDetailData ? $this->getOrderDetailData() : [];

        /** @var ExcelExport $excelExport */
        $excelExport = ExcelExport::query()->create([
            'type' => ExcelExport::TYPE_ORDER,
            'name' => $fileName,
            'url' => '',
        ]);

        dispatch(new OrderExport($excelExport, $data, $detailData))->onQueue('export');

        return true;
    }



    /**
     * 列表导出
     *
     * @return bool
     */
    public function orderExport(array $shipmentIds = null, int $templateId = null)
    {
        $sn = $shipmentIds ? 'SH_' : null;

        $fileName = 'Orders_' . $sn . Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.xlsx';

        $data = $this->getExportData($shipmentIds);
        self::checkCount($data, $templateId, 'orderExport');

        $detailData = $this->withDetailData ? $this->getOrderDetailData() : [];

        $userData = $this->withUserData ? $this->getOrdersUserData() : [];

        /** @var ExcelExport $excelExport */
        $excelExport = ExcelExport::query()->create([
            'type' => $shipmentIds ? ExcelExport::TYPE_SHIPMENT : ExcelExport::TYPE_ORDER,
            'name' => $fileName,
            'url' => '',
        ]);

        dispatch(new OrderExport($excelExport, $data, $detailData, $userData, $templateId))->onQueue('export');

        return true;
    }

    /**
     * @return array
     */
    protected function getExportData(array $params = []): array
    {
        $this->query->with(
            [
                'shippingAddress',
                'shop',
                'packages.logisticsApply',
                'channel:id,name,tail_course',
                'purchasePlan.purchase',
                'expressLine:id,name',
                'platformFulfillments'
            ]
        );

        if(isset($this->formData['export_type']) && $this->formData['export_type'] == 2) {
            $ids = $this->formData['ids'] ?? [];
            $this->query->when($ids, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            });
        }
        $this->setFilterRules();
        $this->setFilter()->setOrderBy();
        $this->queryCondition();

        $orderData = [];
        $this->query->chunk(100, function ($orders) use (&$orderData) {
            foreach ($orders as $order) {
                $this->orderIds[] = $order->id ?? 0;

                $orderPrice = $order->current_total_price ?? '';
                $orderCurrency = $order->currency ?? '';

                $logisticsFee = $order->order_one_price === Order::ONE_PRICE_OPEN ? $order->sku_logistics_fee : $order->logistics_fee;
                $vendorPrice = $order->vendor_price ?? 0;
                $favourablePrice = $order->favourable_price ?? 0;
                $otherSupplementPrice = $order->other_supplement_price ?? 0;
                $supplementPrice = $order->supplement_price ?? 0;
                $refundPrice = $order->refund_price ?? 0;

                $platform = $order->platform ?? '';

                //订单总报价=产品报价+物流报价+其他金额-优惠金额
                $vendorTotalPrice = (float) ($vendorPrice + $logisticsFee + $otherSupplementPrice - $favourablePrice);
                $vendorTotalPrice = number_format($vendorTotalPrice, 2, '.', '');
                $package = $order->packages[0] ?? null;
                $platformLogisticsNumber = $order->platformFulfillments->pluck('tracking_number')->toArray();
                $orderData[] = [
                    'order_sn' => $order->order_id ?? '', //订单号
                    'platform' => $platform, //站点
                    'shop_name' => $order->shop->shop_name ?? '', //卖家
                    'name' => $order->name ?? '', //订单序列号
                    'current_total_price' => $orderPrice .' '. $orderCurrency, //订单价格
                    'status' => Order::getStatusName($order->order_status), //状态
                    'country' => $order->shippingAddress->country ?? '', //国家
                    'channel_name' => $order->expressLine->name ?? '', //物流渠道
                    'way_bill_number' => $package->logisticsApply->way_bill_number ?? '', //物流单号
                    'vendor_price' => $order->order_one_price === Order::ONE_PRICE_OPEN ? ($vendorPrice + $logisticsFee) : $vendorPrice, //产品总报价(USD)
                    'logistics_fee' => $logisticsFee, //物流报价(USD)
                    'favourable_price' => $favourablePrice, //优惠金额(USD)
                    'other_supplement_price' => $otherSupplementPrice, //其他金额(USD)
                    'vendor_total_price' => $vendorTotalPrice, //报价总价(USD)
                    'supplement_price' => $supplementPrice, //补收金额(USD)
                    'refund_price' => $refundPrice, //退款金额(USD)
                    'total_price' => (float) ($vendorTotalPrice + $supplementPrice) - (float)$refundPrice, //订单总金额(USD)
                    'system_remark' => $order->system_remark ?? '', //系统备注
                    'warehouse_remark' => $order->warehouse_remark ?? '', //仓库备注
                    'purchase_sn' => $order->purchasePlan[0]->purchase[0]->order_sn ?? '', //关联采购单
                    'created_at' => (string)($order->created_at ?? ''), //下单时间
                    'paymented_at' => $order->paymented_at ?? '', //付款时间
                    'commited_at' => $order->commited_at ?? '', //提交时间
                    'cancelled_at' => $order->cancelled_at ?? '', //订单取消时间
                    'first_name' => $order->shippingAddress->first_name ?? '', //收货地址-名
                    'last_name' => $order->shippingAddress->last_name ?? '', //收货地址-姓
                    'shipping_address_country' => $order->shippingAddress->country ?? '', //收货地址-国家
                    'shipping_address_company' => $order->shippingAddress->company ?? '', //收货地址-公司/短地址
                    'shipping_address_phone' => $order->shippingAddress->phone ?? '', //收货地址-手机号
                    'shipping_address_zip' => $order->shippingAddress->zip ?? '', //收货地址-邮编
                    'shipping_address_province' => $order->shippingAddress->province ?? '', //收货地址-省份
                    'shipping_address_city' => $order->shippingAddress->city ?? '', //收货地址-城市
                    'shipping_address_address1' => $order->shippingAddress->address1 ?? '', //收货地址-地址1
                    'shipping_address_address2' => $order->shippingAddress->address2 ?? '', //收货地址-地址2
                    'shipping_address_tax' => $order->shippingAddress->tax ?? '', //收货地址-税号
                    'platform_logistics_number' => implode(PHP_EOL, $platformLogisticsNumber)
                ];
            }
        });

        return $orderData;
    }

    /**
     * @return array
     */
    protected function getSinglePackageExportData(int $shipmentId = null)
    {
        $this->query->with(
            [
                'user',
                'expressLine:id,name',
                'packages.details',
                'packages.prop',
            ]
        )->withCount('packages')
            ->whereNull('parent_id');

        $this->setOrderFilter();

        $this->query->when($shipmentId, function ($query) use ($shipmentId) {
            $query->where('shipment_id', $shipmentId);
        });

        $this->setFilter()->setOrderBy();

        $orderData = [];
        $this->query->chunk(100, function ($orders) use (&$orderData) {
            $count = 0;
            $preOrder = '';
            foreach ($orders as $order) {
                $this->orderIds[] = $order['id'];
                if (isset($order['packages'][0])) {
                    foreach ($order['packages'][0]['details'] as $detail) {
                        /** @var Order $tmp */
                        $tmp = clone $order;
                        $tmp['props'] = $order['packages'][0]['prop']->pluck('cn_name')->join(' ');
                        $tmp['brand'] = $detail['brand'] ?? '';
                        $tmp['name'] = $detail['name'] ?? '';
                        $tmp['price'] = $detail['unit_price'] / 100;
                        $tmp['spec'] = $detail['spec'];
                        $tmp['qty'] = $detail['qty'];
                        $tmp['package_remark'] = $order['packages'][0]['remark'];
                        $tmp['counter'] = $tmp->order_sn === $preOrder ? '' : ++$count;

                        $preOrder = $tmp->order_sn;
                        $order = $tmp;
                        $orderData[] = [
                            $tmp['counter'],
                            $order['order_sn'],
                            $tmp['props'],
                            $tmp['brand'] ?? '',
                            $tmp['name'] ?? '',
                            $tmp['price'],
                            $tmp['spec'],
                            $tmp['qty'],
                            $order['address']['receiver_name'],
                            $order['address']['phone'] . "\t",
                            $order['address']['province'] ?? '',
                            $order['address']['city'] ?? '',
                            $order['address']['district'] ?? '',
                            $order['address']['address'] ?? '',
                            $order['address']['id_card'] ?? '',
                            $tmp['package_remark'] ?? '',
                        ];
                    }
                }
            }
        });

        return $orderData;
    }


    protected static function checkCount(array $data, $source = 'normal')
    {
        $count = count($data);
        info('导出数量计算', ['source' => $source, 'count' => $count]);

        throw_if($count > 8000,
            new AccidentException('当前导出订单数量过多，请缩小搜索范围', Code::OPERATE_FAIL)
        );
    }




    /**
     * @param int|null $id
     * @return string
     */
    protected function getUserRealAddress($address = [])
    {
        if (!$address) {
            return '';
        }

        return sprintf(
            '%s %s %s %s %s',
            $address['address'] ?? '',
            $address['door_no'] ?? '',
            $address['area']['name'] ?? '',
            $address['street'] ?? '',
            $address['city'] ?? '',
        );
    }

    /**
     * @param int|null $id
     * @return string
     */
    protected function getUserprofile($address)
    {
        if (!$address) {
            return '';
        }

        return sprintf(
            '%s %s %s %s',
            $address['address'] ?? '',
            $address['door_no'] ?? '',
            $address['street'] ?? '',
            $address['city'] ?? '',
        );
    }

    protected function areaAndSub($address)
    {
        $areaName = $subAreaName = '';
        if(!empty($address['area']) && is_array($address['area'])){
            $areaName = $address['area']['name'];
        }

        if(!empty($address['sub_area']) && is_array($address['sub_area'])){
            $subAreaName = $address['sub_area']['name'];
        }

        return implode(' ', [$areaName, $subAreaName]);
    }

    /**
     * @return array
     */
    public function getOrderDetailData(): array
    {
        $items = OrderLineItem::query()
            ->with([
                'shopOrder:id,order_id,currency,order_one_price,name',
                'mapping.goodsSku.goods',
                'shopOrder.shippingAddress',
                'shopOrder.packages.logisticsApply'
            ])
            ->whereIn('order_id', $this->orderIds)
            ->orderBy('order_id', 'DESC')
            ->get();

        return $items->map(function ($item) {
            $price = $item->price ?? '';
            $currency = $item->shopOrder->currency ?? '';

            $productOnePrice = '';//产品一口价
            //开启产品一口价
            if ($item->shopOrder->order_one_price === 1) {
                $productOnePrice = $item->quote_price + $item->logistics_fee;
                $productOnePrice = number_format($productOnePrice, 2);
            }

            $package = $item->shopOrder->packages[0] ?? null;
            return [
                $item->shopOrder->order_id ?? '', // 订单号
                $item->shopOrder->name ?? '', // 订单编号
                $item->shopOrder->shippingAddress->country ?? '', // 国家
                $package->logisticsApply->way_bill_number ?? '', // 物流单号
                $item->name ?? '', // 品名
                $item->variant_title ?? '', // 规格
                $item->sku ?? '', // SKU
                $item->mapping->goodsSku->sku_id ?? '' , //关联本地SKU
                $item->quantity ?? '', // 数量
                $price . ' ' . $currency, // 产品原始价格
                $item->quote_price ?? '', // 产品报价(USD)
                $productOnePrice, //产品一口价(USD)
                $item->mapping->goodsSku->goods->goods_name ?? '', //产品一口价(USD)
                $item->mapping->goodsSku->lenght ?? '',
                $item->mapping->goodsSku->width ?? '',
                $item->mapping->goodsSku->height ?? '',
                $item->mapping->goodsSku->weight ?? '',
                $item->mapping->goodsSku->spec_name ?? ''
            ];
        })->toArray();
    }

    /**
     * @return array
     */
    protected function getPPOrderDetailData(): array
    {
        return ShipmentService::getPPOrderDetailData(
            $this->orderIds,
            true
        )->groupBy(fn($g) => $g->purchase_order_goods_id)
            ->map(function ($goods) {
                $pg = $goods->first()->pGoods;
            return [
                    $pg->number ?: '',
                    $pg->cn_name ?: '',
                    $pg->en_name ?: '',
                    $pg->barcode ?: '',
                    $goods->sum(fn($g) => $g->pack_quantity) ?? 0
            ];
        })->values()
            ->all();
    }


    /**
     * @return array
     */
    public function getOrdersUserData()
    {
        /** @var Collection<int, Collection> $userOrders */
        $userOrders = Order::with(
            [
                'user:id,name',
                'packages:id,order_id,length,width,height',
            ]
        )->whereIn('id', $this->orderIds)
            ->withCount(['packages'])
            ->get()->groupBy(function ($order) {
                return $order->user_id;
            });

        $data = [];
        foreach ($userOrders as $userId => $orders) {
            $data[] = [
                'user_id' => $userId,
                'username' => $orders->first()->user->name,
                'order_count' => $orders->count(),
                'weight' => $orders->sum->actual_weight,
                'volume' => $orders->sum(function ($order) { //立方米
                    return $order->packages->sum(function ($package) {
                        return ($package['length'] / 100 * $package['width'] / 100 * $package['height'] / 100) / 1000000;
                    });
                }),
                'value' => $orders->sum(function ($order) {
                    return $order->value;
                }),
                'expect_payment_fee' => $orders->sum->actual_payment_fee,
                'payment_fee' => $orders->sum->originPayAmount,
                'added_value_amount' => $orders->sum->value_added_amount,
            ];
        }

        return $data;
    }

    /**
     * @param array $headers
     * @return void
     */
    protected function setAdditionData(array $headers)
    {
        foreach (['user_aggregated_data', 'package_detail_data'] as $key) {
            $header = collect($headers)->firstWhere('id', $key);

            if ($header && $header['checked'] === '0') {
                match ($key) {
                    'user_aggregated_data' => $this->withUserData = 0,
                    'package_detail_data' => $this->withDetailData = 0,
                };
            }
        }
    }

    protected function mapOrderService(Collection $services)
    {
        return $services->map(function ($service) {
            return sprintf('%s %s ', $service->name, $service->price / 100);
        })->join("\t");
    }
}
