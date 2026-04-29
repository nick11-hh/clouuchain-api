<?php

namespace App\Services\Admin;

use App\Exceptions\ErrorDataException;
use App\Exports\OrderDianxiaomiExport;
use App\Helper\CurrencyConverter;
use App\Http\Resources\Client\ExpressLinePriceRegionList;
use App\Http\Resources\Client\ExpressLineRegionInfo;
use App\Imports\OrderDianxiaomiImport;
use App\Imports\OrderImport;
use App\Imports\OrderLogisticsUpdateImport;
use App\Jobs\AutoOrderQuoteJob;
use App\Jobs\AutoPullOrderJob;
use App\Jobs\Export\DianXiaoMiOrderExport;
use App\Jobs\FulfillmentOrderJob;
use App\Jobs\LogisticsPlaceJob;
use App\Jobs\LogisticsPlaceJobV2;
use App\Jobs\SendEmailJob;
use App\Jobs\UpdateTrackingJob;
use App\Lib\Code;
use App\Mail\MailConfig;
use App\Models\BalanceRecord;
use App\Models\ChargeTypesModel;
use App\Models\CompanyExpressModel;
use App\Models\Country;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\CustomGroup;
use App\Models\CustomsQuoteConfig;
use App\Models\EmailTemplate;
use App\Models\ExcelExport;
use App\Models\ExchangeRateModel;
use App\Models\ExpressCompany;
use App\Models\ExpressLineModel;
use App\Models\ExpressLinePrice;
use App\Models\ExpressLineQuoteModel;
use App\Models\ExpressLineRegion;
use App\Models\ExpressLineRegionPostcodeArea;
use App\Models\ExpressLineRegionPostcodeAreaModel;
use App\Models\ExpressOrderModel;
use App\Models\GoodsSku;
use App\Models\HandMovementModel;
use App\Models\InboundOrder;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsChannelModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\OrderDeclarationModel;
use App\Models\OrderItemMapping;
use App\Models\OrderItemStock;
use App\Models\OrderLineItem;
use App\Models\OrderPackingMaterialsModel;
use App\Models\OrderShippingAddress;
use App\Models\OrderThirdPartyFulfillmentLogs;
use App\Models\OutboundOrder;
use App\Models\OutboundShopOrderRelate;
use App\Models\Package;
use App\Models\PlatformVirtualSku;
use App\Models\PurchaseOrdersModel;
use App\Models\PurchasePlan;
use App\Models\QuotationRecordModel;
use App\Models\RemoteType;
use App\Models\ShopModel;
use App\Models\ShopOrderAbnormal;
use App\Models\ShopOrderLogs;
use App\Models\SkuQuotationGroupModel;
use App\Models\Stock;
use App\Models\StockChangeLogs;
use App\Models\StockLockLog;
use App\Models\SystemConfig;
use App\Models\ThirdPartyWarehouseConfig;
use App\Models\WarehouseAddress;
use App\Rules\CanadianPostalCodeRange;
use App\Services\ApiResponseService;
use App\Services\AutoOrderPayment;
use App\Services\BarcodeService;
use App\Services\Base\BalanceService;
use App\Services\Base\CommissionService;
use App\Services\Base\OrderBaseService;
use App\Services\Base\OrderQuoteService;
use App\Services\Base\PackageBaseService;
use App\Services\Base\StockService;
use App\Services\PlatformShop\DataService\OrderDataService;
use App\Services\PlatformShop\PlatformShopService;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use App\Services\Traits\OrderTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\HigherOrderBuilderProxy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Services\Shopify\OrderService as ShopifyOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\PdfToImage\Pdf;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Imports\Admin\OrderImport as AdminOrderImport;
use App\Imports\Admin\OrderImportMain as AdminOrderImportMain;
use Illuminate\Support\Facades\Cache;
use App\Lib\Platform;
use App\Exceptions\AccidentException;

class OrderService extends BaseService
{
    use OrderTrait;

    protected bool $isStation = false;

    protected bool $withPostArea = false;

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
        'stock_status'                  => ['=', 'stock_status'],
        'platform_status'               => ['=', 'platform_status']
    ];

//     public $orderBy = [
//         'created_at' => 'desc'
//     ];

    public function __construct(OrderModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function test()
    {
//        $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
//            ->value('custom_exuoteConfig::query()->update([
////            'product_quote_default_profit_rate' => 20,
////            'freight_quote_default_profit_rate' => 20,
////            'product_quote_review_profit_rate' => 20,
////            'freight_quote_review_profit_rate' => 20,
////            'updated_at' => date("Y-m-d H:i:s"),
////        ]);change_rate');
        $this->query->where('created_at', '>', '2025-11-01 000:00:00')->update([
            'exchange_rates' => 6.9,
            'updated_at' => date("Y-m-d H:i:s"),
        ]);
        return true;
    }

    public function index()
    {
        $this->query->with([
            'allLineItems.mapping.goodsSku',
            'shippingAddress:id,order_id,country,country_code,zip',
            'shop:id,shop_name,shop_url',
            'channel:id,name,tail_course',
            'expressLine:id,name,en_name',
            'staff:id,name',
            'purchasePlan.purchase',
            'abnormal',
            'chargeType',
            'custom',
            'packages.logisticsApply',
            'platformFulfillments'
        ]);

        $this->queryCondition();

        $this->orderByQuery();

        return parent::index();
    }

    public function batchMatchingLogistics($params)
    {
        $params = [
            [
                'order_id' => '6723471179946_mate',
                'country_code' => 'CN', 0.100, '', 1
            ],
            ['6723469770922_mate', 'CN', 7.000, '', 1],
            ['6683095564458_mate', 'CN', 1.200, '', 1],
            ['6662797557930_mate', 'CN', 0.300, '', 1],
        ];
        return true;
    }

    /**
     * 获取订单id合集
     * @return \Illuminate\Support\Collection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/27 16:49
     */
    public function getIds()
    {
        $this->query->with([
            'allLineItems',
            'shippingAddress:id,order_id,country',
            'shop:id,shop_name,shop_url',
            'channel:id,name,tail_course',
            'expressLine:id,name,en_name',
            'staff:id,name',
            'purchasePlan.purchase',
            'expressOrders:id,package_sn',
            'expressOrders.tracking',
        ]);

        $this->queryCondition();

        $this->setFilter();

        return $this->query->pluck('id');
    }


    public function orderByQuery(): bool
    {
        if (!empty($this->formData['order_by'])) {
            $this->query->orderBy($this->formData['order_by'], $this->formData['sort'] ?? 'desc');
        }

        return true;
    }

    public function orderQuotationProcess($data)
    {
        if (empty($data)) {
            return $data;
        }

        $expressLineId = 0;

        //获取国家ID
        $shippingAddressData = array_column($data, 'shipping_address');
        $countryCodeData = array_column($shippingAddressData, 'country_code');
        $countryData = Country::query()->whereIn('code', $countryCodeData)->pluck('id', 'code')->toArray();

        $countryList = [];
        foreach ($countryData as $code => $id) {
            $countryList[strtoupper($code)] = $id;
        }

        info('一客一价-1：$countryList', $countryList);

        //获取所有产品id
        $itemsData = array_column($data, 'line_items');
        $itemIds = [];
        foreach ($itemsData as $items) {
            foreach ($items as $item) {
                //过滤软删除的产品
                if ((isset($item['is_delete']) && $item['is_delete'])) {
                    continue;
                }

                $mapping = $item['mapping'] ?? [];

                //产品没有关联sku并且不存在sku关联关系的情况下
                if (empty($item['goods_sku_id']) && empty($mapping)) {
                    continue;
                }

                if (empty($item['goods_sku_id']) && !empty($mapping)) {
                    $item['goods_sku_id'] = $item['mapping']['goods_sku_id'] ?? 0;
                }

                $itemIds[] = $item['goods_sku_id'];
            }
        }

        info('一客一价-2：$itemIds', $itemIds);


        //根据产品id查询所有的报价信息
        $skuQuotationData = [];
        if ($itemIds) {
            $skuQuotationList = SkuQuotationGroupModel::query()->with([
                'skuQuotationGroupAttr' => function ($query) {
                    $query->where('is_new', 1);//只获取最新的报价
                }
            ])->whereIn('sku_id', $itemIds)->get()->toArray();

            foreach ($skuQuotationList as $quotation) {
                foreach ($quotation['sku_quotation_group_attr'] as $item) {
                    //组装报价信息 skuID-客户ID-国家ID
                    $skuQuotationData[$quotation['sku_id']. '-'  . $quotation['custom_id'] . '-' . $quotation['country_id']][] = [
                        'express_line_id' => $quotation['express_line_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ];
                }
            }
        }

        info('一客一价-3：$skuQuotationData', $skuQuotationData);

        foreach ($data as $key => $value) {
            $vendorPrice = 0;
            $quotationCount = 0;
            $data[$key]['vendor_price'] = 0;

            foreach ($value['line_items'] as $k => $item) {
                //初始化每个产品报价 所以下面的异常使用continue而非break
                $value['line_items'][$k]['quotation_price'] = 0;

                //不存在报价信息
                if (empty($skuQuotationData)) {
                    continue;
                }

                //只计算未报价 报价中 已报价的订单
//                if (!in_array($value['order_status'], [0, 1, 2, 3])) {
//                    continue;
//                }

                //再查询一次sku关联关系
                $mapping = $item['mapping'] ?? [];
                if (empty($item['goods_sku_id']) && !empty($mapping)) {
                    $item['goods_sku_id'] = $item['mapping']['goods_sku_id'] ?? 0;
                }

                $skuId = $item['goods_sku_id'] ?? 0;
                //未关联sku
                if (empty($skuId)) {
                    continue;
                }

                $customId = $value['customer_id'] ?? 0;

                $countryCode = $value['shipping_address']['country_code'] ?? '';
                $countryId = $countryList[$countryCode] ?? 0;
                //未设置收件人国家
                if (empty($countryId)) {
                    continue;
                }

                //sku报价参数
                $skuQuotation = [];

                //sku报价 所有客户&&所有国家 优先级：4
                $skuQuotationWithAllKey = $skuId . '-0-0';
                if (isset($skuQuotationData[$skuQuotationWithAllKey])) {
                    $skuQuotation = $skuQuotationData[$skuQuotationWithAllKey];
                }

                //sku报价 所有客户&&指定国家 优先级：3
                $skuQuotationWithAllCustomerKey = $skuId . '-0-' . $countryId;
                if (isset($skuQuotationData[$skuQuotationWithAllCustomerKey])) {
                    $skuQuotation = $skuQuotationData[$skuQuotationWithAllCustomerKey];
                }

                //sku报价 指定客户&&所有国家 优先级：2
                $skuQuotationWithAllCountryKey = $skuId . '-' . $customId . '-0';
                if (isset($skuQuotationData[$skuQuotationWithAllCountryKey])) {
                    $skuQuotation = $skuQuotationData[$skuQuotationWithAllCountryKey];
                }

                //sku报价 指定客户&&指定国家 优先级：1
                $skuQuotationKey = $skuId . '-' . $customId . '-' . $countryId;
                if (isset($skuQuotationData[$skuQuotationKey])) {
                    $skuQuotation = $skuQuotationData[$skuQuotationKey];
                }

                if ($skuQuotation) {
                    $skuExpressLineId = $skuQuotation[0]['express_line_id'];
                    //组装成数量对应价格并按数量正序排序 方便计算区间报价
                    $skuQuotation = array_column($skuQuotation, 'price', 'quantity');
                    ksort($skuQuotation);

                    $quotationPrice = 0;
                    foreach ($skuQuotation as $quantity => $price) {
                        if ($item['quantity'] >= $quantity) {
                            $quotationPrice = $price;
                        }
                    }

                    if ($quotationPrice) {
                        $vendorPrice += ($quotationPrice * $item['quantity']);
                        $value['line_items'][$k]['quotation_price'] = $quotationPrice;
                        $quotationCount++;
                        $expressLineId = $skuExpressLineId;
                    }
                }
            }

            //存在报价 并且所有产品都关联了报价才在列表展示订单报价
            if($vendorPrice > 0 && count($value['line_items']) === $quotationCount) {
                $data[$key]['vendor_price'] = $vendorPrice;
            }

            //批量下单 报价币种 USD
            $currency = 'USD';

            $data[$key]['once_price_express_line_id'] = $expressLineId;
            $data[$key]['status'] = $value['order_status'];
            $data[$key]['original_currency'] = $value['currency'];
            $data[$key]['currency'] = $currency; //报价币种 默认 USD
            $data[$key]['line_items'] = $value['line_items'];
            $data[$key]['status_name'] = Order::getStatusName($value['order_status']);
        }

        return $data;
    }

    /**
     * 订单详情
     * @param $id
     * @return mixed|void
     */
    public function show($id)
    {
        $this->query->with([
            'lineItems.declaration',
            'shippingAddress',
            'shop',
            'lineItems.mapping.goodsSku.goods',
            'handCustoms',
            'logs.admin:id,name',
            'packingMaterials.admin:id,name',
            'expressLine:id,cn_name',
            'staff:id,name',
            'chargeType',
            'logs' => function ($query) {
                $query->orderBy('id', 'desc');
            },
        ]);
        $data = $this->query->where('id', $id)->first();
        $data->remote_type = RemoteTypeService::isRemoteArea($data->shippingAddress->country_code, $data->shippingAddress->city, $data->shippingAddress->zip);
        return $data;
    }

    /**
     * 订单报价信息
     * @param $id
     */
    public function orderQuoteInfo($id)
    {
        $order = $this->model::query()->with(['lineItems.mapping.goodsSku.goods:id,goods_name,cover_image', 'shippingAddress',])->findOrFail($id);

        //店铺对应客户，客户所分配的员工
        $order->customer_staff_id = $order->shop->customer->staff_id ?? 0;
        $order->remote_type = RemoteTypeService::isRemoteArea($order->shippingAddress->country_code, $order->shippingAddress->city, $order->shippingAddress->zip);
//        $quoteData = $this->orderQuotationProcess([$order->toArray()])[0] ?? [];
//        $quoteData['goods_once_price'] = Custom::query()->where('id', $order->customer_id)->value('goods_once_price');
        return $order;
    }

    public function getChannelByOrderId($id)
    {
        $order = $this->model::query()->with(['lineItems', 'shippingAddress', 'shop', 'lineItems.mapping.goodsSku.goods'])->where('id', $id)->first();

        if (empty($order)) {
            throw new AccidentException('订单不存在', Code::OPERATE_FAIL);
        }

        if (empty($order->shippingAddress)) {
            throw new AccidentException('请填写收件人信息', Code::OPERATE_FAIL);
        }

        if (empty($order->shippingAddress->country_code)) {
            throw new AccidentException('请填写收件人国家', Code::OPERATE_FAIL);
        }

        $propIds = [];//产品属性 普通/带电
        $order->lineItems->each(function ($item) use (&$propIds) {
            //获取产品属性
            $goodsSkuId = $item->mapping->goodsSku->id ?? 0;
            if (empty($goodsSkuId)) {
                return false;
            }
            $attributes = LogisticsCustomsDeclarationModel::query()->where('goods_sku_id', $goodsSkuId)->value('attributes');

            //取属性最多的一个产品
            if (is_array($attributes) && (count($propIds) < count($attributes))) {
                $propIds = $attributes;
            }

            return true;
        });

        $country_id = Country::where('code', strtolower($order->shippingAddress->country_code))->value('id');

        if (!$country_id) {
            return [];
        }
        $warehouse = new WarehouseAddressService(new WarehouseAddress);
        $warehouseList = $warehouse->filterList(['country_id' => $country_id]);


        $warehouse_id = $warehouseList[0]['id'] ?? 0;

        $expressPrice = new ExpressPriceService();

        $customer_group = Custom::where('id', $order->customer_id)->value('group_id');

        $expressPriceData =  [
            'country_id' => $country_id,//国家
            'warehouse_id' => $warehouse_id,//仓库
            'weight' => $this->formData['weight'],//重量
            'customer_id' => $order->customer_id ?? 0,//客户
            'customer_group' => $customer_group,//客户分组
            'postcode' => $order->shippingAddress->zip ?? '',//邮编
            'prop_ids' => $propIds,//产品属性
        ];

        return $expressPrice->query($expressPriceData);
    }

    /**
     * 扫描签收-订单详情
     * @return void
     */
    public function scanOrder()
    {
        validator($this->formData, [
            'keyword' => 'required'
        ], [], [
            'keyword' => '采购单号/物流单号'
        ])->validate();

        $this->query->with(['lineItems', 'shippingAddress', 'shop', 'purchaseOrder']);

        $this->query->whereHas('purchaseOrder', function ($query) {
            $query->where('order_sn', 'like', '%' . $this->formData['keyword'] . '%')->orWhere('shipment_number', 'like', '%' . $this->formData['keyword'] . '%');
        });

        return $this->query->get();
    }

    /**
     * 报价
     * @return void
     */
    public function quote()
    {
        validator($this->formData, [
            'params' => 'required|array',
            'params.*.id' => 'required',
            'params.*.price' => 'required'
        ], [], [
            'params' => '报价参数',
            'params.*.id' => '订单id',
            'params.*.price' => '价格'
        ])->validate();

        $orderIds = array_column($this->formData['params'], 'id');
        throw_if(
            $this->model::whereIn('id', $orderIds)->where('order_status', '>', OrderModel::STATUS_ALLOCATED)->first(),
            new AccidentException('操作失败，报价参数中不能有已支付的订单', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            collect($this->formData['params'])->each(function ($item) {
                $this->model::where('id', $item['id'])->update(['vendor_price' => $item['price']]);
            });

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('拒绝报价：' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * 设为已报价
     * @return mixed
     * @throws ValidationException
     * @throws Throwable
     */
    public function setQuote(): mixed
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $fields = [
            'customer_id',
            'order_id',
            'shop_id',
            'vendor_change_price',
            'logistics_fee',
            'order_status',
            'vendor_price',
            'logistics_provider',
            'logistics_provider_code',
            'express_line_id',
        ];

        $orderData = $this->model->select($fields)->whereIn('id', $this->formData['ids'])->get();

        //订单报价-发送邮件通知
        $emailTemplate = EmailTemplate::query()->where('type', EmailTemplate::ORDER_QUOTATION)->where('enabled', 1)->first();

        MailConfig::getEmailConfig();

        foreach ($orderData as $order) {
            if ($order['order_status'] > OrderModel::STATUS_ALLOCATED) {
                throw new AccidentException('操作失败，只有未报价订单才能设置为已报价', Code::OPERATE_FAIL);
            }
            if (empty($order['logistics_provider']) || empty($order['logistics_provider_code']) || empty($order['express_line_id'])) {
                throw new AccidentException('订单未设置报价或未设置物流方式', Code::OPERATE_FAIL);
            }

            //开启邮件配置
            if ($emailTemplate) {
                $order->load(['shop:id,shop_name', 'custom:id,custom_email,main_user_id', 'custom.mainUser:id,custom_id,username']);

                $toEmail = $order->custom->custom_email ?? '';
                if (empty($toEmail)) {
                    continue;
                }

                //物流费用
                $logisticsFee = $order->logistics_fee ?? 0;

                //商品一口价时取sku物流总报价
                if ($order->order_one_price === 1) {
                    $logisticsFee = $order->sku_logistics_fee ?? 0;
                }

                try {
                    $emailParams = [
                        'user_name' => $order->custom->mainUser->username ?? '',
                        'shop_name' => $order->shop->shop_name ?? '',
                        'order_no' => $order->order_id,
                        'vendor_price' => $order->vendor_price, //商品总报价  CNY => USD
                        'logistics_fee' => $logisticsFee, //商品总报价  CNY => USD
                        'favourable_price' => $order->favourable_price, //优惠金额  CNY => USD
                        'other_supplement_price' => $order->other_supplement_price, //优惠金额  CNY => USD
                        'vendor_change_price' => $order->vendor_change_price, //改价  CNY => USD
                    ];

                    //报价金额
                    $emailParams['quoted_amount'] = $emailParams['vendor_price'] + $emailParams['logistics_fee'] - $emailParams['favourable_price'] + $emailParams['other_supplement_price'];
                    $emailParams['quoted_amount'] = number_format($emailParams['quoted_amount'], 2);

                    dispatch(new SendEmailJob('OrderQuotationEmail', $toEmail, $emailParams));
                } catch (\Exception $e) {
                    info('产品报价-发送邮件通知失败', [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'msg' => $e->getMessage()
                    ]);
                }
            }
        }
        return $this->model::whereIn('id', $this->formData['ids'])->update(['order_status' => OrderModel::STATUS_ALLOCATED, 'commited_at' => now()]);
    }

    /**
     * 订单已付款
     * @return array
     * @throws AccidentException
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/18 15:49
     */
    public function setPaymented()
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $ids = $this->formData['ids'];
        $orderCheck = $this->model::whereIn('id', $ids)->where('order_status', '<>', OrderModel::STATUS_ALLOCATED)->exists();
        if ($orderCheck) {
            throw  new AccidentException('操作失败，只有待支付状态的订单才能设置为已付款', Code::OPERATE_FAIL);
        }
        $orderData = $this->model::whereIn('id', $ids)->where('order_status', OrderModel::STATUS_ALLOCATED)
            ->get(['id', 'order_id', 'customer_id']);

        if ($orderData->isEmpty()) {
            return ApiResponseService::success('没有找到符合条件的订单');
        }

        // 4. 批量处理支付，每个订单独立事务
        $results = $this->processBatchPaymentsWithTransaction($orderData);

        // 5. 返回处理结果
        return $this->buildResponse($results);
//        $error = '';
//        $autoOrderPayment = new AutoOrderPayment();
//        $status = true;
//        foreach ($orderData as $order) {
//            try {
//                $autoOrderPayment->autoOrderPaymentProcess($order['customer_id'], $order['id']);
//                $error .= "<p style='color: green'>订单号：{$order['order_id']}-支付成功</p>";
//            } catch (\Throwable $e) {
//                $status = false;
//                $error .= "<p style='color: red'>订单号：{$order['order_id']}---" . $e->getMessage() . "</p>";
//            }
//        }
//        if ($error && $status === false) {
//            return ApiResponseService::success('false', Code::CUSTOM_ERROR, $error);
//        }
//
//        return ApiResponseService::success('');
    }

    /**
     * @param $orders
     * @return array
     */
    protected function processBatchPaymentsWithTransaction($orders)
    {
        $autoOrderPayment = new AutoOrderPayment();
        $successCount = 0;
        $errorMessages = [];

        foreach ($orders as $order) {
            DB::beginTransaction();
            try {
                $autoOrderPayment->autoOrderPaymentProcess($order->customer_id, $order->id);
                DB::commit();
                $successCount++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $errorMessages[] = "订单号：{$order->order_id} --- " . $e->getMessage();
            }
        }

        return [
            'success_count' => $successCount,
            'error_messages' => $errorMessages,
            'total_count' => $orders->count()
        ];
    }

    /**
     * @param $results
     * @return array
     */
    protected function buildResponse($results)
    {
        $message = "成功处理 {$results['success_count']}/{$results['total_count']} 个订单";

        if (!empty($results['error_messages'])) {
            $errorHtml = implode('', array_map(fn($err) => "<p style='color:red'>{$err}</p>", $results['error_messages']));
            return ApiResponseService::success($message, Code::CUSTOM_ERROR, $errorHtml);
        }

        return ApiResponseService::success($message);
    }

    /**
     * 修改物流渠道
     */
    public function setLogistics()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'express_line_id' => 'required|int',
        ])->validate();

        throw_if(
            $this->model::query()->whereIn('id', $this->formData['ids'])->whereNotIn('order_status', [
                Order::STATUS_QUOTE_NO,
                Order::STATUS_QUOTE_ASK,
                Order::STATUS_QUOTED,
            ])->first(),
            new AccidentException('操作失败，未付款的订单才能修改物流渠道', Code::OPERATE_FAIL)
        );

        $orders = $this->model::query()->whereIn('id', $this->formData['ids'])->get();

        $orders->each(function ($order) {
            //统计sku重量
            $weight = 0;
            $mappingNum = 0;
            $order->lineItems->each(function ($item) use (&$weight, &$mappingNum) {
                if (empty($item->mapping)) {
                    return false;
                }

                $mappingNum ++;
                $weight += $item->mapping->goodsSku->weight * $item->quantity;

                return true;
            });

            //只处理所有产品都关联了sku的订单
            if ($mappingNum !== $order->lineItems->count()) {
                return true;
            }

            try {
                //获取订单匹配到的所有渠道
                $this->formData['weight'] = $weight;
                $result = $this->getChannelByOrderId($order->id);
                foreach ($result as $express) {
                    //所选渠道存在结果中时才保存，否则跳过
                    if ($express['id'] === $this->formData['express_line_id']) {
                        $channel = LogisticsChannelModel::with('expressCompanies')->where('code', $express['channel_code'])->first();
                        if (empty($channel)) {
                            continue;
                        }

                        $data = [
                            'express_line_id' => $express['id'],
                            'logistics_provider' => $channel->id,
                            'logistics_fee' => $express['expire_fee'] / 100,
                            'logistics_provider_code' => $channel->expressCompanies->code,
                            'order_one_price' => Order::ONE_PRICE_CLOSE, //暂时不支持订单一口价
                        ];
                        $order->update($data);
                        break;
                    }
                }
            } catch (Exception $e) {
                logger($order->id . ' 设置物流渠道失败：' . $e->getMessage());
            }
            return true;
        });

        return true;
    }

    /**
     * 创建订单的发货项
     * 1、库存有部分货-发货，不足部分下采购单：混合发货
     * 2、只使用库存发货，库存不足抛异常
     * 3、只建采购单，不使用库存
     * @param $order
     * @return bool
     * @throws Exception
     */
    public function createDeliverData($order, $type = 1)
    {
        //使用客户库存的订单，在支付的时候就锁定了库存，所以这里不需要再进行库存校验，直接创建出库单即可
        // if (!empty($order->use_customer_stock)) {
        //     return $this->createOutboundOrder($order);
        // }

        $purchaseOrderData = [
            'shop_order_id' => $order->id,
            'shop_id' => $order->shop_id,
        ];

        $order->lineItems->each(function ($item) use (&$purchaseOrderData, $order, $type) {
            $mapping = OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item['variant_id'])->first();
            if (empty($mapping)) throw new AccidentException('订单未映射本地商品', Code::OPERATE_FAIL);

            //默认使用是“本企业”的库存
            $customerId = 0;

            //使用客户的库存
            if (!empty($order->use_customer_stock)) {
                $type = 2;
                $customerId = $order->customer_id;
            }

            $stock = Stock::query()->where('sku_id', $mapping->goods_sku_id)->where('custom_id', $customerId)->first();
            $item->purchase_quantity = $item->quantity;

            if ($type === 2 && (empty($stock) || $stock->quantity < $item->quantity)) {
                throw new AccidentException('库存不足', Code::OPERATE_FAIL);
            }

            //使用库存
            if (!empty($stock) && $stock->quantity > 0 && $type !== 3) {
                //防止重复锁定
                if ($item->stock ?? null) {
                    $quantity = $item->stock->all_quantity;
                } else {
                    $quantity = $this->orderItemUseStock($order, $item, $stock);
                }

                $item->purchase_quantity = $item->quantity - $quantity;
                $item->stock = $stock;
            }

            // 库存满足发货需求，直接返回
            if ($item->purchase_quantity <= 0) return true;  // 不需要采购

            $goods = [
                'sku_id' => $mapping->goods_sku_id,
                'quantity' => $item->purchase_quantity,
                'order_items' => [
                    [
                        'order_sn' => $order->order_id,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id
                    ]
                ]
            ];
            $purchaseOrderData['goods'][] = $goods;
        });

        if (empty($purchaseOrderData['goods'])) {
            return $this->createOutboundOrder($order);
        }

        $order->order_status = OrderModel::STATUS_WAIT_PRINT_OUT_STOCK;
        $order->save();
        // 创建采购单
        // $purchase = new PurchaseOrderService(new PurchaseOrdersModel);
        // $purchase->createPurchase($purchaseOrderData);

        $planParams = $this->formatPlanData($purchaseOrderData);
        // 创建采购计划
        $purchasePlan = new PurchasePlanService();
        $purchasePlan->store($planParams, $purchaseOrderData);
        return true;
    }

    /**
     * 订单设为有货并创建出库单
     */
    public function createOutboundOrder($order): bool
    {
        if (empty($order)) {
            return false;
        }

        $order->order_status = OrderModel::STATUS_WAIT_PRINT_IN_STOCK;
        $order->save();

        // 创建出库单
        $outboundOrderService = new OutboundOrderService();
        $logisticInfo = $order->logisticsApply;
        $address = $order->shippingAddress;
        $outboundOrderService->createByShopOrder($order, $logisticInfo, $address);
        return true;
    }

    public function formatPlanData($data)
    {
        $params = [
            'id' => 0,
            'remark' => '通过订单自动创建',
            'status' => 1,
            'items' => []
        ];

        $goods = [];
        foreach($data['goods'] as $item) {
            $goods[] = [
                'sku_id' => $item['sku_id'],
                'plan_qty' => $item['quantity'],
                'supplier_id' => $item['supplier_id'] ?? 0,
                'shop_id' => $data['shop_id'],
                'warehouse_id' => $item['warehouse_id'] ?? 0
            ];
        }

        $params['items'] = $goods;

        return $params;
    }

    // public function isStock($order)
    // {
    //     $order->lineItems->each(function ($item) use(&$purchaseOrderData, $order) {
    //         $mapping = OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item['variant_id'])->first();
    //         if (empty($mapping)) throw new AccidentException('订单未映射本地商品', Code::OPERATE_FAIL);
    //
    //         $stock = Stock::query()->where('sku_id', $mapping->goods_sku_id)->first();
    //
    //         return !empty($stock) && $stock->quantity > 0;
    //     });
    // }


    /**
     * @param $item
     * @param $stock
     * @return mixed
     * @throws Exception
     */
    public function orderItemUseStock($order, $item, $stock)
    {
        $quantity = min($item->quantity, $stock->quantity);

        $stockService = new StockService($stock);
        $lockInfo = $stockService->setOperateSn($order->order_id)
            ->autoLockStock($quantity, StockLockLog::SOURCE_ORDER_WAIT_DELIVER);
        foreach ($lockInfo as $value) {
            $data = [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'stock_id' => $value['stock_item']->stock_id,
                'stock_item_id' => $value['stock_item']->id,
                'lock_id' => $value['lock']->id,
                'quantity' => $value['quantity'],
                'all_quantity' => $quantity,
            ];
            OrderItemStock::query()->create($data);
        }

        return $quantity;
    }

    /**
     * @return true
     * @throws Throwable
     * @throws ValidationException
     */
    public function removePrint()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::query()->whereIn('id', $this->formData['ids'])->where('order_status', Order::STATUS_PENDING)
                ->where('logistics_status', '<>', OrderModel::LOGISTICS_APPLY_SUCCESS)->first(),
            new AccidentException('操作失败，只能操作已申请运单的订单', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            $orders = $this->model::with('lineItems')->whereIn('id', $this->formData['ids'])->get();
            // 创建订单发货项
            $i = 1;
            $orders->each(function ($order) use (&$i) {
                $order->order_status = Order::STATUS_APPLY_NUM;
                $order->save();

                $packageIds = $order->packages->pluck('id')->toArray();
                (new PackageService())->moveToStock(['ids' => $packageIds]);
                ShopOrderLogs::addLog([
                    'order_id'      => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_MOVE_TO_DISTRIBUTION,
                    'content'       => '订单移入配货中',
                ]);
            });
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 打印面单
     * @param $id
     * @return \Illuminate\Database\Eloquent\HigherOrderBuilderProxy|mixed
     * @throws Throwable
     */
    public function printLable($id)
    {
        throw_if(
            $this->model::where('id', $id)->whereNotIn('order_status', [
                OrderModel::STATUS_WAIT_PRINT,
                OrderModel::STATUS_DELIVERY_SUCCESS,
                OrderModel::STATUS_DELIVERY_FAILURE,
                OrderModel::STATUS_WAIT_PRINT_IN_STOCK,
                OrderModel::STATUS_WAIT_PRINT_OUT_STOCK
            ])->first(),
            new AccidentException('操作失败，移入配货后才能打印', Code::OPERATE_FAIL)
        );

        $order = $this->model::with('logisticsApply:id,order_id,label_url')->findOrFail($id);

        LogisticsApplyModel::where('id', $order->logisticsApply->id)->increment('print');

        if (empty($order->logisticsApply->label_url)) {
            $express = new ExpressCompaniesService(new CompanyExpressModel);
            if ($express->getLabel($id)) {
                $order->logisticsApply->label_url = LogisticsApplyModel::where('id', $order->logisticsApply->id)->value('label_url');
            }
        }

        return ['label_url' => $order->logisticsApply->label_url];
    }

    public function printLogisticsLable()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('is_disable', Order::IS_DISABLE_YES)->first(),
            new AccidentException('操作失败，存在禁止处理的订单，不允许发货', Code::OPERATE_FAIL)
        );

        $res = $this->model::with('logisticsApply:id,order_id,label_url')
            ->whereIn('id', $this->formData['ids'])
            ->select('id', 'order_id')
            ->get();

        $labelUrls = [];
        // 本地url
        $localUrl = config('app.url');
        // 是否为本地存储
        $isLocalStorage = config('app.local_storage');

        // 创建上下文
        $contextOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            )
        );
        $context = stream_context_create($contextOptions);

        $res->each(function ($item) use (&$labelUrls, $localUrl, $isLocalStorage, $context) {
            if(!empty($item->logisticsApply)) {

                if (empty($item->logisticsApply->label_url)) {
                    $express = new ExpressCompaniesService(new CompanyExpressModel);
                    if ($express->getLabel($item->id)) {
                        $item->logisticsApply->label_url = LogisticsApplyModel::where('id', $item->logisticsApply->id)->value('label_url');
                    }
                }

                // 解析 URL
                $parsed_url = parse_url($item->logisticsApply->label_url);
                // 获取协议和主机名
                $protocol = $parsed_url['scheme'] ?? '';
                $host = $parsed_url['host'] ?? '';

                $host = $protocol . '://' . $host;

                if ($localUrl === $host && !$isLocalStorage) {
                    $headers = get_headers($item->logisticsApply->label_url, 1, $context);
                    if (isset($headers['Location'])) {
                        // 如果 Location 是数组，则获取最后一个重定向的 URL
                        $redirectedUrl = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
                        $labelUrls[] = $redirectedUrl;
                    }
                } else {
                    $labelUrls[] = $item->logisticsApply->label_url;
                }
            }

        });

        return $this->mergePdf($labelUrls);
    }

    public function printSendLable()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('is_disable', Order::IS_DISABLE_YES)->first(),
            new AccidentException('操作失败，存在禁止处理的订单，不允许打印发货单', Code::OPERATE_FAIL)
        );

        $data = Order::with(['logisticsApply', 'lineItems'])->whereIn('id', $this->formData['ids'])->get();

        $lables = [];
        $data->each(function ($item) use (&$lables) {
            $lables[] = $this->generatePdf($item);
        });

        return $this->mergePdf($lables);
    }

    public function parseUrl($label_url)
    {
        // 本地url
        $localUrl = config('app.url');
        // 是否为本地存储
        $isLocalStorage = config('app.local_storage');
        // 解析 URL
        $parsed_url = parse_url($label_url);
        // 获取协议和主机名
        $protocol = $parsed_url['scheme'] ?? '';
        $host = $parsed_url['host'] ?? '';

        $host = $protocol . '://' . $host;

        // 创建上下文
        $contextOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            )
        );
        $context = stream_context_create($contextOptions);

        if ($localUrl === $host && !$isLocalStorage) {
            $headers = get_headers($label_url, true, $context);
            if (isset($headers['Location'])) {
                // 如果 Location 是数组，则获取最后一个重定向的 URL
                return is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
            }
        }

        return $label_url;
    }

    public function printLogisticsSendLable()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('is_disable', Order::IS_DISABLE_YES)->first(),
            new AccidentException('操作失败，存在禁止处理的订单，不允许发货', Code::OPERATE_FAIL)
        );

        $data = Order::with(['logisticsApply', 'lineItems'])->whereIn('id', $this->formData['ids'])->get();

        $lables = [];
        $data->each(function ($item) use (&$lables) {
            if(!empty($item->logisticsApply)) {
                if (!empty($item->logisticsApply->label_url)) {
                    $lables[] = $this->parseUrl($item->logisticsApply->label_url);
                } else {
                    $express = new ExpressCompaniesService(new CompanyExpressModel);
                    if ($express->getLabel($item->id)) {
                        $item->logisticsApply->label_url = LogisticsApplyModel::where('id', $item->logisticsApply->id)->value('label_url');

                        $lables[] = $this->parseUrl($item->logisticsApply->label_url);
                    }
                }
                $lables[] = $this->generatePdf($item);
            }

        });

        return $this->mergePdf($lables);
    }

    public function generatePdf($data)
    {
        $fileName = 'send_label_' . Str::random(8) . '.pdf';
        $items = [];
        $skuNumber = $data->lineItems->count();
        $data->lineItems->each(function ($item) use (&$items) {
            $items[] = [
                'img' => $item->imgs[0] ?? '',
                'name' => $item->title,
                'spec' => $item->variant_title,
                'quantity' => $item->quantity,
                'stock' => '',
            ];
        });

        $total = array_sum(array_column($items, 'quantity'));
        $labelData = [
            'brcode' => BarcodeService::generateCode($data->order_id),
            'name' => $data->name,
            'order_id' => $data->order_id,
            'way_bill_number' => $data->logisticsApply->way_bill_number,
            'print_time' => date('Y-m-d H:i:s'),
            'sku_num' => $skuNumber,
            'total' => $total,
            'remark' => $data->warehouse_remark,
            'items' => $items
        ];

        try {
            $fullPath = Storage::disk('admin_public')->path($fileName);

            \PDF::loadView(
                'labels.send-label', ['data' => $labelData]
            )->setOptions([
                'page-height' => 100,
                'page-width' => 100,
                'dpi' => 300,
                'margin-top' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'margin-right' => 0
            ])
                ->save($fullPath, true);

            return $fullPath;
        } catch (\Throwable $throwable) {
            info('标签生成失败', ['msg' => $throwable->getMessage(), 'file' => $throwable->getFile(), 'line' => $throwable->getLine()]);

            info('标签生成失败' . $throwable->getTraceAsString());
            return false;
        }
    }

    /**
     * 合并pdf
     * @throws Exception
     */
    public function mergePdf($labels)
    {
        $path = storage_path('app/public');
        $mergeFile = '/admin/';
        $name = Str::random(8) . '-merge.pdf';

        $outputPath = $path . $mergeFile . $name;

        $filePath = [$outputPath];
        // 本地url
        $localUrl = config('app.url');
        foreach ($labels as $label) {
            if (!$label) {
                continue;
            }
            // 解析 URL
            $parsed_url = parse_url($label);
            // 获取协议和主机名
            $protocol = $parsed_url['scheme'] ?? '';
            $host = $parsed_url['host'] ?? '';

            $host = $protocol . '://' . $host;

            if ($localUrl === $host) {
                $labelPath = str_replace('/storage', '', $parsed_url['path']);
                $filePath[] = $path . $labelPath;
            } else {
                $newFile = $path . '/admin/' . Str::random(10) . '-tmp.pdf';
                $tmpPdf = file_get_contents($label);
                file_put_contents($newFile, $tmpPdf);
                $filePath[] = $newFile;
            }
        }

        $files = implode(' ', $filePath);

        $process = Process::fromShellCommandline("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=" . $files);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (!config('app.local_storage')) {
            try {
                Storage::disk('cos')
                    ->putFileAs(
                        '/admin',
                        $outputPath,
                        $name
                    );
            } catch (Exception $e) {
                logger('cos' . $e->getMessage());
            }

        }

        unset($filePath[0]);

        if (!config('app.local_storage')) {
            foreach ($filePath as $file) {
                unlink($file);
            }
        }

        if (!file_exists($outputPath)) {
            throw new AccidentException('生成标签失败', Code::OPERATE_FAIL);
        }

        return config('app.url') . '/storage' . $mergeFile . $name;
    }

    /**
     * 创建履行订单
     * @param $id
     * @return void
     */
    public function createFulfillment($id)
    {
        $shopify = new ShopifyOrder();

        return $shopify->retrievesFulfillment($id);
    }

    /**
     * 请求履行
     * @return void
     */
    public function requestFulfillment($id)
    {
        $shopify = new ShopifyOrder();

        return $shopify->requestFulfillment($id);
    }

    // 手动设置为出库
    public function handleSend()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();
        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->whereNotIn('order_status', [Order::STATUS_PENDING, Order::STATUS_APPLY_NUM])->first(),
            new AccidentException('操作失败，只有已付款和配货中状态下的订单才能进行发货操作', Code::OPERATE_FAIL)
        );
        $orders = $this->model::query()->with('packages')->whereIn('id', $this->formData['ids'])->get();
        return DB::transaction(function () use ($orders) {
            $service = new PackageService();
            foreach ($orders as $order) {
                $order->packages->each(function ($package) use ($service) {
                    $service->outbound($package);
                });
            }
            return true;
        });

    }

    /**
     * 订单发货
     */
    public function send()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->whereNotIn('order_status', [Order::STATUS_PENDING, Order::STATUS_APPLY_NUM])->first(),
            new AccidentException('操作失败，只有待处理和配货状态下的订单才能进行发货操作', Code::OPERATE_FAIL)
        );

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('is_disable', Order::IS_DISABLE_YES)->first(),
            new AccidentException('操作失败，存在禁止处理的订单，不允许发货', Code::OPERATE_FAIL)
        );

        try {
            $this->model::whereIn('id', $this->formData['ids'])->update(['order_status' => OrderModel::STATUS_SHIPPED, 'outbound_time' => now()]);
        } catch (Exception $e) {
            logger('发货失败：' . $e->getMessage());
            $this->model::whereIn('id', $this->formData['ids'])->update(['order_status' => OrderModel::STATUS_DELIVERY_FAILURE]);
            throw new AccidentException('发货失败，请联系管理员', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function sendSuccess($order, $deliverTime)
    {
        $sync_waybill_number = SystemConfigBaseService::getConfigValue(SystemConfig::SYNC_WAYBILL_NUMBER);
        if($sync_waybill_number == 3) {
//            (new OrderBaseService($order))->orderPlatformTransport();
        }
//        if($sync_waybill_number == 3) dispatch(new FulfillmentOrderJob([$order->id]))->delay(now()->addSeconds(2));

        if (empty($order->deliver_time) && !empty($deliverTime)) $order->deliver_time = $deliverTime;
//        $order->order_status = OrderModel::STATUS_DELIVERY_SUCCESS;
        return $order->save();
    }

    /**
     * 状态统计
     * @return void
     */
    public function statusCount()
    {
        //删除订单状态
        unset($this->formData['status']);
        // 删除子状态
        unset($this->formData['sku_status']);
        unset($this->formData['logistics_status']);
        unset($this->formData['stock_status']);
        unset($this->formData['financial_status']);
        unset($this->formData['abnormal_reason']);
        unset($this->formData['other_status']);
        unset($this->formData['split_merge_status']);

        $this->filters = [];
        $this->setFilterRules();
        $this->setFilter();

        $this->queryCondition();

        $counts = (clone $this->query)->select('order_status as status', DB::raw('count(*) as count'))
            ->where('abnormal_status', Order::ORDER_STATUS_NORMAL)->groupBy('order_status')->get();

        $data = [];
        $status = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '13', '14', '16'];
        $counts->each(function ($item) use (&$data) {
            $data[$item->status] = $item->count;
        });

        $data[''] = (clone $this->query)->count();

        foreach ($status as $item) {
            if (!isset($data[$item])) {
                $data[$item] = 0;
            }
        }

        //计算未报价状态平台订单状态为开启、平台支付状态为已付款的数量
        $quoteNoCount = (clone $this->query)->where('order_status', Order::STATUS_QUOTE_NO)->where('abnormal_status', Order::ORDER_STATUS_NORMAL)
            ->where(function ($query) {
                $query->whereIn('platform_payment_status', ['paid', 'partially_refunded', 'partially_paid'])->orWhere('platform', '!=', Platform::SHOPIFY);
            })->count();
        info('查询慢', [$this->query->toSql()]);
        $data[Order::STATUS_QUOTE_NO] = $quoteNoCount;
        $data['abnormal'] = (clone $this->query)->where('abnormal_status', Order::ORDER_STATUS_ABNORMAL)->count();

        ksort($data);

        return $data;
    }

    /**
     * 更换物流状态统计
     * @return void
     */
    public function changeStatusCount()
    {
        $counts = $this->query->where('is_change', 1)
            ->select('change_status as status', DB::raw('count(*) as count'))
            ->groupBy('change_status')
            ->get();

        $data = [];
        $status = ['0', '1', '2', '3', '4', '5'];
        $counts->each(function ($item) use (&$data) {
            $data[$item->status] = $item->count;
        });

        foreach ($status as $item) {
            if (!isset($data[$item])) {
                $data[$item] = 0;
            }
        }

        ksort($data);

        return $data;
    }

    /**
     * 标记订单为更换物流
     * @return bool
     */
    public function changeLogistics()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'change_type' => 'required|int',
            'express_line_id' => 'required_if:change_type,5|nullable|int',
            'change_remark' =>  'nullable|string',
        ], [
            'express_line_id.required_if' => '请选择物流渠道',
        ])->validate();

        try {

            $expressData = [];
            $expressLineId = $this->formData['express_line_id'] ?? 0;
            if ($expressLineId) {
                $expressLine = ExpressLineModel::query()->findOrFail($expressLineId);

                $channel = LogisticsChannelModel::with('expressCompanies')->where('code', $expressLine->channel_code)->first();
                if (empty($channel)) {
                    throw new AccidentException('物流渠道未配置物流公司');
                }

                $expressData = [
                    'express_line_name' => $expressLine->name ?? '',
                    'channel_id' => $channel->id,
                    'channel_code' => $channel->code ?: $channel->name,
                    'express_companies_code' => $channel->expressCompanies->code,
                ];
            }

            $orders = Order::query()->with('expressLine')->whereIn('id',$this->formData['ids'])->get();
            $orders->each(function ($order) {
                if (!in_array($order->order_status, [Order::STATUS_PENDING, Order::STATUS_APPLY_NUM])) {
                    throw new AccidentException("订单{$order->order_id}当前状态不能更换运单", Code::OPERATE_FAIL);
                }
            });
            $logsData = [];
            $orders->each(function ($order) use ($expressData, &$logsData) {
                $orderData = [
                    'is_change' => 1,
                ];

                $logContent = '更换运单。更换原因：';
                $logContent .= ExpressOrderModel::getChangeTypeName($this->formData['change_type']);

                if ($expressData) {
                    $orderData['express_line_id'] = $this->formData['express_line_id'];
                    $orderData['logistics_provider'] = $expressData['channel_id'];
                    $orderData['logistics_provider_code'] = $expressData['express_companies_code'];
                    $orderData['change_logistics_provider'] = $expressData['channel_id'];

                    $oldExpressLineName = $order->expressLine->name ?? '';
                    $oldChannelCode = $order->expressLine->channel_code ?? '';
                    $expressCompaniesCode = $order->logistics_provider_code ?? '';
                    $logContent .= "；物流渠道：{$order->express_line_id}_{$oldExpressLineName} => {$expressData['express_line_name']}";
                    $logContent .= "，{$expressCompaniesCode} => {$expressData['express_companies_code']}";
                    $logContent .= "，{$oldChannelCode} => {$expressData['channel_code']}";

                    $order->packages->each(function ($package) use ($expressData, $orderData) {
                        $package->update([
                            'express_companies_id'   => $orderData['express_line_id'] ?? 0,
                            'express_companies_code' => $expressData['express_companies_code'] ?? '',
                            'express_channel_code'   => $expressData['channel_code'],
                        ]);
                    });

                }

                $order->packages->each(function ($package) use ($expressData, $orderData) {
                    dispatch(new LogisticsPlaceJobV2($package->id, ['change_type' => $this->formData['change_type']]))->delay(Carbon::now()->addSeconds(3));
                });

                if ($this->formData['change_remark']) {
                    $logContent .= '；备注：' . $this->formData['change_remark'];
                }

                $logData = [
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CHANGE_LOGISTICS,
                    'content' => $logContent,
                ];
                ShopOrderLogs::addLog($logData);
                $logsData[] = $logData;
                //更新订单信息
                $order->update($orderData);
            });

        } catch (Exception $e) {
            info('更换运单失败', ['msg' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    public function updateTracking()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        dispatch(new UpdateTrackingJob($this->formData['ids']));

        return true;
    }

    public function openFulfillment($id)
    {
        $order = $this->model::with(['shop', 'fulfillmentOrder'])->where('id', $id)->first();
        $service = new \App\Services\Shopify\OrderService();

        info('履行订单id', [$order->fulfillmentOrder->fulfillment_order_id]);
        return $service->openFulfillment($order->shop, $order->fulfillmentOrder->fulfillment_order_id);
    }

    public function cancelFulfillment($id)
    {
        $service = new \App\Services\Shopify\OrderService();

        return $service->cancelFulfillment($id);
    }

    /**
     * 拉取订单
     * @param $params
     * @return array
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/4 16:18
     */
    public function pullOrders($params)
    {
        $syncType = $params['sync_type'] ?? Order::SYNC_TYPE_AUTOMATIC;

        if ($syncType == Order::SYNC_TYPE_MANUAL) {
            $pullOrderType = $params['pull_order_type'] ?? 1; //拉单类型 1按条件范围 2按照单号
            $params['order_ids']                    = $params['order_ids'] ?? [];
            $params['begin_date']                   = $params['begin_date'] ?? '';
            $params['end_date']                     = $params['end_date'] ?? '';
            $params['platform_order_status']        = $params['platform_order_status'] ?? 'any'; //平台订单状态，默认未完结状态
            $params['platform_payment_status']      = $params['platform_payment_status'] ?? 'any'; //平台付款状态，默认已付款
            $params['platform_fulfillment_status']  = $params['platform_fulfillment_status'] ?? 'any'; //平台发货状态，默认未发货

            if (empty($params['shop_id'] ?? '')) {
                throw new AccidentException('请先选择店铺，再拉取订单', Code::OPERATE_FAIL);
            }

            //拉单类型 1按照日期范围, 2按照指定单号
            if ($pullOrderType == 1) {
                //不选择日期范围则默认拉取最近2个月订单
                if ($params['begin_date'] && $params['end_date']) {
                    $beginDate  = Carbon::createFromFormat('Y-m-d', $params['begin_date']);
                    $endDate    = Carbon::createFromFormat('Y-m-d', $params['end_date']);
                    $diffDay = $endDate->diffInDays($beginDate) + 1;
                    $params['diff_days'] = $diffDay;
                } else {
                    throw new AccidentException('拉单类型:按条件范围，请先选择日期范围', Code::OPERATE_FAIL);
                }
            } elseif ($pullOrderType == 2) {
                if (count(array_filter($params['order_ids'])) <= 0) {
                    throw new AccidentException('拉单类型：按单号，请先输入平台单号', Code::OPERATE_FAIL);
                } else {
                    if (count(array_filter($params['order_ids'])) > 1000) {
                        throw new AccidentException('按单号拉取每次最多拉取1000个订单', Code::OPERATE_FAIL);
                    }

                    $params['begin_date']   = date('Y-m-d', strtotime('-'.  Order::MAX_PULL_ORDER_RANGE_DAYS .' day', time()));
                    $params['end_date']     = date('Y-m-d');
                    $params['platform_order_status']        = 'any'; //平台订单状态
                    $params['platform_payment_status']      = 'any'; //平台付款状态
                    $params['platform_fulfillment_status']  = 'any'; //平台发货状态
                }
            } elseif ($pullOrderType == 3) {
                if (empty($params['name'])) throw new AccidentException('请输入平台编号', Code::OPERATE_FAIL);

                $nameList = preg_split('/[\s,，]+/', $params['name']);
                $nameList = array_unique(array_filter($nameList));
                if (count($nameList) > 20) throw new AccidentException('最多支持20个平台编号', Code::OPERATE_FAIL);

                $params['name'] = $nameList;
            }

            $shops = $this->getShopListForPullOrder($params);

            $service = new PlatformShopService($shops->first());
            $result = $service->syncOrderList($params);

            $count = $result['count'] ?? 0;
            $addOrderCount = $result['add_order_count'] ?? 0;
            $message = "订单同步完成，共查询到 {$count} 个订单，新增 {$addOrderCount} 个订单";

            return ['message' => $message, 'count' => $count, 'add_order_count' => $addOrderCount];
        } else {
            $shops = $this->getShopListForPullOrder($params);
            foreach ($shops as $shop) {
                dispatch(new AutoPullOrderJob($shop));
            }
            return ['message' => '订单同步任务添加成功，正在同步中，请稍后刷新页面查看同步结果'];
        }

    }

    /**
     * 获取拉取订单的店铺列表
     * @param array $params
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/4 16:17
     */
    public function getShopListForPullOrder(array $params)
    {
        $query = ShopModel::query()->where(['enable' => ShopModel::ENABLE, 'status' => ShopModel::STATUS_AUTH]);

        $query->when($params['shop_id'] ?? '', function ($query) use ($params) {
            $query->where('id', $params['shop_id']);
        });

        $shops = $query->get();
        if ($shops->isEmpty()) {
            throw new AccidentException('店铺未授权或未启用，请先授权店铺或启用再进行操作', Code::OPERATE_FAIL);
        }

        return $shops;
    }

    /** 订单商品映射报价
     * @param $orderId
     * @param $params
     * @return bool
     * @throws Exception
     * @throws Throwable
     */
    public function orderMappingQuote($orderId, $params)
    {
        validator($params, $this->saveMappingRules())->validate();
        $order = $this->model::with(['lineItems.mapping.goodsSku.goods', 'shippingAddress'])->findOrFail($orderId);
        throw_if(
            $order->order_status > OrderModel::STATUS_ALLOCATED,
            new AccidentException('操作失败，只有未报价订单才能报价', Code::OPERATE_FAIL)
        );

        //库存消耗为客户时，需校验客户库存
        if (!empty($params['use_customer_stock'])) {
            $this->verifyStock($order, $params['mapping_list']);
        }
        return DB::transaction(function () use ($order, $params) {
            // 客户报价是否使用商品一口价
            $params['goods_once_price'] = Custom::query()->where('id', $order->customer_id)->value('goods_once_price');
            if ($order->order_status > 1) {
                $params['exchange_rates'] = $order->exchange_rates;
                // 客户产品利润
                $params['product_quote_default_profit_rate'] = $order->product_profit;
                // 客户物流利润
                $params['freight_quote_default_profit_rate'] = $order->freight_profit;
            } else {
                // 客户产品利润
                $params['product_quote_default_profit_rate'] = CustomsQuoteConfig::query()->where('customer_id', $order->customer_id)->value('product_quote_default_profit_rate');
                // 客户物流利润
                $params['freight_quote_default_profit_rate'] = CustomsQuoteConfig::query()->where('customer_id', $order->customer_id)->value('freight_quote_default_profit_rate');
            }
            // 客户ID
            $params['customer_id'] = (int) $order->customer_id;
            $orderQuoteService = new OrderQuoteService($order);
            // 订单报价
            $orderQuotationData = $orderQuoteService->setSavePriceQuote()->orderQuote($params);
            $orderQuoteService->saveQuoteData($orderQuotationData, $params);
            // 商品映射关系
            $mappingData = [];
            foreach ($orderQuotationData['goods_price_detail'] as $goodsPrice) {
                $unit_price = $goodsPrice['unit_price'] ?? 0;
                if ($unit_price > 0) {
                    $unit_price = round(bcdiv($unit_price, $params['exchange_rates'], 4), 2);
                }
                $mappingData[] = [
                    'line_item_id'   => $goodsPrice['line_item_id'],
                    'sku_id'         => $goodsPrice['mappingGoodsSku']['id'] ?? 0,
                    'quote_price'    => $unit_price,
                    'purchase_price' => $goodsPrice['purchase_price'] ?? 0,
                    'profit_price'   => $goodsPrice['profit_price'] ?? 0
                ];
            }

            $this->checkAndSaveOrderMapping($order, $mappingData);

            // 保存物流渠道
            $order->logistics_provider = $orderQuotationData['logistics_provider'];
            $order->logistics_provider_code = $orderQuotationData['logistics_provider_code'];
            $order->express_line_id = $orderQuotationData['express_line_id'];
            $order->myLogisticsId = $orderQuotationData['myLogisticsId'];
            $order->myLogisticsChannelId = $orderQuotationData['myLogisticsChannelId'];
            $order->quote_id = $params['quote_id'] ?? 0;
            $order->channel_name = $params['channel_name'] ?? '';
            $order->charge_type_id = $params['charge_type_id'] ?? 0;
            $order->exchange_rates = $params['exchange_rates'];
            $order->product_profit = $params['product_quote_default_profit_rate'];
            $order->freight_profit = $params['freight_quote_default_profit_rate'];

            //员工ID
            $order->staff_id = getAdminId();

            //物流成本、物流利润
            $order->freight_quote_calculate_method = $orderQuotationData['freight_quote_calculate_method'];
            $order->logistics_cost = $orderQuotationData['logistics_cost'];
            $order->logistics_profit = $orderQuotationData['logistics_profit'];

            $order->save();


            $logContent = "订单报价：物流成本({$orderQuotationData['logistics_cost']} ¥)、物流利润({$orderQuotationData['logistics_profit']} ¥)";
            ShopOrderLogs::addLog([
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_VENDOR_PRICE,
                'content' => $logContent,
            ]);

            $this->declaration($order->id);

            //提交报价
            if (!empty($params['is_submit_quote'])) {
                //传递订单id
                $this->formData['ids'] = [$order->id];
                $this->setQuote();
            }

            return true;
        });
    }

    /** 检查订单的商品映射并获取价格
     * @param $order
     * @param $itemsMapping
     * @throws Exception
     */
    public function checkAndSaveOrderMapping($order, $itemsMapping)
    {
        $orderItems = OrderLineItem::query()->with(['mapping.goodsSku'])->where('order_id', $order->id)->get();
        $mappings = collect($itemsMapping)->keyBy('line_item_id');
        $orderItems->each(function ($item) use ($order, &$goodsPrice, &$recordData, $mappings) {
            $mapping = $mappings[$item->id] ?? null;
            if (empty($mapping['sku_id'])) throw new AccidentException("{$item->name}：关联的商品不存在，请重新选择", Code::OPERATE_FAIL);

            $goodsSku = GoodsSku::query()->findOrFail($mapping['sku_id']);

            $item->goods_sku_id = $mapping['sku_id'];
            $item->quote_price = $mapping['quote_price'];
            $item->purchase_price = $mapping['purchase_price'];
            $item->profit = $mapping['profit_price'];
            $item->save();

            //保存item-sku映射关系
            OrderItemMapping::query()->updateOrCreate([
                'platform' => $order->platform,
                'platform_variant_id' => $item->variant_id,
            ], ['goods_sku_id' => $goodsSku->id]);
        });
    }

    /**
     * @param $id
     * @param $params
     * @return true
     */
    public function orderAddLineItem($id, $params)
    {
        $order = Order::query()->findOrFail($id);
        $goodsList = $params['add_goods_list'] ?? [];
        return $this->addOrderGoods($goodsList, $order);
    }

    /**
     * 更新订单商品数量
     */
    public function updateLineItem($params)
    {
        validator($params, [
            'id' => 'required|integer',
            'quantity' => 'required|integer',
            'variant_id' => 'required|string',
            'variant_title' => 'required|string',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $info = OrderLineItem::query()->where('id', $params['id'])
                ->select('order_id', 'quantity')->first();
            OrderLineItem::query()->where('id', $params['id'])->update([
                'quantity' => $params['quantity'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            ShopOrderLogs::addLog([
                'order_id' => $info->order_id,
                'operate_type' => ShopOrderLogs::OPERATOR_TYPE_MANUALLY_UPDATE_ORDER_GOODS,
                'content' => "客户手动更新订单商品,SKU：{$params['variant_id']}，规格名称：{$params['variant_title']}，数量：{$info->quantity}改为：{$params['quantity']}",
            ]);
            return true;
        });
    }

    /** 订单归档
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function archive($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();
        return DB::transaction(function () use ($params) {
            $orders = Order::query()->whereIn('id', $params['ids'])->get();
            $allowStatus = [Order::STATUS_WAIT, Order::STATUS_QUOTE_ASK, Order::STATUS_CANCELLED, Order::STATUS_SHIPPED];
            $orders->each(function ($order) use ($allowStatus) {
                if (!in_array($order->order_status, $allowStatus)) {
                    throw new AccidentException("订单{$order->order_id}状态不允许归档", Code::OPERATE_FAIL);
                }
                $order->order_status_before = $order->order_status;
                $order->order_status = Order::STATUS_ARCHIVE;
                $order->save();
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_ARCHIVE,
                    'content' => '订单归档',
                ]);
            });

            return true;
        });
    }


    /** 取消订单归档
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function rollbackArchive($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();
        return DB::transaction(function () use ($params) {
            $orders = Order::query()->whereIn('id', $params['ids'])->get();
            $orders->each(function ($order) {
                if ($order->order_status != Order::STATUS_ARCHIVE) {
                    throw new AccidentException("订单{$order->order_id}当前状态不是已归档", Code::OPERATE_FAIL);
                }
                $order->order_status = $order->order_status_before;
                $order->save();
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ROLLBACK_ARCHIVE,
                    'content' => '订单取消归档，重新回档',
                ]);
            });

            return true;
        });
    }

    public function setVirtual($id)
    {
        return DB::transaction(function () use ($id) {
            // 添加虚拟产品
            (new PlatformVirtualSkusService())->storeByOrderItem($id);
            // 删除产品
            $this->formData['ids'] = [$id];
            $this->deleteItems();
            return true;
        });

    }

    /**
     * @param $params
     * @return \Maatwebsite\Excel\Excel
     */
    public function importLogistics()
    {
        return Excel::import(new OrderLogisticsUpdateImport(), request()->file('import_file'));
    }

    /**
     * 增加订单商品
     * @param array $goodsList
     * @param OrderModel $order
     * @return true
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/17 17:45
     */
    public function addOrderGoods(array $goodsList, Order $order)
    {
        return DB::transaction(function () use ($goodsList, $order) {
            foreach ($goodsList as $addGoods) {
                $goodsSku = GoodsSku::query()->findOrFail($addGoods['goods_sku_id']);
                $skuArray = $goodsSku->toArray();
                $skuArray['quantity'] = $addGoods['quantity'];
                $skuArray['add_type'] = OrderLineItem::ADD_TYPE_MANUALLY_ADD;
                $skuArray['goods_name'] = $goodsSku->goods->goods_name ?? '';
                $skuData = OrderLineItem::initByLocalProduct($order->id, $skuArray);
                $item = OrderLineItem::query()->create($skuData);

                //保存item-sku映射关系
                OrderItemMapping::query()->updateOrCreate([
                    'platform' => $order->platform,
                    'platform_variant_id' => $item->variant_id,
                ], ['goods_sku_id' => $goodsSku->id]);

                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operate_type' => ShopOrderLogs::OPERATOR_TYPE_MANUALLY_ADD_ORDER_GOODS,
                    'content' => "客户手动增加订单商品,SKU：{$item->variant_id}，规格名称：{$item->variant_title}",
                ]);
            }
            return true;
        });

    }

    /**
     * 删除订单商品
     * @param array $goodsList
     * @param OrderModel $order
     * @return OrderModel
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/17 16:28
     */
    public function deleteOrderGoods(array $goodsList, Order $order)
    {
        $ids = array_column($goodsList, 'id');
        OrderLineItem::query()->whereIn('id', $ids)->delete();

        $mappingIds = array_column($goodsList, 'mapping_id');
        OrderItemMapping::query()->whereIn('id', $mappingIds)->delete();

        foreach ($order->lineItems as $item) {
            if (in_array($item->id, $ids)) {
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_DELETE_ITEM,
                    'content' => "客户删除订单SKU: {$item->sku}，规格名称：{$item->name}"
                ]);
            }
        }

        return $order->refresh();
    }

    # todo autoOrderQuote 订单自动报价

    /** 订单报价
     * @param $order
     * @param $logistic
     * @param $orderQuotationData
     * @return void
     * @throws Throwable
     */
    public function orderQuote($order, $logistic, $orderQuotationData, $itemsMapping)
    {
        $goodsPrice = $this->checkOrderMapping($order, $logistic, $itemsMapping);

        // if (empty($channel)) throw new AccidentException('渠道不存在', Code::OPERATE_FAIL);
        throw_unless(ExpressLineModel::where('id', $logistic['logistics_type'])->first(),
            new AccidentException('渠道不存在', Code::OPERATE_FAIL));

        $channel_code = ExpressLineModel::where('id', $logistic['logistics_type'])->value('channel_code');
        $channel = LogisticsChannelModel::with('expressCompanies')->where('code', $channel_code)->first();
        if (empty($channel)) throw new AccidentException('未配置物流渠道');
        $orderQuotationPrice = 0;

        if (!empty($orderQuotationData['line_items'])) {
            $mappings = array_column($itemsMapping, 'sku_id', 'variant_id');

            //订单报价 = 产品报价 * 产品数量
            foreach ($orderQuotationData['line_items'] as $item) {
                $quotationPrice = $item['quotation_price'] ?? 0;

                //有产品一客一价报价为0时，取商品报价金额
                if (empty($quotationPrice)) {
                    $goodsSkuId = $mappings[$item['variant_id']] ?? 0;
                    $quotationPrice = GoodsSku::query()->where('id', $goodsSkuId)->value('quote_price') ?? 0;
                }
                $orderQuotationPrice += $quotationPrice * $item['quantity'];
            }
        }
        $order->vendor_price = $orderQuotationPrice ?: $goodsPrice;

        //库存消耗为客户时，不计算商品报价
        if (!empty($order->use_customer_stock)) {
            $order->vendor_price = 0;
        }

        $order->logistics_fee = $logistic['logistics_fee'];
        $order->logistics_provider = $channel->id;
        $order->express_line_id = $logistic['logistics_type'];
        $order->logistics_provider_code = $channel->expressCompanies->code;
        $order->other_supplement_price = $orderQuotationData['other_supplement_price'] ?? 0;
        $order->favourable_price = $orderQuotationData['favourable_price'] ?? 0;//优惠价格
        $order->order_one_price = ($orderQuotationData['order_one_price'] ?? false) ? 1 : 2;//商品一口价 1是 2否
        $order->charge_type_id = $orderQuotationData['charge_type_id'] ?? 0; //费用类型id
//        $order->order_status = OrderModel::STATUS_ALLOCATED;

        $logisticsFee = $order->logistics_fee;

        //开启商品一口价时保存sku物流总报价
        if ($order->order_one_price === 1) {
            $order->sku_logistics_fee = $orderQuotationData['sku_logistics_fee'] ?? 0;

            $logisticsFee = $order->sku_logistics_fee;
        }

        //获取费用类型名称
        $chargeType = '';
        if ($order->charge_type_id > 0) {
            $chargeTypeName = ChargeTypesModel::query()->where('id', $order->charge_type_id)->value('name');
            $chargeType = '(费用类型：'. $chargeTypeName .')';
        }

        //报价金额=商品报价+物流报价+其他补价-优惠价格
        $totalVendorPrice = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price;
        $operatorType = ShopOrderLogs::OPERATOR_TYPE_VENDOR_PRICE;
        $logContent = '订单报价：商品报价('.$order->vendor_price.') + 物流报价('.$logisticsFee.') + 其他补价'. $chargeType .'('.$order->other_supplement_price.') - 优惠价格('.$order->favourable_price.') = USD ' . $totalVendorPrice;

        if ($orderQuotationData['is_change_price']) {
            $order->vendor_change_price = $orderQuotationData['vendor_change_price'];

            $operatorType = ShopOrderLogs::OPERATOR_TYPE_VENDOR_CHANGE_PRICE;
            $logContent = '订单改价：原金额USD ' . $totalVendorPrice . '，改价金额USD ' . $order->vendor_change_price;
        }

        if (!empty($order->use_customer_stock)) {
            $logContent = '消耗客户库存，' . $logContent;
        }

        $order->save();

        $logData = [
            'order_id' => $order->id,
            'operator_type' => $operatorType,
            'content' => $logContent,
        ];
        ShopOrderLogs::addLog($logData);
    }

    /** 检查订单的商品映射并获取价格
     * @param $order
     * @param array $logistic
     * @param $itemsMapping
     * @return HigherOrderBuilderProxy|int|mixed
     * @throws Exception
     */
    public function checkOrderMapping($order, $logistic, $itemsMapping)
    {
        $goodsPrice = 0;
        $orderItems = OrderLineItem::query()->with(['mapping.goodsSku'])->where('order_id', $order->id)->get();

        $recordData = [];
        $mappings = array_column($itemsMapping, 'sku_id', 'variant_id');
        $orderItems->each(function ($item) use ($order, &$goodsPrice, &$recordData, $mappings) {
            $goodsSkuId = $mappings[$item->variant_id] ?? 0;
            if (empty($goodsSkuId)) throw new AccidentException("{$item->name}：关联的商品不存在，请重新选择", Code::OPERATE_FAIL);

            $goodsSku = GoodsSku::query()->findOrFail($goodsSkuId);

            $item->goods_sku_id = $goodsSku->id;
            $item->quote_price = $goodsSku->quote_price;//报价金额
            $item->save();

            //保存item-sku映射关系
            OrderItemMapping::query()->updateOrCreate([
                'platform' => $order->platform,
                'platform_variant_id' => $item->variant_id,
            ], ['goods_sku_id' => $goodsSku->id]);

            // 统计商品报价金额
            $goodsPrice += $goodsSku->quote_price * $item->quantity;

            // 报价记录
            $recordData[] = [
                'variant_id' => $item->variant_id,
                'sku_id' => $goodsSku->id,
                'goods_price' => $item->quote_price,
            ];
        });

        $shippingAddr = [
            'country' => $order->shippingAddress->country,
            'country_code' => $order->shippingAddress->country_code
        ];

        $code = ExpressLineModel::where('id', $logistic['logistics_type'])->value('channel_code');
        if (empty($code)) throw new AccidentException('请设置物流渠道对应的物流服务商');
        info('物流渠道code', ['logistics_code' => $code, 'line' => $logistic['logistics_type']]);

        $logisticsProvider = LogisticsChannelModel::where('code', $code)->value('id');
        if (empty($logisticsProvider)) {
            throw new AccidentException('订单所选物流方式暂未设置配单公司/渠道代码，请前往‘物流-运费模板-渠道-渠道编辑-修改操作’进行配置', Code::OPERATE_FAIL);
        }

        $logistics = [
            'logistics_provider' => $logisticsProvider
        ];

        $this->quotationRecord($recordData, $shippingAddr, $logistics);


        return $goodsPrice;
    }

    /**
     * 报价记录
     * @param $data
     * @return void
     */
    public function quotationRecord($data, $shippingAddr, $logistics)
    {
        foreach ($data as $item) {
            $record = QuotationRecordModel::where('variant_id', $item['variant_id'])->first();
            $item['country'] = $shippingAddr['country'];
            $item['country_code'] = $shippingAddr['country_code'];
            $item['logistics_provider'] = $logistics['logistics_provider'];
            if (empty($record)) {
                QuotationRecordModel::create($item);
            } else {
                $record->update($item);
                // QuotationRecordModel::where('id', $record->id)->update(['sku_id' => $item['sku_id'], 'goods_price' => $item['goods_price']]);
            }
        }
    }

    /** 保存订单的产品映射关系
     * @param $mappingArray
     * @return void
     */
    public function saveOrderMapping($order, $mappingArray)
    {
        foreach ($mappingArray as $value) {
            $mapping = OrderItemMapping::query()->where('platform_variant_id', $value['variant_id'])->where('platform', $order->platform)->first();
            if (empty($mapping)) {
                $mapping = new OrderItemMapping();
                $mapping->platform = $order->platform;
                //本地品的将SKUID做为变体ID
                $mapping->platform_variant_id = $value['variant_id'];
            }

            $mapping->goods_sku_id = $value['sku_id'];
            $mapping->save();
        }
    }

    protected function saveMappingRules()
    {
        return [
            'express_line_id' => 'required|int',
            'use_customer_stock' => 'required|int',
            'mapping_list' => 'required|array',
            'mapping_list.*.line_item_id' => 'required|int',
            'mapping_list.*.sku_id' => 'required|int',
            'vendor_change_price' => 'sometimes|nullable|numeric',
            'is_submit_quote' => 'sometimes|boolean',
            'order_one_price' => 'sometimes|boolean',
            'favourable_price' => 'sometimes|nullable|numeric',
            'staff_id' => 'sometimes|nullable|int',
            'custom_logistic_price' => 'sometimes|nullable|numeric',
            'custom_favourable_price' => 'sometimes|nullable|numeric',
            'other_supplement_price' => 'sometimes|nullable|numeric',
        ];
    }

    /**
     * @throws Exception|Throwable
     */
    public function moveToInStock(): bool
    {
        validator($this->formData, [
            'ids' => 'required',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '<>', OrderModel::STATUS_WAIT_PRINT_OUT_STOCK)->first(),
            new AccidentException('操作失败，订单非缺货状态', Code::OPERATE_FAIL)
        );

        $orders = $this->model::with('lineItems')->whereIn('id', $this->formData['ids'])->get();

        DB::beginTransaction();
        try {
            $orders->each(function ($order) {
                $this->createDeliverData($order, 2);
                // $order->lineItems->each(function ($item) use($order) {
                //     $mapping = OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item['variant_id'])->first();
                //     if (empty($mapping)) throw new AccidentException('订单未映射本地商品', Code::OPERATE_FAIL);
                //
                //     $stock = Stock::query()->where('sku_id', $mapping->goods_sku_id)->first();
                //
                //     if(!empty($stock) && $stock->quantity > 0) {
                //         //使用库存
                //         $this->orderItemUseStock($order, $item, $stock);
                //         $this->createDeliverData();
                //         $order->order_status = OrderModel::STATUS_WAIT_PRINT_IN_STOCK;
                //         $order->save();
                //     } else {
                //         throw new AccidentException('操作失败，SKU: '.$item->sku.' 无库存', Code::OPERATE_FAIL);
                //     }
                //
                // });
            });

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * 移入缺货，先释放锁定的库存
     * 再创建采购订单
     * @throws Throwable
     * @throws ValidationException
     */
    public function moveToOutStock()
    {
        validator($this->formData, [
            'ids' => 'required',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '<>', OrderModel::STATUS_WAIT_PRINT_IN_STOCK)->first(),
            new AccidentException('操作失败，订单非有货状态', Code::OPERATE_FAIL)
        );

        $orders = $this->model::with(['lineItems', 'orderStock'])->whereIn('id', $this->formData['ids'])->get();

        DB::beginTransaction();
        try {
            // 释放订单所占用的库存
            $this->release($orders);

            // 更新订单状态
            $this->model::whereIn('id', $this->formData['ids'])->update(['order_status' => OrderModel::STATUS_WAIT_PRINT_OUT_STOCK]);

            $res = OutboundShopOrderRelate::whereIn('shop_order_id', $this->formData['ids'])->select('outbound_order_id')->get()->toArray();

            $outboundOrderIds = array_column($res, 'outbound_order_id');

            OutboundOrder::whereIn('id', $outboundOrderIds)->update(['status' => OutboundOrder::STATUS_CANCEL]);

            // 创建订单发货项
            // $orders->each(function ($order) {
            //     $this->createDeliverData($order);
            // });

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return true;
    }

    /**
     * 订单释放库存
     * @return void
     */
    public function release($orders)
    {
        $stockService = new StockService();

        $orders->each(function ($order) use ($stockService) {
            $lockIds = array_column($order->orderStock->toArray(), 'lock_id');
            $locks = StockLockLog::query()->whereIn('id', $lockIds)->get();

            $locks->each(function ($lock) use ($stockService) {
                $stockService->unlockStock($lock, StockLockLog::SOURCE_ORDER_WAIT_PRINT);
            });
        });

    }

    /**
     * 订单商品报关信息
     * @param $item_id
     * @param $data
     * @return bool
     */
    public function declaration($order_id)
    {
        $order = $this->model::with('lineItems')->where('id', $order_id)->first();

        if (empty($order)) {
            return false;
        }

        $order->lineItems->each(function ($item) {
            $mapping = OrderItemMapping::query()->with('goodsSku.goods')->where('platform_variant_id', $item->variant_id)->first();
            if ($mapping) {
                // 获取商品报关信息
                $goodsDeclaration = LogisticsCustomsDeclarationModel::query()->where('goods_sku_id', $mapping->goodsSku->id)->first();

                if (!empty($goodsDeclaration)) {
                    $declaration = OrderDeclarationModel::query()->where('order_item_id', $item->id)->first();
                    if ($declaration) {
                        return false;
                    }

                    OrderDeclarationModel::create([
                        'order_item_id' => $item->id,
                        'cn_name' => $goodsDeclaration['cn_name'],
                        'en_name' => $goodsDeclaration['en_name'],
                        'unit_price' => $goodsDeclaration['unit_price'],
                        'code' => $goodsDeclaration['code'],
                        'weight' => $goodsDeclaration['weight'],
                        'attributes' => $goodsDeclaration['attributes'],
                        'material' => $goodsDeclaration['material'],
                        'use_to' => $goodsDeclaration['use_to'],
                    ]);
                }
            }
        });

        return true;
    }

    public function updateShippingAddr($id)
    {
        validator($this->formData, [
            'country' => 'required',
        ], [], [
            'country' => '国家',
        ])->validate();

        $address = OrderShippingAddress::query()->where('order_id', $id)->first();

        $country = Country::query()->where('cn_name', $this->formData['country'])->orWhere('en_name', $this->formData['country'])->orWhere('code', $this->formData['country'])->first();
        $countryCode = $country->code ?? '';

        $title = [
            'first_name'   => '名',
            'last_name'    => '姓',
            'name'         => '昵称',
            'company'      => '公司',
            'country'      => '国家/地区',
            'country_code' => '国家代码',
            'phone'        => '电话',
            'zip'          => '邮编',
            'province'     => '省/州',
            'city'         => '城市',
            'address1'     => '地址1',
            'address2'     => '地址2',
            'tax'          => '税号',
            'email'        => '邮箱',
        ];

        $data = [
            'name' => $this->formData['name'] ?? '',
            'company' => $this->formData['company'] ?? '',
            'country' => $this->formData['country'] ?? '',
            'country_code' => strtoupper($countryCode),
            'phone' => $this->formData['phone'] ?? '',
            'zip' => $this->formData['zip'] ?? '',
            'province' => $this->formData['province'] ?? '',
            'city' => $this->formData['city'] ?? '',
            'address1' => $this->formData['address1'] ?? '',
            'address2' => $this->formData['address2'] ?? '',
            'tax' => $this->formData['tax'] ?? '',
        ];

        if ($this->formData['first_name'] ?? '') {
            $data['first_name'] = $this->formData['first_name'];
        }

        if ($this->formData['last_name'] ?? '') {
            $data['last_name'] = $this->formData['last_name'];
        }

        try {
            if ($address) {
                //校验差异值
                $differential = differenceComparing($data, $address->toArray(), $title);

                $address->update($data);

                if (!empty($differential)) {
                    //没有差异信息不需要更新
                    $logData = [
                        'order_id' => $address->order_id,
                        'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ADDRESS,
                        'content' => '手动修改收货地址：' . $differential['content'],
                    ];
                    ShopOrderLogs::addLog($logData);
                    $address->edited_at = now();
                    $address->save();
                }

            } else {
                $data['order_id'] = $id;
                OrderShippingAddress::query()->create($data);
            }
        } catch (Exception $e) {
            throw new AccidentException('更新收件人信息失败', Code::OPERATE_FAIL);
        }


        return true;
    }

    public function updateDeclaration($order_item_id): bool
    {
        $declaration = OrderDeclarationModel::query()->with('orderItem')->where('order_item_id', $order_item_id)->first();

        $title = [
            'cn_name'    => '中文报关名',
            'en_name'    => '英文报关名',
            'unit_price' => '报关单价',
            'code'       => '海关编码',
            'weight'     => '报关重量',
            'attributes' => '物品属性',
            'material'   => '材质',
            'use_to'     => '用途',
        ];

        $data = [
            'cn_name' => $this->formData['cn_name'] ?? '',
            'en_name' => $this->formData['en_name'] ?? '',
            'unit_price' => $this->formData['unit_price'] ?? 0,
            'code' => $this->formData['code'] ?? '',
            'weight' => $this->formData['weight'] ?? 0,
            'attributes' => $this->formData['attributes'] ?? [],
            'material' => $this->formData['material'] ?? '',
            'use_to' => $this->formData['use_to'] ?? '',
        ];

        try {
            if ($declaration) {
                //校验差异值
                $differential = differenceComparing($data, $declaration->toArray(), $title);

                $declaration->update($data);

                //没有差异信息不需要更新
                $logData = [
                    'order_id' => $declaration->orderItem->order_id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ADDRESS,
                    'content' => "【{$declaration->orderItem->sku}】修改报关信息：" . $differential['content'],
                ];
                ShopOrderLogs::addLog($logData);
            } else {
                $data['order_item_id'] = $order_item_id;
                OrderDeclarationModel::create($data);
            }
        } catch (Exception $e) {
            throw new AccidentException('更新报关信息失败', Code::OPERATE_FAIL);
        }


        return true;
    }

    /**
     * 订单导入
     * @return void
     */
    public function import()
    {
        $file = request()->file('file');

        if (empty($file)) {
            throw new AccidentException('导入文件不能为空', Code::OPERATE_FAIL);
        }

        $ext = $file->getClientOriginalExtension();
        $fileName = date('Ymd') . '-' . Str::random() . '.' . $ext;
        $file->storeAs(
            '/', $fileName, 'admin_public'
        );

        $path = storage_path('app/public/admin');

        if (!file_exists($path . '/20240223-WqakD5jMjFzBtooF.xls')) {
            throw new AccidentException('导入文件不存在', Code::OPERATE_FAIL);
        }

        $import = new OrderImport();

        $import->import($path . '/20240223-WqakD5jMjFzBtooF.xls');
        $failures = $import->failures();
        $messates = [];
        if ($failures) {
            foreach ($failures as $failure) {
                // 出问题的那一行
                $rowNo = $failure->row();
                //标题键（如果使用标题行）或列索引
                $index = $failure->attribute();
                //来自Laravel验证程序的实际错误消息
                $error = $failure->errors();
                //失败行的值。
                $value = $failure->values();

                $messates[] = ['row_no' => $rowNo, 'index' => $index, 'msg' => $error, 'value' => implode(' | ', $value)];
            }
            info('错误信息', $messates);
        }

        return $messates;
    }

    public function handMovement($id)
    {
        validator($this->formData, [
            'cn_name' => 'required',
            'en_name' => 'required',
            'unit_price' => 'required',
            'weight' => 'required',
        ], [], [
            'cn_name' => '中文名称',
            'en_name' => '英文名称',
            'unit_price' => '申报单价',
            'weight' => '申报重量',
        ])->validate();

        $handMovement = HandMovementModel::where('order_id', $id)->first();

        $data = HandMovementModel::init($id, $this->formData);
        DB::beginTransaction();
        try {
            if ($handMovement) {
                $data['attributes'] = json_encode($data['attributes']);
                HandMovementModel::where('id', $handMovement->id)->update($data);
            } else {
                HandMovementModel::create($data);
            }

            // 更新订单表手动报关标识
            $this->model::where('id', $id)->update(['is_hand_customs' => 1]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('保存报关信息失败：' . $e->getMessage());
            throw new AccidentException('保存报关信息失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function updateHandCustoms($id)
    {
        return $this->model::where('id', $id)->update(['is_hand_customs' => 0]);
    }

    /**
     * 订单佣金生成
     * @param $orders
     */
    public function orderPromotion($orders)
    {
        foreach ($orders as $order) {
            $custom = Custom::query()->where('id', $order->customer_id)->first();
            if ($custom->invite_id == 0) {
                logger("客户[" . $custom->custom_name . "]无邀请人");
                continue;
            }
            $commission = new CommissionService($custom->id);

            $commission->increase($order->vendor_price + $order->logistics_fee, $custom->commission_rate, $order->order_id);
        }
    }

    /**
     * 添加订单备注
     * @param $id
     * @param $data
     * @return bool|int
     */
    public function addOrderRemark($id, $data)
    {
        $order = Order::query()->findOrFail($id);

        return $order->update([
            'warehouse_remark' => $data['warehouse_remark'] ?? '',
            'system_remark' => $data['system_remark'] ?? '',
        ]);
    }

    /** 订单改价
     * @param $params
     * @return bool
     * @throws Exception
     * @throws Throwable
     */
    public function vendorChangePrice($params)
    {
        validator($params, [
            'order_id' => 'required|int',
            'vendor_change_price' => 'required|numeric',
            'change_price_remark' => 'sometimes|nullable',
        ], [], [
            'order_id' => '订单ID',
            'vendor_change_price' => '最终价格',
            'change_price_remark' => '备注',
        ])->validate();

        $order = $this->model::query()->findOrFail($params['order_id']);

        throw_if(
            $order->order_status !== OrderModel::STATUS_QUOTED,
            new AccidentException('操作失败，只有已报价待支付订单才能改价', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () use ($order, $params) {

            $order->vendor_change_price = $params['vendor_change_price'];
            $order->change_price_remark = $params['change_price_remark'];
            $order->save();

            //物流费用
            $logisticsFee = $order->logistics_fee;

            //开启商品一口价 物流费用为sku的物流总报价
            if ($order->order_one_price === 1) {
                $logisticsFee = $order->sku_logistics_fee;
            }

            //支付金额=商品报价+物流报价+其他补价-优惠价格
            $totalVendorPrice = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price;

            $logContent = '订单改价：原金额USD ' . $totalVendorPrice . '，改价金额USD ' . $order->vendor_change_price;

            if ($order->change_price_remark) {
                $logContent = $logContent . '，备注：' . $order->change_price_remark;
            }

            $logData = [
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_VENDOR_CHANGE_PRICE,
                'content' => $logContent,
            ];
            ShopOrderLogs::addLog($logData);

            return true;
        });
    }

    /**
     * 获取支付信息
     * @return void
     */
    public function getPaymentInfo()
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();
        $orderCheck = $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '<>', OrderModel::STATUS_ALLOCATED)->first();

        if (!empty($orderCheck)) {
            throw  new AccidentException('操作失败，只有待支付状态的订单才能设置为已付款', Code::OPERATE_FAIL);
        }

        $orderList = $this->model::whereIn('id', $this->formData['ids'])->where('order_status', OrderModel::STATUS_ALLOCATED)->get();

        /*if ($orderList->pluck('id', 'shop_id')->count() > 1) {
            throw  new AccidentException('操作失败，同一个店铺的订单才能批量设置已付款', Code::OPERATE_FAIL);
        }*/

        $paymentAmount = 0;
        foreach ($orderList as $order) {
            //物流费用
            $logisticsFee = $order->logistics_fee;

            //开启商品一口价 物流费用为sku的物流总报价
            if ($order->order_one_price === 1) {
                $logisticsFee = $order->sku_logistics_fee;
            }

            //支付金额=商品报价+物流报价+其他补价-优惠价格
            $amount = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price;

            //通过购物车购买的订单已经是美元，所以这不需要再进行换算
            //供应商改价
            if ($order->vendor_change_price > 0) {
                $amount = $order->vendor_change_price;
            }

            $paymentAmount += $amount;

            $customerId = $order->customer_id;
        }

        $availableBalance = CustomBalance::query()->where('custom_id', $customerId)->value('balance');

        $result = [
            'ids' => $this->formData['ids'],
            'payment_amount' => number_format($paymentAmount, 2, '.', ''),
            'available_balance' => $availableBalance / 100,//美分 => 美元
        ];

        return $result;
    }

    /**
     * 获取汇率转换后的价格(目前管理端暂未用到)
     * @return array
     */
    public function getExchangeRatePrice(): array
    {
        validator($this->formData, [
            'price' => 'required',
        ])->validate();

        $result = [
            'CNY' => 0,
            'USD' => 0,
            'EUR' => 0,
        ];

        //查询USD、EUR汇率
        $exchangeRate = ExchangeRateModel::query()->whereIn('currency_code', ['CNY', 'USD', 'EUR'])->pluck('custom_exchange_rate','currency_code')->toArray();
        if (empty($exchangeRate)) {
            return $result;
        }

        if (isset($exchangeRate['CNY'])) {
            $result['CNY'] = (new CurrencyConverter('CNY'))->reversedCurrenciesExchange($this->formData['price']); // USD => CNY
        }

        if (isset($exchangeRate['USD'])) {
            $result['USD'] = (new CurrencyConverter('USD'))->reversedCurrenciesExchange($this->formData['price']); // USD => USD
        }

        if (isset($exchangeRate['EUR'])) {
            $result['EUR'] = (new CurrencyConverter('EUR'))->reversedCurrenciesExchange($this->formData['price']); // USD => EUR
        }

        return $result;
    }

    /**
     * 获取汇率转换后的价格
     * @return array
     */
    public function getExchangeRate(): array
    {
        return ExchangeRateModel::query()->pluck('custom_exchange_rate','currency_code')->toArray();
    }

    /**
     *  更新sku报价信息
     */
    public function updateSkuQuotation($params): bool
    {
        $items = $params['items'] ?? [];

        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            $sku = OrderLineItem::query()->findOrFail($item['id']);

            $sku->quote_price = $item['quote_price'] ?? 0; //商品报价
            $sku->purchase_price = $item['purchase_price'] ?? 0; //采购成本
            $sku->profit = $sku->quote_price - $sku->purchase_price; //利润
            $sku->logistics_fee = $item['logistics_fee'] ?? 0; //物流报价

            $sku->save();
        }

        return true;
    }

    public function deleteItems()
    {
        DB::beginTransaction();
        try {
            $ids = $this->formData['ids'] ?? [];

            if (empty($ids)) {
                throw new AccidentException('请选择需要删除的商品', Code::OPERATE_FAIL);
            }

            //将关联订单的报价金额修改成0
            $data = [
                'vendor_price' => 0,//报价
                'vendor_change_price' => 0,//改价
                'other_supplement_price' => 0,//其他补价
                'logistics_fee' => 0,//物流费用
                'favourable_price' => 0,//优惠金额
                'logistics_compute_fee' => 0,
                'favourable_compute_price' => 0,
                'total_price' => 0,
            ];

            $items = OrderLineItem::query()->select(['order_id', 'sku', 'name'])->whereIn('id', $ids)->get();
            $orderIds = $items->pluck('order_id');

            $this->model::query()->whereIn('id', $orderIds)->update($data);

            OrderLineItem::query()->whereIn('id', $ids)->update(['quote_price' => 0]);

            OrderLineItem::query()->whereIn('id', $ids)->delete();

            $orders = $this->model::query()->whereIn('id', $orderIds)->with('lineItems')->get();
            foreach ($orders as $order) {
                if ($order->order_status > Order::STATUS_QUOTE_ASK) {
                    throw new AccidentException('已报价的订单不能删除商品', Code::OPERATE_FAIL);
                }
                if (count($order->lineItems) < 1) {
                    throw new AccidentException('单个商品的订单不能删除商品', Code::OPERATE_FAIL);
                }
            }

            $items->each(function ($item) {
                ShopOrderLogs::addLog([
                    'order_id' => $item->order_id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_DELETE_ITEM,
                    'content' => "客户删除订单SKU: {$item->sku},规格名称：{$item->name}"
                ]);
            });

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new AccidentException('操作失败:' . $e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function restoreItems()
    {
        DB::beginTransaction();
        try {
            $ids = $this->formData['ids'] ?? [];

            if (empty($ids)) {
                throw new AccidentException('请选择需要恢复的商品', Code::OPERATE_FAIL);
            }

            //将关联订单的报价金额修改成0
            $data = [
                'vendor_price' => 0,//报价
                'vendor_change_price' => 0,//改价
                'other_supplement_price' => 0,//其他补价
                'logistics_fee' => 0,//物流费用
                'favourable_price' => 0,//优惠金额
                'sku_logistics_fee' => 0,//一口价物流费用
                'logistics_compute_fee' => 0,
                'favourable_compute_price' => 0,
                'total_price' => 0,
            ];

            $items = OrderLineItem::onlyTrashed()->select(['order_id', 'sku'])->whereIn('id', $ids)->get();
            $orderIds = $items->pluck('order_id');

            $this->model::query()->whereIn('id', $orderIds)->update($data);

            OrderLineItem::query()->whereIn('id', $ids)->restore();

            OrderLineItem::query()->whereIn('id', $ids)->update(['quote_price' => 0]);

            $items->each(function ($item) {
                ShopOrderLogs::addLog([
                    'order_id' => $item->order_id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_RESTORE_ITEM,
                    'content' => "客户恢复订单SKU: {$item->sku}"
                ]);
            });

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new AccidentException('操作失败:' . $e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function getSkuQuotation(): array
    {
        validator($this->formData, [
            'order_id' => 'required|int',
            'item_id' => 'required|int',
            'goods_sku_id' => 'required|int',
        ])->validate();

        $order = $this->model::query()->with([
            'shippingAddress:id,order_id,country_code',
            'lineItems' => function ($query) {
                $query->where('id', $this->formData['item_id']);
            },
        ])->findOrFail($this->formData['order_id']);

        //获取国家ID
        $countryCode = $order->shippingAddress->country_code ?? '';
        $countryId = Country::query()->where('code', $countryCode)->value('id');

        //客户ID
        $customerId = $order->customer_id ?? '';

        //skuID
        $skuId = $this->formData['goods_sku_id'];

        //根据产品id查询所有的报价信息
        $skuQuotationData = [];
        $skuQuotationList = SkuQuotationGroupModel::query()->with([
            'skuQuotationGroupAttr' => function ($query) {
                $query->where('is_new', 1);//只获取最新的报价
            }
        ])->where('sku_id', $skuId)->get()->toArray();

        foreach ($skuQuotationList as $quotation) {
            foreach ($quotation['sku_quotation_group_attr'] as $item) {
                //组装报价信息 skuID-客户ID-国家ID
                $skuQuotationData[$quotation['sku_id']. '-'  . $quotation['custom_id'] . '-' . $quotation['country_id']][] = [
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];
            }
        }

        //sku报价参数
        $skuQuotation = [];

        //sku报价 所有客户&&所有国家 优先级：4
        $skuQuotationWithAllKey = $skuId . '-0-0';
        if (isset($skuQuotationData[$skuQuotationWithAllKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationWithAllKey];
        }

        //sku报价 所有客户&&指定国家 优先级：3
        $skuQuotationWithAllCustomerKey = $skuId . '-0-' . $countryId;
        if (isset($skuQuotationData[$skuQuotationWithAllCustomerKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationWithAllCustomerKey];
        }

        //sku报价 指定客户&&所有国家 优先级：2
        $skuQuotationWithAllCountryKey = $skuId . '-' . $customerId . '-0';
        if (isset($skuQuotationData[$skuQuotationWithAllCountryKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationWithAllCountryKey];
        }

        //sku报价 指定客户&&指定国家 优先级：1
        $skuQuotationKey = $skuId . '-' . $customerId . '-' . $countryId;
        if (isset($skuQuotationData[$skuQuotationKey])) {
            $skuQuotation = $skuQuotationData[$skuQuotationKey];
        }

        //商品购买数量
        $itemQuantity = $order->lineItems[0]->quantity ?? 0;

        //sku报价金额
        $quotationPrice = 0;
        if ($skuQuotation) {
            //组装成数量对应价格并按数量正序排序 方便计算区间报价
            $skuQuotation = array_column($skuQuotation, 'price', 'quantity');
            ksort($skuQuotation);

            foreach ($skuQuotation as $quantity => $price) {
                if ($itemQuantity >= $quantity) {
                    $quotationPrice = $price;
                }
            }
        }

        return ['sku_quotation_price' => $quotationPrice];
    }

    public function getStockOrderList()
    {
        // 设置查询条件
        $this->setFilter();

        $this->query->with([
            'lineItems',
            'warehouse',
            'custom',
        ]);

        if(isset($this->formData['status']) && !empty($this->formData['status'])) {
            $this->query->where('order_status', $this->formData['status']);
        }

        if (isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            $this->query->where('order_id', 'like', '%'.$this->formData['keyword'].'%');
        }

        //只查询备货订单
        $this->query->where('order_type', Order::ORDER_TYPE_STOCK);

        $this->query->latest();

        return parent::index();
    }

    /**
     * 备货订单-状态统计
     * @return array
     */
    public function stockOrderStatusCount(): array
    {
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['status']);
        $this->setFilter();

        if (isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            $this->query->where('order_id', 'like', '%'.$this->formData['keyword'].'%');
        }

        //只查询备货订单
        $this->query->where('order_type', Order::ORDER_TYPE_STOCK);

        $statuses = $this->query->select('order_status as status', DB::raw('count(*) as count'))->groupBy('order_status')->get();
//        $statusCounts = $statuses->pluck('count', 'status');

        $allStatuses = array_keys($this->model::statusList()); //获取所有状态
        $statusCounts = collect($allStatuses)
            ->mapWithKeys(function ($status) use ($statuses) {
                // 如果状态存在于统计结果中，返回它的计数，否则返回0
                return [$status => $statuses->firstWhere('status', $status)?->count ?? 0];
            });

        $data = [];
        foreach ($statusCounts as $status => $count) {
            $data[] = ['status' => $status, 'count' => $count];
        }

        return $data;
    }

    /**
     * 备货订单-生成入库单
     * @return bool
     */
    public function stockOrderToInbound(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $orderCheck = $this->model::whereIn('id', $this->formData['ids'])
            ->where('order_status', '<>', OrderModel::STATUS_PENDING)
            ->where('order_type', Order::ORDER_TYPE_STOCK)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只有待处理状态的订单才能 设为已采购', Code::OPERATE_FAIL);
        }

        $orderList = $this->model::query()
            ->with(['lineItems.mapping.goodsSku.goods'])
            ->whereIn('id', $this->formData['ids'])
            ->where('order_type', Order::ORDER_TYPE_STOCK)
            ->get();

        $inboundOrderService = new InboundOrderService();

        try {
            foreach ($orderList as $order) {
                $inboundData = [
                    "inbound_type" => InboundOrder::TYPE_STOCK,//入库单类型 备货入库
                    "warehouse_id" => $order->warehouse_id,//仓库ID
                    "custom_id" => $order->customer_id,//客户ID
                    "logistics_sn" => '',//物流单号
                    "stock_order_sn" => $order->order_id,//备货单号
                    "order_source" => InboundOrder::ORDER_SOURCE_FOR_CUSTOMER_BUY, //客户下单来源
                ];

                foreach ($order->lineItems as $item) {
                    $inboundData['items'][] = [
                        'goods_sku_id' => $item->mapping->goods_sku_id ?? 0,
                        'quantity' => $item->quantity ?? 0,
                    ];
                }

                $inbound = $inboundOrderService->store($inboundData);
                //更新订单状态为待入库
                if ($inbound) {
                    $order->update(['order_status' => Order::STATUS_STOCK_PENDING]);

                    $this->stockOrderToInboundAfter($inbound->id);
                }
            }
        } catch (\Exception $e) {
            info('设置待入库失败', ['file' => $e->getFile(), 'line' => $e->getLine(), 'message' => $e->getMessage(), 'params' => $this->formData['ids']]);
            return false;
        }

        return true;
    }

    /**
     * 订单待入库后的处理
     * @param $id
     * @return true|void
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/31 17:29
     */
    public function stockOrderToInboundAfter($id)
    {
        $skuService = new StockService();

        $inboundOrder = InboundOrder::with('items')->find($id);
        if (empty($inboundOrder)) {
            return true;
        }

        foreach ($inboundOrder->items as $item) {
            $skuService->inboundOrderInStorage($inboundOrder, $item, 2);
        }
    }

    /**
     * 订单发货-使用包材-新增订单包材关联信息并扣减备货库存
     */
    public function orderPackingMaterials($params): bool
    {
        validator($params, [
            'order_id' => 'required|int',
            'packing_materials' => 'required'
        ], [], [
            'order_id' => '订单id',
            'packing_materials' => '包材信息',
        ])->validate();

        $order = $this->model::query()->findOrFail($params['order_id']);

        $packingMaterials = $params['packing_materials'];

        foreach ($packingMaterials as $packing) {
            //过滤不属于当前订单的包材-批量发货时可能存在这种情况
            $orderId = $packing['order_id'] ?? 0;
            if ($orderId !== $order->id) {
                continue;
            }

            $stockId = $packing['stock_id'] ?? 0;
            $goodsSkuId = $packing['goods_sku_id'] ?? 0;
            $quantity = $packing['input_quantity'] ?? 0;
            $specName = $packing['spec_name'] ?? '';

            if (empty($stockId) || empty($goodsSkuId) || empty($quantity)) {
                continue;
            }

            //查询库存信息
            $stock = Stock::query()->findOrFail($stockId);
            if ($stock->quantity < $quantity) {
                throw new AccidentException($specName .': 库存不足，可用库存为：' . $stock->quantity, Code::OPERATE_FAIL);
            }

            //先新增订单包材信息再扣减库存
            //查询商品信息
            $skuDetail = GoodsSku::query()->with(['goods:id,spu,goods_name'])->findOrFail($goodsSkuId);
            $orderPackingMaterialsData = [
                'order_id'     => $order->id, //订单ID',
                'goods_id'     => $skuDetail->goods->id ?? 0, //商品ID',
                'goods_sku_id' => $skuDetail->id, //sku主键',
                'name'         => $skuDetail->goods->goods_name ?? '', //商品名称',
                'sku'          => $skuDetail->sku_id, //SKU',
                'spec_name'    => $skuDetail->spec_name, //规格名称',
                'quantity'     => $quantity, //数量',
                'images'       => $skuDetail->images ?? [], //商品图片',
            ];

            //添加订单包材信息
            $orderPackingMaterialsData = OrderPackingMaterialsModel::init($orderPackingMaterialsData);
            $orderPackingMaterials = OrderPackingMaterialsModel::query()->create($orderPackingMaterialsData);

            //锁定库存
            $this->orderPackingMaterialsUseStock($order, $orderPackingMaterials, $stock);
        }

        return true;
    }

    /**
     * 订单包材使用库存
     */
    public function orderPackingMaterialsUseStock($order, $item, $stock): bool
    {
        $quantity = min($item->quantity, $stock->quantity);

        $stockService = new StockService($stock);
        $lockInfo = $stockService->setOperateSn($order->order_id)
            ->autoLockStock($quantity, StockLockLog::SOURCE_ORDER_DELIVERED);

        foreach ($lockInfo as $value) {
            $data = [
                'order_id' => $order->id,
                'order_item_id' => 0,
                'order_packing_materials_id' => $item->id,
                'stock_id' => $value['stock_item']->stock_id,
                'stock_item_id' => $value['stock_item']->id,
                'lock_id' => $value['lock']->id,
                'quantity' => $value['quantity'],
                'all_quantity' => $quantity,
            ];
            OrderItemStock::query()->create($data);
        }

        return true;
    }

    public function batchUpdateDeclaration(): bool
    {
        validator($this->formData, [
            'order_ids' => 'required|array',
            'cn_name' => 'required',
            'en_name' => 'required',
            'unit_price' => 'required',
            'weight' => 'required',
        ], [], [
            'order_ids' => '订单ID',
            'cn_name' => '中文名称',
            'en_name' => '英文名称',
            'unit_price' => '申报单价',
            'weight' => '申报重量',
        ])->validate();

        //根据订单ID查询itemID
        $orderItemIds = OrderLineItem::query()->whereIn('order_id', $this->formData['order_ids'])->pluck('id');

        if ($orderItemIds->isEmpty()) {
            return false;
        }

        $orderItemIds->each(function ($itemId) {
            $this->updateDeclaration($itemId);
        });

        return true;
    }


    /**
     * @throws Exception
     */
    public function pushThirdPartyWarehouse($params)
    {
        $ids = $params['ids'] ?? [];
        if (empty($ids)) throw new AccidentException('请选择需要推送的订单', Code::OPERATE_FAIL);
        $config = ThirdPartyWarehouseConfig::getConfig();
        if (empty($config)) throw new AccidentException('暂未配置或开启第三方ERP，请前往基础配置中配置', Code::OPERATE_FAIL);
        $orders = Order::query()->whereIn('id', $ids)->get();
        $service = new ThirdPartyWarehouseService($config);
        $orders->each(function ($order) use ($service) {
//            if ($order->fulfillment_push_status == Order::FULFILLMENT_PUSH_SUCCESS) return true;
            if ($order->order_status != Order::STATUS_PENDING) return true;
            try {
                $service->pushOrderToWarehouse($order);
            } catch (Exception $e) {}

        });
        return true;
    }

    /** 订单设置为搁置
     * @return bool
     * @throws ValidationException
     */
    public function setShelve(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $statusList = [
            Order::STATUS_QUOTE_NO,
            Order::STATUS_QUOTE_ASK,
            Order::STATUS_QUOTED,
        ];

        $orderCheck = $this->model::query()->whereIn('id', $this->formData['ids'])
            ->whereNotIn('order_status', $statusList)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只有未付款状态的订单才能 设为搁置', Code::OPERATE_FAIL);
        }

        $orderList = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $orderList->each(function($item) {
            ShopOrderLogs::addLog([
                'order_id'      => $item->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_SET_SHELVE,
                'content'       => "订单设为已搁置",
            ]);
            //搁置前的状态
            $orderStatusBefore = $item->order_status;
            $item->update(['order_status_before' => $orderStatusBefore, 'order_status' => Order::STATUS_SHELVE]);
        });

        return true;
    }

    /** 订单取消搁置
     * @return bool
     * @throws ValidationException
     */
    public function cancelShelve(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $orderCheck = $this->model::query()->whereIn('id', $this->formData['ids'])
            ->whereNot('order_status', Order::STATUS_SHELVE)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只有搁置状态的订单才能 取消搁置', Code::OPERATE_FAIL);
        }

        $orderList = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $orderList->each(function($item) {
            ShopOrderLogs::addLog([
                'order_id'      => $item->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CANCEL_SHELVE,
                'content'       => "订单取消搁置",
            ]);
            //恢复搁置前的状态
            $orderStatusBefore = Order::STATUS_SHELVE;
            $item->update(['order_status_before' => $orderStatusBefore, 'order_status' => $item->order_status_before]);
        });

        return true;
    }


    /** 设为不发货
     * @return bool
     * @throws ValidationException
     */
    public function setNotShipping(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $statusList = [
            Order::STATUS_QUOTE_NO,
            Order::STATUS_QUOTE_ASK,
            Order::STATUS_QUOTED,
        ];

        $orderCheck = $this->model::query()->whereIn('id', $this->formData['ids'])
            ->whereNotIn('order_status', $statusList)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只有未付款状态的订单才能 设为不发货', Code::OPERATE_FAIL);
        }

        $orderList = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $orderList->each(function($item) {
            ShopOrderLogs::addLog([
                'order_id'      => $item->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                'content'       => "订单移入不发货",
            ]);
            //搁置前的状态
            $orderStatusBefore = $item->order_status;
            $item->update(['order_status_before' => $orderStatusBefore, 'order_status' => Order::STATUS_NOT_SHIPPING]);
        });

        return true;
    }

    /** 订单重新移入待报价
     * @return bool
     * @throws ValidationException
     */
    public function cancelNotShipping(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id'
        ])->validate();

        $orderCheck = $this->model::query()->whereIn('id', $this->formData['ids'])
            ->whereNot('order_status', Order::STATUS_NOT_SHIPPING)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只不发货状态的订单才能操作', Code::OPERATE_FAIL);
        }

        $orderList = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $orderList->each(function($item) {
            ShopOrderLogs::addLog([
                'order_id'      => $item->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                'content'       => "订单移出不发货",
            ]);
            //恢复搁置前的状态
            $item->update(['order_status_before' => Order::STATUS_NOT_SHIPPING, 'order_status' => $item->order_status_before, 'confirm_shipment_at' => now()]);
        });

        return true;
    }

    /**
     * 修改收件信息
     */
    public function batchUpdateShippingAddress(): bool
    {
        validator($this->formData, [
            'order_ids' => 'required|array',
        ], [], [
            'order_ids' => '订单ID',
        ])->validate();

        return DB::transaction(function () {
            //根据订单ID查询收件信息
            $list = OrderShippingAddress::query()->whereIn('order_id', $this->formData['order_ids'])->get();

            if ($list->isEmpty()) {
                return false;
            }

            $country = Country::query()->where('cn_name', $this->formData['country'])->orWhere('en_name', $this->formData['country'])->first();
            $this->formData['country_code'] = strtoupper($country->code ?? '');
            $this->formData['name'] = trim($this->formData['first_name'] . ' ' . $this->formData['last_name']);

            //只保留非空数据
            $this->formData = array_filter($this->formData);

            $title = [
                'first_name'   => '名',
                'last_name'    => '姓',
                'name'         => '昵称',
                'country'      => '国家/地区',
                'country_code' => '国家代码',
                'phone'        => '电话',
                'zip'          => '邮编',
                'province'     => '省/州',
                'city'         => '城市',
                'address1'     => '地址1',
                'address2'     => '地址2',
                'tax'          => '税号',
                'email'        => '邮箱',
            ];

            $intersection = array_intersect_key($this->formData, $title);
            if (empty($intersection)) {
                throw new AccidentException('操作失败，收件信息不能为空', Code::OPERATE_FAIL);
            }

            //仅填充数据
            $fillOnlyData = $this->formData['fill_only_data'] ?? false;

            $logs = [];
            $list->each(function ($item) use ($title, $fillOnlyData, &$logs) {
                //校验差异值
                $differential = differenceComparing($this->formData, $item->toArray(), $title, $fillOnlyData);

                //没有差异信息不需要更新
                $updateData = $differential['data'];
                if (empty($updateData)) {
                    return false;
                }

                $item->update($updateData);

                $logData = [
                    'order_id' => $item->order_id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ADDRESS,
                    'content' => '修改收件信息：' . $differential['content'],
                ];
                $logs[] = ShopOrderLogs::init($logData);
            });

            if ($logs) {
                ShopOrderLogs::query()->insert($logs);
            }

            return true;
        });
    }

    /**
     * 分配员工
     * @return bool
     */
    public function assignStaff(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'staff_id' => 'required|int',
        ], [], [
            'ids' => '订单id',
            'staff_id' => '员工ID',
        ])->validate();

        $statusList = [
            Order::STATUS_QUOTE_NO,
            Order::STATUS_QUOTE_ASK,
            Order::STATUS_QUOTED,
        ];

        $orderCheck = $this->model::query()->whereIn('id', $this->formData['ids'])
            ->whereNotIn('order_status', $statusList)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只有未付款状态的订单才能 分配员工', Code::OPERATE_FAIL);
        }

        return $this->model::query()->whereIn('id', $this->formData['ids'])->update(['staff_id' => $this->formData['staff_id']]);
    }

    public function getFulfillmentPushLogs($params)
    {
        $query = OrderThirdPartyFulfillmentLogs::query()->with(['operateUser']);
        if (!empty($params['order_id'])) {
            $query->where(function ($query) use ($params) {
                $query->where('order_id', $params['order_id'])->orWhere('platform_order_no', $params['order_id']);
            });

        }
        if (!empty($params['status'])) $query->where('status', $params['status']);
        if (!empty($params['type'])) $query->where('type_id', $params['type']);

        return $query->latest('id')->paginate($params['size'] ?? 10);
    }


    public function exportDianxiaomiOrder($params)
    {
        // validator($params, ['order_ids' => 'required|array'])->validate();
        return DB::transaction(function () use ($params) {
            $date = date('Y-m-d');
            $rand = rand(10000, 99999);

            if (isset($this->formData['order_ids']) && !empty($this->formData['order_ids'])) {
                $this->query->whereIn('id', $this->formData['order_ids']);
            } else {
                $this->queryCondition();
            }

            $query = $this->query;

            $fileName = "dianxiaomi-{$date}-{$rand}.xlsx";
            Excel::store(new OrderDianxiaomiExport($query), $fileName);
            $url = Storage::disk()->url($fileName);
            $this->query->update([
                'fulfillment_platform' =>  ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI
            ]);
            return ExcelExport::query()->create([
                'type' => ExcelExport::TYPE_ORDER_DIANXIAOMI,
                'name' => $fileName,
                'url' => $url,
                'status' => ExcelExport::STATUS_DONE
            ]);
        });
    }

    /**
     * 导出店小秘订单
     * @return true
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/14 11:53
     */
    public function exportDianXiaoMiOrderNew()
    {
        $filename = 'dianxiaomi_'. Carbon::now()->format('YmdHis') . '_' . Str::random(6) . '.xlsx';

        /** @var $excelExport excelExport */
        $excelExport = ExcelExport::query()->create([
            'type'  => ExcelExport::TYPE_ORDER_DIANXIAOMI,
            'name'  => $filename,
            'url'   => '',
        ]);

        dispatch(new DianXiaoMiOrderExport($excelExport, $this->formData))->onQueue('export');

        return true;
    }

    public function batchUpdateFulfillmentPlatform($params)
    {
        validator($params, [
            'order_ids' => 'required|array',
            'platform' => 'required|string'
        ])->validate();
        return Order::query()->whereIn('id', $params['order_ids'])->update([
            'fulfillment_platform' => $params['platform']
        ]);
    }


    public function batchUpdateRemark($params)
    {
        $payload = validator($params, [
            'order_ids' => 'required|array',
            'system_remark' => 'sometimes|nullable|string',
            'warehouse_remark' => 'sometimes|nullable|string',
            'only_fill_in' => 'sometimes|nullable|bool',
        ])->validate();
        return DB::transaction(function () use ($payload) {
            if (!empty($payload['system_remark'])) {
                $query = Order::query()->whereIn('id', $payload['order_ids']);
                if ($payload['only_fill_in']) $query->whereNull('system_remark');
                $query->update([
                    'system_remark' => $payload['system_remark']
                ]);
            }
            if (!empty($payload['warehouse_remark'])) {
                $query = Order::query()->whereIn('id', $payload['order_ids']);
                if ($payload['only_fill_in']) $query->whereNull('warehouse_remark');
                $query->update([
                    'warehouse_remark' => $payload['warehouse_remark']
                ]);
            }
            return true;
        });
    }


    public function importOrderLogistics()
    {
        try {
            Excel::import(new OrderDianxiaomiImport(request()->get('import_type')), request()->file('import_file'));
            return ['ret' => 1];
        } catch (ErrorDataException $e) {
            return ['ret' => 0, 'data' => $e->getErrorData()];
        }
    }

    public function syncThirdPartyWarehouse($params): string
    {
        if (count($params['ids']) > 50) {
            throw new AccidentException('选中订单数量不能大于50');
        }
        $orders = Order::query()->whereIn('id', $params['ids'])->get();
        $successCount = 0;
        $failCount = 0;

        foreach ($orders as $order) {
            if ($order->fulfillment_platform != ThirdPartyWarehouseConfig::PLATFORM_MABANG) {
                $failCount++;
                continue;
            }

            try {
                $service = new ThirdPartyWarehouseService();
                $service->syncOrderSendStatus($order);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
            }
        }
        $message = $successCount > 0 ? '成功同步' . $successCount . '单' : '';
        $message .= $failCount > 0 ? '失败' . $failCount . '单' : '';

        return $message;
    }

    public function autoQuotation()
    {
        $start = time();
        validator($this->formData, [
            'ids' => 'required|array',
            'confirm_quotation' => 'sometimes|nullable|bool',
        ])->validate();

        //超过19单异步处理自动报价
        $ids = $this->formData['ids'];
        $confirmQuotation = $this->formData['confirm_quotation'];

        if (count($ids) > 8) {
            $orders = $this->model::query()->with(['lineItems.mapping.goodsSku', 'shippingAddress'])->whereIn('id', $this->formData['ids'])->get();
            $orders->each(function ($order) use ($confirmQuotation) {
                dispatch(new AutoOrderQuoteJob($order, $confirmQuotation))->onQueue('auto-order-quote');
            });

            return ['sync' => 0, 'success_count' => 0, 'error_count' => 0];
        }

        $orderDataService = new OrderDataService();
        $orders = $this->model::query()->with(['lineItems.mapping.goodsSku', 'shippingAddress'])->whereIn('id', $this->formData['ids'])->get();
        $successCount = 0;
        $errorCount = 0;
        $orders->each(function ($order) use ($orderDataService, $confirmQuotation, &$successCount, &$errorCount) {
            try {
                DB::transaction(function () use ($orderDataService, $confirmQuotation, $order, &$successCount, &$errorCount) {
                    $orderDataService->autoOrderQuote($order, $confirmQuotation);
                });
                $successCount ++;
            } catch (Exception $e) {
                $errorCount ++;
                logger('自动报价：' . $e->getMessage());
            }
        });
        logger('执行时间：' . time() - $start);
        return ['sync' => 1, 'success_count' => $successCount, 'error_count' => $errorCount];
    }

    public function verifyStock($order, $mappings): bool
    {
        if (empty($order) || empty($mappings)) {
            throw new AccidentException('数据异常，校验库存失败');
        }

        $customerId = $order->shop->customer_id ?? 0;

        $mappings = array_column($mappings, null, 'line_item_id');

        //多个订单商品关联同一个sku
        $skuStockList = [];

        $error = [];
        $order->lineItems->each(function ($item) use ($customerId, $mappings, &$skuStockList, &$error) {
            $skuId = $mappings[$item->id]['sku_id'] ?? 0;
            $sku = $mappings[$item->id]['sku'] ?? '';

            if (!isset($skuStockList[$skuId])) {
                $skuStockList[$skuId] = $item->quantity;
            } else {
                $skuStockList[$skuId] += $item->quantity;
            }

            $itemQuantity = $skuStockList[$skuId];

            $stockQuantity = Stock::query()->where('sku_id', $skuId)->where('customer_id', $customerId)->value('quantity');

            if (empty($stockQuantity)) {
                $error[] = $sku . "：客户库存不足";
            }

            if (!empty($stockQuantity) && $stockQuantity < $itemQuantity) {
                $error[] = $sku . '：客户库存不足, 可用库存为：' . $stockQuantity;
            }
        });

        if ($error) {
            throw new AccidentException(implode('；', $error), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 打回报价中
     */
    public function orderRollbackQuote($params, $remark = ''): bool
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();

        $noSupportRollbackList = $this->model::query()->whereIn('id', $params['ids'])->whereNotIn('order_status', [OrderModel::STATUS_PENDING, OrderModel::STATUS_QUOTED])->first();
        throw_if(
            $noSupportRollbackList,
            new AccidentException('操作失败，只有代付款和已付款的订单才支持打回报价中', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            foreach ($params['ids'] as $id) {
                $order = $this->model::query()->findOrFail($id);

                $remark .= '订单打回报价中';
                $operatorType = ShopOrderLogs::OPERATOR_TYPE_ROLLBACK_QUOTE_ASK;
                //已付款订单打回报价中需退款
                if ($order->order_status == $this->model::STATUS_PENDING) {
                    $remark .= ',';
                    $this->orderFullRefund($order, $remark);

                    $operatorType = ShopOrderLogs::OPERATOR_TYPE_ORDER_REFUND;
                }

                $order->order_status_before = $order->order_status;
                $order->order_status = Order::STATUS_QUOTE_ASK;
                $order->refund_price = 0;
                $order->financial_status = Order::FINANCIAL_STATUS_UNPAID;

                $order->save();

                ShopOrderLogs::addLog([
                    'order_id'      => $order->id,
                    'operator_type' => $operatorType,
                    'content'       => $remark,
                ]);
                // 删除订单包裹
                (new PackageService())->orderRollbackSync($order);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 订单全额退款
     * @param $order
     * @param $remark
     * @return false
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/30 18:37
     */
    public function orderFullRefund($order, &$remark)
    {
        // 订单未付款
        if ($order->financial_status === Order::FINANCIAL_STATUS_UNPAID) {
            return false;
        }

        //物流费用
        $logisticsFee = $order->logistics_fee;

        //开启商品一口价 物流费用为sku的物流总报价
        if ($order->order_one_price === 1) {
            $logisticsFee = $order->sku_logistics_fee;
        }

        //支付金额=商品报价+物流报价+其他补价-优惠价格
        $amount = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price;

        //已经存在退款金额
        if ($order->refund_price > 0) {
            $amount -= $order->refund_price;

            //忽略0.1的精度
            if ($amount < 0.1) $amount = 0;
        }

        //通过购物车购买的订单已经是美元，所以这不需要再进行换算
        //供应商改价
        if ($order->vendor_change_price > 0) {
            $amount = $order->vendor_change_price;
        }

        //补收金额 USD
        if ($order->supplement_price > 0) {
            $supplementPrice = $order->supplement_price;
            $amount = sprintf("%.2f", $amount + $supplementPrice);
            $appendRemark = "订单退款，退款金额：{$amount} USD，包含补收费用 {$supplementPrice} USD";
        } else {
            $appendRemark = "订单退款，退款金额：{$amount} USD";
        }

        if ($order->refund_price > 0) {
            $amount =  sprintf("%.2f", $amount - $order->refund_price);
        }

        // 已退款无需退款
        if ($amount <= 0) {
            $appendRemark = "订单已退款，无需退款";
            $remark .= $appendRemark;
            return false;
        }

        $remark .= $appendRemark;

        $order->refund_price += $amount;
        $order->financial_status = Order::FINANCIAL_STATUS_FULL_REFUND;// 全额退款
        $order->save();

        $balance = new BalanceService($order->customer_id);
        // 操作退款 增加客户余额
        if ($amount) {
            $balance
                ->setRemark($remark)
                ->setRelationId($order->id)
                ->increase($amount, BalanceRecord::SOURCE_ORDER_REFUND, $order->name ? $order->name : $order->order_id);

            // 同步更新到马帮的其他收入和其他支出金额(出库后再同步)
            /*if ($order->fulfillment_platform == ThirdPartyWarehouseConfig::PLATFORM_MABANG) {
                $thirdPartyWarehouseService = new ThirdPartyWarehouseService();
                $thirdPartyWarehouseService->updateOrderData($order, OrderThirdPartyFulfillmentLogs::UPDATE_ORDER_REFUND_AND_SUPPLEMENT_AMOUNT);
            }*/
        }
    }

    /**
     * 订单退款
     */
    public function orderRefund($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'refund_amount' => 'required|numeric',
            'refund_type'   => 'required|integer',
        ])->validate();

        $unpaidOrder = $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '<', OrderModel::STATUS_PENDING)->first();
        throw_if(
            $unpaidOrder,
            new AccidentException('操作失败，只有付款完成的订单才支持退款', Code::OPERATE_FAIL)
        );

        //锁定
        $lockName = 'order_refund' . getAdminId();
        $lock = Cache::lock($lockName, 5);
        if (!$lock->get()) {
            throw_if(
                true,
                new AccidentException('退款操作频繁...', Code::OPERATE_FAIL)
            );
        }

        $refundTypeName = ChargeTypesModel::where('id', intval($params['refund_type']))
            ->where('type', ChargeTypesModel::TYPE_ORDER_REFUND)
            ->value('name');

        $isBatchOperate = $params['is_batch_operate'] ?? 0;
        DB::beginTransaction();
        try {
            foreach ($params['ids'] as $id) {
                $order = $this->model::query()->findOrFail($id);

                //物流费用
                $logisticsFee = $order->logistics_fee;

                //开启商品一口价 物流费用为sku的物流总报价
                if ($order->order_one_price === 1) {
                    $logisticsFee = $order->sku_logistics_fee;
                }

                //支付金额=商品报价+物流报价+其他补价-优惠价格
                $amount = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price ;

                //通过购物车购买的订单已经是美元，所以这不需要再进行换算
                //供应商改价
                if ($order->vendor_change_price > 0) {
                    $amount = $order->vendor_change_price;
                }

                $statusName = Order::getStatusName($order->order_status);

                //再加上补收费用
                if ($order->supplement_price > 0) {
                    $supplementPrice = $order->supplement_price;
                    $amount = sprintf("%.2f", $amount + $supplementPrice);
                }
                //退款金额不能大于订单支付金额
                $amount = customNumberFormat($amount);
                $refundAmount = (float)$params['refund_amount'];

                if ($refundAmount > $amount) {
                    if ($isBatchOperate == 1) {
                        continue;
                    } else {
                        throw new AccidentException('退款金额不能大于支付金额', Code::OPERATE_FAIL);
                    }
                }

                $financialStatus = Order::FINANCIAL_STATUS_REBATES; //部分退款
                if ($refundAmount === $amount) {
                    $financialStatus = Order::FINANCIAL_STATUS_FULL_REFUND; //全额退款
                }

                if ($order->refund_price > 0) {
                    $totalRefundAmount = customNumberFormat($order->refund_price + $refundAmount);
                    if ($totalRefundAmount > $amount) {
                        if ($isBatchOperate == 1) {
                            continue;
                        } else {
                            $availableRefundAmount = (float) $amount - $order->refund_price;
                            throw new AccidentException('累计退款金额不能大于支付金额，当前可退款金额为：' . $availableRefundAmount, Code::OPERATE_FAIL);
                        }
                    }

                    if ($totalRefundAmount === $amount) {
                        $financialStatus = Order::FINANCIAL_STATUS_FULL_REFUND; //全额退款
                    }
                }

                $logContent = "订单退款【{$statusName}】，退款金额：{$refundAmount} USD，退款类型：" . $refundTypeName . "，退款备注：" . ($params['refund_remark'] ?? '');

                $balance = new BalanceService($order->customer_id);
                // 操作退款 增加客户余额
                $balance
                    ->setRemark($params['refund_remark'] ?? '')
                    ->setRelationId($order->id)
                    ->increase($refundAmount, BalanceRecord::SOURCE_ORDER_REFUND, $order->name ? $order->name : $order->order_id);

                $order->refund_price += $refundAmount;
                $order->financial_status = $financialStatus; //财务状态
                if(strlen($order->refund_type_str) < 1300){
                    $order->refund_type_str = empty($order->refund_type_str) ? $params['refund_type'] : $order->refund_type_str . ',' . $params['refund_type']; //退款类型
                }
                $order->save();

                // 同步更新到马帮的其他收入和其他支出金额(出库后再同步)
                if ($order->fulfillment_platform == ThirdPartyWarehouseConfig::PLATFORM_MABANG && ($order->order_status == Order::STATUS_SHIPPED || $order->order_status == Order::STATUS_DELIVERED)) {
                    $thirdPartyWarehouseService = new ThirdPartyWarehouseService();
                    $thirdPartyWarehouseService->updateOrderData($order, OrderThirdPartyFulfillmentLogs::UPDATE_ORDER_REFUND_AND_SUPPLEMENT_AMOUNT);
                }

                //记录日志
                ShopOrderLogs::addLog([
                    'order_id'      => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_REFUND,
                    'content'       => $logContent,
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        //解锁
        $lock->release();

        return true;
    }

    /**
     * 禁止/恢复处理
     * @return bool
     */
    public function setDisableStatus(): bool
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'is_disable' => 'required|int'
        ])->validate();

        $statusList = [
            Order::STATUS_WAIT_PRINT_IN_STOCK,
            Order::STATUS_WAIT_PRINT_OUT_STOCK,
        ];

        $orderCheck = $this->model::query()->whereIn('id', $this->formData['ids'])
            ->whereNotIn('order_status', $statusList)
            ->first();

        if (!empty($orderCheck)) {
            throw new AccidentException('操作失败，只有配货的订单才能设置 禁止/恢复处理', Code::OPERATE_FAIL);
        }

        if ($this->formData['is_disable'] === 1) {
            $isDisable = Order::IS_DISABLE_YES;
        } else {
            $isDisable = Order::IS_DISABLE_NO;
        }

        $this->model::query()->whereIn('id', $this->formData['ids'])->update(['is_disable' => $isDisable]);
        return true;
    }

    /**
     * 导入订单
     * @return mixed
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/13 15:57
     */
    public function importNew()
    {
        $file = request()->file('file');

        if (empty($file)) {
            throw new AccidentException('导入文件不能为空', Code::OPERATE_FAIL);
        }

        $extension = $file->getClientOriginalExtension();

        if (!in_array($extension, ['xls', 'xlsx', 'csv'])) {
            throw new AccidentException('文件格式错误，请上传excel文件', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($file) {
            $import = new AdminOrderImportMain();
            Excel::import($import, $file);
        });
    }

    /**
     * 补收费用
     */
    public function supplementFee($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'charge_type_id' => 'required|int',
            'supplement_price' => 'required|numeric'
        ])->validate();

        $unpaidOrder = $this->model::query()->whereIn('id', $this->formData['ids'])->where('order_status', '<', OrderModel::STATUS_PENDING)->first();
        throw_if(
            $unpaidOrder,
            new AccidentException('操作失败，只有付款完成的订单才支持补收费用', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            $chargeTypeName = ChargeTypesModel::query()->where('id', $params['charge_type_id'])->value('name');

            foreach ($params['ids'] as $id) {
                $order = $this->model::query()->findOrFail($id);

                //补收金额
                $amount = $params['supplement_price'];

                $balance = new BalanceService($order->customer_id);
                // 补收费用 扣减客户余额
                $balance->setRelationId($order->id)->deduction($amount, BalanceRecord::SOURCE_SUPPLEMENT_FEE, $order->name ? $order->name : $order->order_id);

                $order->charge_type_id = $params['charge_type_id']; //费用类型id
                $order->supplement_price += $params['supplement_price']; //费用金额 USD
                $order->supplement_charge_remark = $params['supplement_charge_remark'] ?? ''; //费用说明
                $order->financial_status = Order::FINANCIAL_STATUS_SUPPLEMENT; //财务状态 补收费用
                $order->save();

                // 同步更新到马帮的其他收入和其他支出金额（出库后再同步）
                if ($order->fulfillment_platform == ThirdPartyWarehouseConfig::PLATFORM_MABANG && ($order->order_status == Order::STATUS_SHIPPED || $order->order_status == Order::STATUS_DELIVERED)) {
                    $thirdPartyWarehouseService = new ThirdPartyWarehouseService();
                    $thirdPartyWarehouseService->updateOrderData($order, OrderThirdPartyFulfillmentLogs::UPDATE_ORDER_REFUND_AND_SUPPLEMENT_AMOUNT);
                }

                //记录日志
                $content = "补收费用，费用类型：{$chargeTypeName}，费用金额：{$params['supplement_price']} USD, 费用说明：" . ($params['supplement_charge_remark'] ?? '');
                $logData = [
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_SUPPLEMENT_FEE,
                    'content' => $content,
                ];
                ShopOrderLogs::addLog($logData);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 订单取消(并处理移除平台异常状态)
     */
    public function orderCancel($params): bool
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();

        throw_if(
            $this->model::query()->whereIn('id', $params['ids'])->whereNotIn('order_status', OrderModel::QUOTED_BUT_NOT_COMPLETE)->first(),
            new AccidentException('操作失败，只能取消已处理但未完成的订单', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            foreach ($params['ids'] as $id) {
                $order = $this->model::query()->findOrFail($id);
                $order->order_status_before = $order->order_status; //取消前的订单状态
                $order->order_status = Order::STATUS_CANCELLED;
                $order->save();

                (new OrderBaseService($order))->dealOrderAbnormal(ShopOrderAbnormal::DEAL_TYPE_ORDER_CANCEL);

                //记录日志
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CANCEL,
                    'content' => '管理员操作取消订单',
                ]);

                $packageService = new PackageService();
                $order->packages->each(function ($package) use ($packageService) {
                    $packageService->cancel($package);
                });
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /** 订单取消并退款
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function orderCancelAndRefund($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'remove_platform_abnormal' => 'sometimes|nullable|bool'
        ])->validate();

        return DB::transaction(function () use ($params) {
            $this->orderCancel($params);
            $orders = $this->model::query()->whereIn('id', $params['ids'])->get();

            foreach ($orders as $order) {
                $remark = '';
                $this->orderFullRefund($order, $remark);

                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_REFUND,
                    'content' => $remark,
                ]);
            }
            return true;
        });
    }

    /**
     * 订单取消撤回
     * @param $params
     * @return mixed
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/30 19:14
     */
    public function orderCancelWithdraw($params)
    {
        validator($params, [
            'ids' => 'required|array',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $orders = $this->model::query()->whereIn('id', $params['ids'])->get();
            foreach ($orders as $order) {
                if ($order->order_status !== Order::STATUS_CANCELLED) {
                    throw new AccidentException("订单{$order->order_id}不是取消状态");
                }
                $remark = '订单撤回取消，';
                if ($order->financial_status === Order::FINANCIAL_STATUS_FULL_REFUND) {
                    $order->order_status = Order::STATUS_QUOTE_ASK;
                    $remark .= '订单全额已退款，打回报价中';
                } else {
                    $order->order_status = $order->order_status_before;
                    $remark .= '返回之前的状态' . Order::statusList()[$order->order_status_before] ?? '-';
                }
                $order->save();
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_REFUND,
                    'content' => $remark,
                ]);
            }
            return true;
        });
    }

    /**
     * 同步平台发货
     */
    public function syncPlatformFulfillment($params): bool
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();

        $orders = $this->model::query()->where('logistics_status', Order::LOGISTICS_APPLY_SUCCESS)->whereIn('id', $params['ids'])->get();

        throw_if(
            count($orders) != count($params['ids']),
            new AccidentException('操作失败，只能操作已申请运单的订单', Code::OPERATE_FAIL)
        );
        $orderCount = count($orders);
        $orders->each(function ($order) use ($orderCount) {
            $order->packages->each(function ($package) use ($orderCount, $order) {
                (new PackageBaseService($package))->packagePlatformDelivery($orderCount == 1, 4, true, $order->id);
            });
        });
        return true;
    }

    /**
     * 调整报价
     * @param $params
     * @return bool
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/25 11:40
     */
    public function changeQuotePrice($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'favourable_price' => 'sometimes|nullable|string',
            'charge_type_id' => 'sometimes|nullable|int',
            'other_supplement_price' => 'sometimes|nullable|string',
        ])->validate();

        $allowStatus = [$this->model::STATUS_QUOTE_NO, $this->model::STATUS_QUOTE_ASK, $this->model::STATUS_QUOTED];

        $list = $this->query
            ->whereIn('id', $params['ids'])
            ->whereIn('order_status', $allowStatus)
            ->get();

        if (empty($list)) {
            return true;
        }

        DB::beginTransaction();
        try {
            foreach ($list as $value) {
                $data = [
                    'favourable_price'          => $params['favourable_price'] ?? $value->favourable_price,
                    'charge_type_id'          => $params['charge_type_id'] ?? $value->charge_type_id,
                    'other_supplement_price'    => $params['other_supplement_price'] ?? $value->other_supplement_price,
                ];

                $value->update($data);


                ShopOrderLogs::addLog([
                    'order_id' => $value->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CHANGE_QUOTE_PRICE,
                    'content' => "调整报价: 优惠价格: {$data['favourable_price']}; 补收类型: {$data['charge_type_id']}; 补收价格: {$data['other_supplement_price']}",
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            info('订单改价失败', ['message' => $e->getMessage()]);
            DB::rollBack();
            return false;
        }
        return true;
    }

    /**
     * 恢复订单
     * @param $params
     * @return bool
     * @throws Throwable
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/21 19:12
     */
    public function restore($params)
    {
        validator($params, [
            'ids' => 'required|array',
        ])->validate();

        $ids = $params['ids'];

        $orderList = $this->query
            ->whereIn('id', $ids)
            ->where('order_status', $this->model::STATUS_CANCELLED)
            ->select(['id', 'order_status', 'order_status_before'])
            ->get();

        throw_if(
            $orderList->isEmpty(),
            new AccidentException('操作失败，只能恢复已取消的订单', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            foreach ($orderList as $order) {
                $data = [
                    'order_status_before' => $order->order_status,
                    'order_status' => $order->order_status_before !== Order::STATUS_CANCELLED ? $order->order_status_before : $this->model::STATUS_QUOTE_ASK,
                ];

                $order->update($data);

                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_RESTORE_ORDER,
                    'content' => "后台操作恢复订单",
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info('恢复订单失败', ['file' => $e->getFile(), 'line' => $e->getLine(), 'message' => $e->getMessage()]);
            return false;
        }

        return true;
    }

    /**
     * 移除平台异常状态（移入报价中）
     * @param $params
     * @return mixed
     * @throws ValidationException
     */
    public function abnormalMoveToQuote($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();
        return DB::transaction(function () use ($params) {
            $orders = Order::query()->whereIn('id', $params['ids'])->where('abnormal_status', Order::ORDER_STATUS_ABNORMAL)->get();
            if (count($orders) < count($params['ids'])) {
                throw new AccidentException('必须是异常订单才能操作', Code::OPERATE_FAIL);
            }

            foreach ($orders as $order) {
                (new OrderBaseService($order))->dealOrderAbnormal(ShopOrderAbnormal::DEAL_TYPE_MOVE_TO_QUOTING);
                $this->orderRollbackQuote([
                    'ids' => [$order->id]
                ]);
                # todo 操作出库单取消或异常
            }
            return true;
        });
    }
    /**
     * 子状态数量统计
     * @throws Exception
     */
    public function subStatusCount($type)
    {
        //删除对应状态查询条件
        unset($this->formData[$type]);
        $this->filters = [];
        $this->setFilterRules();
        $this->setFilter();
        $this->queryCondition();

        switch ($type) {
            case 'sku_status':
            case 'logistics_status':
            case 'stock_status':
            case 'financial_status':
                return (clone $this->query)->select("{$type} as status", DB::raw('count(*) as count'))->groupBy($type)->get();
            case 'abnormal_reason':
                $ids = (clone $this->query)->select('id')->get()->pluck('id')->toArray();
                return ShopOrderAbnormal::query()->where('deal_status', ShopOrderAbnormal::STATUS_WAIT_DEAL)->whereIn('order_id', $ids)
                    ->select("abnormal_reason as status", DB::raw('count(*) as count'))->groupBy('abnormal_reason')->get();
            case 'other_status':
                return (clone $this->query)->whereIn('order_status', [Order::STATUS_CANCELLED, Order::STATUS_SHELVE])
                    ->select("order_status as status", DB::raw('count(*) as count'))->groupBy('order_status')->get();
            default:
                throw new AccidentException('非法状态', Code::OPERATE_FAIL);
        }
    }

    /**
     * 移除平台异常状态(忽略异常)
     * @param $params
     * @return mixed
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/7 14:13
     */
    public function ignoreAbnormal($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();
        return DB::transaction(function () use ($params) {
            $orders = Order::query()->whereIn('id', $params['ids'])->where('abnormal_status', Order::ORDER_STATUS_ABNORMAL)->get();
            if (count($orders) < count($params['ids'])) {
                throw new AccidentException('必须是异常订单才能操作', Code::OPERATE_FAIL);
            }

            foreach ($orders as $order) {
                (new OrderBaseService($order))->dealOrderAbnormal(ShopOrderAbnormal::DEAL_TYPE_IGNORE_ABNORMAL);
            }
            return true;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return array
     * @throws AccidentException
     * @throws ValidationException
     */
    public function generationQuoteInfo($id, $params)
    {
        validator($params, [
            'logistics_type' => 'sometimes|nullable|int',
            'custom_logistic_price' => 'sometimes|nullable|numeric',
            'custom_favourable_price' => 'sometimes|nullable|numeric',
            'other_supplement_price' => 'sometimes|nullable|numeric',
            'exchange_rates' => 'sometimes|nullable|numeric',
            'mapping_list' => 'sometimes|nullable|array',
            'is_submit_quote' => 'sometimes|nullable|boolean',
            'order_one_price' => 'sometimes|nullable|int',
            'use_customer_stock' => 'sometimes|nullable|int',
        ])->validate();
        $order = Order::query()->findOrFail($id);
        $quoteService = new OrderQuoteService($order);
        $params['goods_once_price'] = Custom::query()->where('id', $order->customer_id)->value('goods_once_price');
        if ($order->order_status > 1) {
            $params['exchange_rates'] = $order->exchange_rates;
            // 客户产品利润
            $params['product_quote_default_profit_rate'] = $order->product_profit;
            // 客户物流利润
            $params['freight_quote_default_profit_rate'] = $order->freight_profit;
        } else {
            // 客户产品利润
            $params['product_quote_default_profit_rate'] = CustomsQuoteConfig::query()->where('customer_id', $order->customer_id)->value('product_quote_default_profit_rate');
            // 客户物流利润
            $params['freight_quote_default_profit_rate'] = CustomsQuoteConfig::query()->where('customer_id', $order->customer_id)->value('freight_quote_default_profit_rate');
        }
        $params['customer_id'] = (int) $order->customer_id;//加上订单客户ID用来计算运费报价
        return $quoteService->setLog(false)->orderQuote($params);
    }

    /**
     * @param $params
     * @return array|string
     * @throws AccidentException
     * @throws ValidationException
     */
    public function shippingCostEstimate($params)
    {
        validator($params, [
            'code' => 'nullable|string',
            'weight' => 'required|numeric|gt:50',
            'prop_id' => 'sometimes|nullable',
            'quote_id' => 'required|int'
        ])->validate();
//        $params['postcode'] = '3809';

        $orderWeight = 0;
        if (in_array($params['quote_id'], [6,7]))
        {
            $orderWeight = $params['weight'];
            $params['prop_id'] = 1;
            if ($orderWeight > 3000) {
                $params['weight'] -= 3000;
            }
        }

        $express_line_ids = ExpressLineQuoteModel::where('quote_id', $params['quote_id'])
            ->whereNull('deleted_at')->pluck('express_line_id')->toArray();

        $expressLines = ExpressLineModel::with('props:id,name')
            ->with('regions:id,express_line_id,reference_time')
            ->with('regions.areas')
            ->with('regions.postcodeAreas')
            ->with('regions.prices')
            ->with('prices:id,express_line_id,type,start,end,price,unit_weight,first_weight')
            ->with('priceRules:id,express_line_id,type,start,end,unit_weight')
            ->whereHas('regions', function ($query) {
                $query->where('enabled', 1);
            })
            ->whereIn('id', $express_line_ids)
            ->where('enabled', 1)
            ->where('is_hidden', 0)
            ->get();

        $params['country_id'] = Country::query()->where('code', $params['code'])->value('id');
        $expressLines = $this->filterByRegion($expressLines, $params);

        if ($expressLines->isEmpty()) {
            return 'No line is available in the current area!';
        }

        $expressLines = $expressLines->when([$params['prop_id']] ?? null, function ($expressLines) use ($params) {
            return $expressLines->filter(function ($value) use ($params) {
                return array_diff([$params['prop_id']], $value->props->modelKeys()) === [];
            });
        });

        $channelList = [];
        if ($params['package_ids'] ?? null) {
            $packages = Package::whereIn('id', [$params['package_ids']])->get();
            /** @var ExpressLineModel $expressLine */
            foreach ($expressLines as $expressLine) {
                $countWeight = $this->getExpectedWeight($expressLine, $packages);
                //忽略最小重量时重量上浮为最小重量
                if ($countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
                    $countWeight = $expressLine->min_weight;
                }
                //计费重量上浮
                if ($expressLine->weight_rise) {
                    $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
                }

                $expressLine->count_weight = $countWeight ?? 0;

                $region = $expressLine->getRegionByArea(
                    $params['country_id'], null,
                    null, $params['postcode'],
                    $this->isStation, $this->withPostArea
                );

                unset($expressLine->regions, $region->expressLine);

                $user = $packages->first()->owner;

                $region = $region->load([
                    'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                    'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                ]);

                //过滤超重的运费模板
                if ($this->filterOverweight($region, $countWeight)) continue;

                $fee = [$expressLine->count_first, $expressLine->count_next] = $expressLine
                    ->getExpressFeeNew($region, $countWeight, true, true, user: $user, useVolume: $useVol);

                $expressLine->region = ExpressLineRegionInfo::make($region);
                $expressLine->expire_fee = array_sum($fee);

                unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);

                $channelList[] = $expressLine->toArray();
            }
        } else {
            /** @var ExpressLineModel $expressLine */
            foreach ($expressLines as $expressLine) {
                //因为这个重量会改变，所以需要重新赋值
                $countWeight = $params['weight'] ?? 0;
                //忽略最小重量时重量上浮为最小重量
                if ((int)$countWeight > 0 && $countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
                    $countWeight = $expressLine->min_weight;
                }
                //计费重量上浮
                if ($expressLine->weight_rise) {
                    $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
                }

                $expressLine->count_weight = $countWeight;
                // 不能确定是什么分区的时候 或者是 查询推荐线路的时候
                // 默认第一个区域的价格
                if (!isset($params['country_id']) || ($params['is_great_value'] ?? 0)) {
                    $region = $expressLine->regions->firstWhere('enabled', 1);
                } else {
                    $region = $expressLine->getRegionByArea(
                        $params['country_id'], null, null,
                        $params['postcode'] ?? '', $this->isStation, $this->withPostArea
                    );
                }
                // 如果没有匹配到区域，则跳过
                if (!$region) {
                    continue;
                }

                $regions = $expressLine->regions;
                unset($region->expressLine, $expressLine->regions);

                $expressLine->regions = ExpressLinePriceRegionList::collection($regions);

                $region = $region->load([
                    'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                    'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                ]);

                //过滤超重的运费模板
                if ($this->filterOverweight($region, $countWeight)) continue;

                $expressLine->region = ExpressLineRegionInfo::make($region);

                if ($countWeight) {
                    [$expressLine->count_first, $expressLine->count_next] = $expressLine
                        ->getExpressFeeNew($region, $countWeight,
                            true, true,
                            useVolume: /*$expressLine->base_mode === ExpressLine::BASE_MODE_VOLUME*/ false
                        );

                    $expressLine->expire_fee = array_sum([$expressLine->count_first, $expressLine->count_next]);
                } else {
                    [$expressLine->count_first, $expressLine->count_next] = [0, 0];
                    $expressLine->expire_fee = 0;
                }

                unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);

                if ($countWeight === 0
                    || $this->isWeightMatched($expressLine, $countWeight)
                ) {
                    if ($expressLine->region->name != '全国区域') {
                        $expressLine->name = $expressLine->name . '- ' . $expressLine->region->name;
                    }
                    $channelList[] = $expressLine->toArray();
                }
            }
        }
        if ($channelList === [] && isset($countWeight)) {
            //当前计费重量/体积 0kg 暂无可用线路
            return 'Current billed weight/volume: ' . ($countWeight / 1000) . 'kg, No lines available!';
        }

        $list = [];
        foreach ($channelList as &$channel) {
            $referenceTime = $channel['region']?->reference_time ?? '';
            if ($referenceTime) {
                $channel['reference_time'] = $referenceTime;
            }
            $country_name = Country::query()->where('code', $params['code'])->value('cn_name');
            $channel['country_name'] = $country_name;
            $channel['count_weight'] = $channel['count_weight'] / 1000;
            $channel['prop_name'] = $channel['props'][0]['prop_name'];

            $count_next = bcdiv($channel['count_next'], $channel['count_weight'], 4);
            $unitPrice = round(bcdiv($count_next, 100, 3), 2);
            $serviceFee = round(bcdiv($channel['count_first'], 100, 3), 2);
            $logisticsFee = round(bcdiv($channel['expire_fee'], 100, 3), 2);
            if ($orderWeight > 3000) {
                $channel['count_weight'] = $orderWeight / 1000;
            }
            $list[] = [
                'id' => $channel['id'],
                'name' => $channel['name'],
                'channel_code' => $channel['channel_code'],
                'express_company_name' => $channel['express_company_name'],
                'reference_time' => $channel['reference_time'],
                'myLogisticsId' => $channel['myLogisticsId'],
                'myLogisticsChannelId' => $channel['myLogisticsChannelId'],
                'count_weight' => $channel['count_weight'],
                'unit_price' => $unitPrice,
                'service_fee' => $serviceFee,
                'logistics_fee' => $logisticsFee,
                'country_name' => $channel['country_name'],
                'prop_name' => $channel['prop_name']
            ];
        }
        return $list;
    }

    /**
     * @param $expressLines
     * @param array $data
     * @return mixed
     */
    protected function filterByRegion($expressLines, array $data)
    {
        return $expressLines->filter(function ($value) use ($data) {
            $regions = $value->regions->filter(function (ExpressLineRegion $v) {
                // 已经启用 且 包含非零的价格即认为已经设置好了
                return $v->enabled && $v->prices->filter(fn($p) => $p->price >= 0)->count();
            });
            foreach ($regions as $region) {
                if ($region->type === ExpressLineRegion::TYPE_AREA) {
                    if ($region->areas->contains(function ($v) use ($data) {
                        return ExpressLineModel::verifyArea($v, $data);
                    })) {
                        return true;
                    }
                } else {
                    if ($region->country_id == $data['country_id']
                        && ($region->postcodeAreas->contains(function ($area) use ($data) {
                                if ($area->type === ExpressLineRegionPostcodeArea::TYPE_RANGE) {

                                    //判断加拿大邮编范围做特殊处理
                                    if ($data['country_id'] == ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
                                        $validator = Validator::make($data, [
                                            'postcode' => ['required', new CanadianPostalCodeRange($area->start, $area->end)],
                                        ]);

                                        return $validator->passes();
                                    } else {
                                        return postcode_integer($data['postcode'] ?? '') >= postcode_integer($area->start)
                                            && postcode_integer($data['postcode'] ?? '') <= postcode_integer($area->end);
                                    }

                                } elseif ($area->type === ExpressLineRegionPostcodeArea::TYPE_FIXED) {
                                    return ($data['postcode'] ?? '') == $area->start && !$area->end;
                                }

                                return false;
                            })
                            || $this->withPostArea)
                    ) {
                        return true;
                    }
                }
            }
            return false;
        });
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param array $packages
     * @return float
     */
    public function getExpectedWeight(ExpressLineModel $expressLine, array $packages): float
    {
        return $packages->reduce(function ($fee, $package) use ($expressLine) {

            $countWeight = $package->package_weight / 1000;

            return $fee + $countWeight;
        }, 0);
    }

    /**
     * @param $region
     * @param $countWeight
     * @return bool
     */
    protected function filterOverweight($region, $countWeight): bool
    {
        $minWeight = $region->prices->min('start');
        $maxWeight = $region->prices->max('end');

        if ($countWeight < $minWeight || $countWeight >= $maxWeight) return true;

        return false;
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param $countWeight
     * @return bool
     */
    protected function isWeightMatched(ExpressLineModel $expressLine, $countWeight): bool
    {
        //符合重量限制或者符合多箱重量限制
        return (in_array($expressLine->mode, [ExpressLineModel::MODE_1, ExpressLineModel::MODE_GRADE_NEXT, ExpressLineModel::MODE_RANGE_FIRST_NEXT])
                && $expressLine->max_weight >= $countWeight
                && $expressLine->min_weight <= $countWeight
            )
            || (
                in_array($expressLine->mode, [ExpressLineModel::MODE_2, ExpressLineModel::MODE_MIX])
                && ($expressLine->max_weight >= $countWeight && $expressLine->multi_box_min_weight <= $countWeight)
                && $expressLine->multi_boxes
            )
            || (
                in_array($expressLine->mode, [ExpressLineModel::MODE_2, ExpressLineModel::MODE_MIX])
                && $expressLine->max_weight >= $countWeight
                && $expressLine->min_weight <= $countWeight
                && !$expressLine->multi_boxes
            );
    }

    /**
     * @param $params
     * @return array
     * @throws AccidentException
     * @throws ValidationException
     */
    public function getLogisticsChannels($params)
    {
        validator($params, [
            'code' => 'nullable|string',
            'prop_id' => 'required|int',
            'quote_id' => 'required|int',
            'weight' => 'required|array',
            'customer_ids' => 'required|array',
        ])->validate();

        $customer_id = array_unique($params['customer_ids']);
        $count = Custom::query()->whereIn('id', $customer_id)->where('goods_once_price', 1)->count();
        if ($count > 0) {
            throw new AccidentException('一口价无法使用此功能！', Code::OPERATE_FAIL);
        }

        // 缓存国家信息（有效期1小时）
        $country = Cache::remember(
            "country_info_{$params['code']}",
            3600,
            fn() => Country::query()
                ->where('code', $params['code'])
                ->select('id', 'cn_name')
                ->first()
        );
        $params['country_id'] = $country?->id;

        $express_line_ids = ExpressLineQuoteModel::where('quote_id', $params['quote_id'])
            ->whereNull('deleted_at')->pluck('express_line_id')->toArray();

        $expressLines = ExpressLineModel::with('props:id,name')
            ->with('regions:id,express_line_id,reference_time')
            ->with('regions.areas')
            ->with('regions.postcodeAreas')
            ->with('regions.prices')
            ->with('prices:id,express_line_id,type,start,end,price,unit_weight,first_weight')
            ->with('priceRules:id,express_line_id,type,start,end,unit_weight')
            ->whereHas('regions', function ($query) {
                $query->where('enabled', 1);
            })
            ->whereIn('id', $express_line_ids)
            ->where('enabled', 1)
            ->where('is_hidden', 0)
            ->get();

        $expressLines = $this->filterByRegion($expressLines, $params);
        $expressLines = $expressLines->when([$params['prop_id']] ?? null, function ($expressLines) use ($params) {
            return $expressLines->filter(function ($value) use ($params) {
                return array_diff([$params['prop_id']], $value->props->modelKeys()) === [];
            });
        });
        $channelList = [];
        /** @var ExpressLineModel $expressLine */
        foreach ($expressLines as $expressLine) {
            //因为这个重量会改变，所以需要重新赋值
            $countWeight = max($params['weight']) * 1000;
            //忽略最小重量时重量上浮为最小重量
            if ((int)$countWeight > 0 && $countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
                $countWeight = $expressLine->min_weight;
            }
            //计费重量上浮
            if ($expressLine->weight_rise) {
                $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
            }

            $expressLine->count_weight = $countWeight;
            // 不能确定是什么分区的时候 或者是 查询推荐线路的时候
            // 默认第一个区域的价格
            if (!isset($params['country_id']) || ($params['is_great_value'] ?? 0)) {
                $region = $expressLine->regions->firstWhere('enabled', 1);
            } else {
                $region = $expressLine->getRegionByArea(
                    $params['country_id'], null, null,
                    $params['postcode'] ?? '', $this->isStation, $this->withPostArea
                );
            }
            // 如果没有匹配到区域，则跳过
            if (!$region) {
                continue;
            }

            $regions = $expressLine->regions;
            unset($region->expressLine, $expressLine->regions);

            $expressLine->regions = ExpressLinePriceRegionList::collection($regions);

            $region = $region->load([
                'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
            ]);

            //过滤超重的运费模板
            if ($this->filterOverweight($region, $countWeight)) continue;

            $expressLine->region = ExpressLineRegionInfo::make($region);

            if ($countWeight) {
                [$expressLine->count_first, $expressLine->count_next] = $expressLine
                    ->getExpressFeeNew($region, $countWeight,
                        true, true,
                        useVolume: /*$expressLine->base_mode === ExpressLine::BASE_MODE_VOLUME*/ false
                    );

            } else {
                [$expressLine->count_first, $expressLine->count_next] = [0, 0];
            }

            unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);

            if ($countWeight === 0
                || $this->isWeightMatched($expressLine, $countWeight)
            ) {
                if ($expressLine->region->name != '全国区域') {
                    $expressLine->name = $expressLine->name . '- ' . $expressLine->region->name;
                }
                $count_next = bcdiv($expressLine->count_next, $expressLine['count_weight'] / 1000, 4);
                $channel = [
                    'id' => $expressLine->id,
                    'name' => $expressLine->name,
                    'code' => $expressLine->channel_code,
                    'quote_id' => $params['quote_id'],
                    'express_company_id' => $expressLine->express_company_id,
                    'express_company_name' => $expressLine->express_company_name,
                    'myLogisticsId' => $expressLine->myLogisticsId,
                    'myLogisticsChannelId' => $expressLine->myLogisticsChannelId,
                    'unit_price' => round(bcdiv($count_next, 100, 3), 2),
                    'service_fee' => round(bcdiv($expressLine->count_first, 100, 3), 2),
                ];
                $key = 'country_prop_channel_'.$params['code'].'_'.$params['prop_id'].'_'.$channel['id'];
                $jsonData = json_encode($channel);
                Cache::set($key, $jsonData, 3600);
                $channelList[] = $channel;
            }
        }
        return $channelList;
    }

    /**
     * 物流渠道批量报价
     * @param $params
     * @return array
     * @throws ValidationException
     */
    public function getQuotedAmount($params)
    {
        validator($params, [
            'code' => 'nullable|string',
            'prop_id' => 'required|int',
            'quote_id' => 'required|int',
            'express_line_id' => 'required|int',
            'weight' => 'required|array',
        ])->validate();

        $key = 'country_prop_channel_'.$params['code'].'_'.$params['prop_id'].'_'.$params['express_line_id'];
        $channel = json_decode(Cache::get($key), true);

        $er = 0;
        if (in_array($params['quote_id'], [6, 7])) {
            $er = 1;
        }
        $list = [];
        foreach ($params['weight'] as $k => $weight) {
            if ($er) {
                $orderWeight = $weight;
                if ($weight > 3000) {
                    $orderWeight -= 3000;
                }
                $price = round(bcmul($orderWeight / 1000, $channel['unit_price'], 4), 2);
            } else {
                $price = round(bcmul($weight / 1000, $channel['unit_price'], 4), 2);
            }
            $logisticsFee = bcadd($price, $channel['service_fee'], 2);
            $list[] = [
                'order_id' => $k,
                'weight' => $weight,
                'logistics_cost' => $logisticsFee,
                'logistics_fee' => $logisticsFee,
                'quote_id' => $channel['quote_id'],
                'express_line_id' => $channel['id'],
                'channel_name' => $channel['name'],
                'channel_code' => $channel['code'],
                'express_company_id' => $channel['express_company_id'],
                'express_company_name' => $channel['express_company_name'],
                'myLogisticsId' => $channel['myLogisticsId'],
                'myLogisticsChannelId' => $channel['myLogisticsChannelId'],
            ];
        }
        return $list;
    }

    public function batchSaveQuotes($params)
    {
        if (!is_array($params)) {
            return '请提交数组格式数据';
        }

        $staff_id = getAdminId();
        $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
            ->value('custom_exchange_rate');
        return DB::transaction(function () use ($params, $staff_id, $exchange_rates) {
            $orderIds = array_column($params, 'order_id');
            $companyIds = array_column($params, 'express_company_id');

            $orders = $this->model::with('lineItems.mapping.goodsSku.goods')->whereIn('order_id', $orderIds)->get()->keyBy('order_id');
            $companyCodes = CompanyExpressModel::query()->whereIn('id', $companyIds)->pluck('code', 'id');
            foreach ($params as $param) {
                $order = $orders[$param['order_id']] ?? null;
                if (!$order) throw new \Exception("Order not found: {$param['order_id']}");

                $goods_once_price = Custom::query()->where('id', $order->customer_id)->value('goods_once_price');
                $customsQuoteConfig = CustomsQuoteConfig::query()
                    ->where('customer_id', $order->customer_id)
                    ->select('product_quote_default_profit_rate', 'freight_quote_default_profit_rate')
                    ->first();
                if (!$customsQuoteConfig) throw new \Exception("Missing quote config for customer {$order->customer_id}");

                $currentExchangeRate = $order->exchange_rates > 0 ? $order->exchange_rates : $exchange_rates;
                $currentFreightProfit = $order->freight_profit > 0 ? $order->freight_profit : $customsQuoteConfig->freight_quote_default_profit_rate;
                $currentProductProfit = $order->product_profit > 0 ? $order->product_profit : $customsQuoteConfig->product_quote_default_profit_rate;

                $freightProfit = bcsub(100, $currentFreightProfit, 2);
                $productProfit = bcsub(100, $currentProductProfit, 2);
                $totalCost = round(bcdiv($param['logistics_fee'], $freightProfit / 100, 4), 2);

                $logisticsFeeCny = bcdiv($param['logistics_fee'], $freightProfit / 100, 4);
                $logistics_compute_fee = round(bcdiv($logisticsFeeCny, $currentExchangeRate, 4), 2);

                $goodsPrice = 0;
                foreach ($order->toArray()['line_items'] as $item) {
                    if ($goods_once_price) {
                        $quote_price = round(bcmul($item['mapping']['goods_sku']['quote_price'], $item['quantity'], 4), 2);
                    } else {
                        $unit_price = bcdiv($item['mapping']['goods_sku']['purchase_price'], $productProfit / 100, 4);
                        $quote_price_cny = bcmul($unit_price, $item['quantity'], 4);
                        $quote_price = round(bcdiv($quote_price_cny, $currentExchangeRate, 4), 2);
                    }
                    $goodsPrice += $quote_price;
                    // 更新商品单价
                    OrderLineItem::query()->where('id', $item['id'])->update(['quote_price' => $quote_price]);
                }

                $order->fill([
                    'order_status' => 1,
                    'staff_id' => $staff_id,
                    'quote_id' => $param['quote_id'],
                    'express_line_id' => $param['express_line_id'],
                    'channel_name' => $param['channel_name'],
                    'logistics_provider' => $param['express_company_id'],
                    'logistics_provider_code' => $companyCodes[$param['express_company_id']] ?? null,
                    'myLogisticsId' => $param['myLogisticsId'],
                    'myLogisticsChannelId' => $param['myLogisticsChannelId'],
                    'exchange_rates' => $currentExchangeRate,
                    'vendor_price' => $goodsPrice,
                    'product_profit' => $customsQuoteConfig->product_quote_default_profit_rate,
                    'freight_profit' => $customsQuoteConfig->freight_quote_default_profit_rate,
                    'logistics_cost' => $param['logistics_fee'],
                    'logistics_fee' => $logistics_compute_fee,
                    'logistics_compute_fee' => $logistics_compute_fee,
                    'logistics_profit' => bcsub($totalCost, $param['logistics_fee'], 2),
                ])->save();
            }
            return true;
        });
    }

}
