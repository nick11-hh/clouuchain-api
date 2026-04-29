<?php

namespace App\Services\Client;

use App\Http\Resources\Admin\PaymentSettingInfo;
use App\Helper\CurrencyConverter;
use App\Imports\OrderImport;
use App\Imports\OrderImportMain;
use App\Jobs\AutoPullOrderJob;
use App\Jobs\SendEmailJob;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\BalanceRecord;
use App\Models\Country;
use App\Models\CreditCardTypes;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\DeclareOrder;
use App\Models\DefaultRechargeAmount;
use App\Models\ExchangeRateModel;
use App\Models\ExpressLineRegion;
use App\Models\ExpressOrderModel;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\OrderItemMapping;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\PaymentSetting;
use App\Models\PaypalPayment;
use App\Models\PurchaseOrdersModel;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use App\Models\Stock;
use App\Models\StockLockLog;
use App\Models\SystemConfig;
use App\Services\Admin\PackageService;
use App\Services\Admin\PurchaseOrderService;
use App\Services\Base\OrderBaseService;
use App\Services\Base\StockService;
use App\Services\Base\SystemConfigService;
use App\Services\PlatformShop\DataService\OrderDataService;
use App\Services\PlatformShop\PlatformShopService;
use App\Services\Base\CommissionService;
use App\Services\Shopify\OrderService as ShopifyOrder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\Base\BalanceService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Admin\OrderService as AdminOrderService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Exceptions\AccidentException;

class OrderService extends BaseService
{
    public $filterRules = [
        'id' => ['in', 'id'],
        'lineItems:title' => ['like', 'product_name'],
        'shippingAddress:country' => ['=', 'country'],
        'shop_id' => ['=', 'shop_id'],
        'platform' => ['=', 'platform'],
        'created_at' => ['between', ['begin_date', 'end_date']],
        //'order_status' => ['in', 'status'],
        'order_type' => ['=', 'order_type'],
        'lineItems:sku' => ['=', 'sku'],
        'warehouse_id' => ['=', 'warehouse_id'],
        'logisticsApply:way_bill_number' => ['like', 'way_bill_number'],
        'expressOrders.tracking:status' => ['=', 'tracking_status'],
    ];

    public $orderBy = [
        'created_at' => 'desc'
    ];

    public function __construct(OrderModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index()
    {
        $this->setQueryCondition();

        $res = parent::index();

        $currencyConverter = new CurrencyConverter();
        $res->each(function ($item) use ($currencyConverter) {
            //包含软删除的数据
            $item->lineItems = $item->allLineItems;

            /* //通过购物车购买的订单已经是美元，所以这不需要再进行换算
             if ($item->platform === Platform::LOCAL) {
                 return;
             }*/

//            $item->vendor_price = $currencyConverter->reversedCurrenciesExchange($item->vendor_price);
//            $item->logistics_fee = $currencyConverter->reversedCurrenciesExchange($item->logistics_fee);
//            $item->other_supplement_price = $currencyConverter->reversedCurrenciesExchange($item->other_supplement_price);
//            $item->vendor_change_price = $currencyConverter->reversedCurrenciesExchange($item->vendor_change_price);
//            $item->favourable_price = $currencyConverter->reversedCurrenciesExchange($item->favourable_price);
//            $item->sku_logistics_fee = $currencyConverter->reversedCurrenciesExchange($item->sku_logistics_fee);
//            $item->supplement_price = $currencyConverter->reversedCurrenciesExchange($item->supplement_price);
//            $item->refund_price = $currencyConverter->reversedCurrenciesExchange($item->refund_price);

            //物流费用
            $logisticsFee = $item->logistics_fee;

            //开启商品一口价 物流费用为sku的物流总报价
            if ($item->order_one_price === 1) {
                $logisticsFee = $item->sku_logistics_fee;
            }

            //原价=商品总报价+物流总报价+其他补价
            $item->original_amount = sprintf("%.2f", ($item->vendor_price + $logisticsFee + $item->other_supplement_price));

            //总报价 = 原价-优惠金额
            $item->total_amount = sprintf("%.2f", ($item->original_amount - $item->favourable_price));

//            $item->lineItems->each(function ($sku) use ($currencyConverter) {
//                $sku->quote_price = $currencyConverter->reversedCurrenciesExchange($sku->quote_price);
//                $sku->logistics_fee = $currencyConverter->reversedCurrenciesExchange($sku->logistics_fee);
//            });
        });
        return $res;
    }

    /**
     * 状态统计
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/27 17:00
     */
    public function statusCount(): array
    {
        $this->setFilter();
        unset($this->formData['status']);

        $this->setQueryCondition();

        $statuses = $this->query->selectRaw('order_status as status, count(*) as count')->groupBy('order_status')->get();

        $allStatuses = array_keys($this->model::statusList()); //获取所有状态
        $statusCounts = collect($allStatuses)
            ->mapWithKeys(function ($status) use ($statuses) {
                // 如果状态存在于统计结果中，返回它的计数，否则返回0
                return [$status => $statuses->firstWhere('status', $status)?->count ?? 0];
            });

        //处理中包含的状态
        $processCount = 0;
        $processStatus = [3, 4];

        //已完成包含的状态
        $completedCount = 0;
        $completedStatus = [5];

        //异常订单
        $abnormalCount = 0;
        $abnormalStatus = 7;


        $data = [];
        foreach ($statusCounts as $status => $count) {

            if (in_array($status, $processStatus)) {
                $processCount += $count;

                continue;
            }

            if (in_array($status, $completedStatus)) {
                $completedCount += $count;

                continue;
            }

            if ($status == $abnormalStatus) {
                $abnormalCount += $count;

                continue;
            }
            if ($status == 3 || $status == 6) continue;
            $data[] = ['status' => $status, 'count' => $count];
        }

        $data[] = ['status' => 3, 'count' => $processCount];
        $data[] = ['status' => 5, 'count' => $completedCount];
        $data[] = ['status' => $abnormalStatus, 'count' => $abnormalCount];

        //计算未报价状态平台订单状态为开启、平台支付状态为已付款的数量
        $quoteNoCount = (clone $this->query)->where('order_status', Order::STATUS_QUOTE_NO)->where('abnormal_status', Order::ORDER_STATUS_NORMAL)
            ->where(function ($query) {
                $query->where(['platform_payment_status' => 'paid'])->orWhere('platform', '!=', Platform::SHOPIFY);
            })
            ->count();

        $data[0] = ['status' => Order::STATUS_QUOTE_NO, 'count' => $quoteNoCount];
        $data[] = ['status' => '', 'count' => $statusCounts->sum()];

        return $data;
    }

    /**
     * 获取订单id合集
     * @return \Illuminate\Support\Collection
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/27 17:00
     */
    public function getIds()
    {
        $this->setQueryCondition();

        $this->setFilter();

        return $this->query->pluck('id');
    }

    public function store()
    {
        validator($this->formData, $this->rules())->validate();

        throw_if(
            $this->model::where('order_id', $this->formData['order_no'])->first(),
            new AccidentException('操作失败，订单号已存在', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            // $user_id = auth()->id();
            $custom_id = request()->get('custom_id') ?? getCustomId();

            $order = Order::query()->where('order_id', $this->formData['order_no'])->where('customer_id', $custom_id)->first();
            if (!empty($order)) throw new AccidentException('The Order no exist');

            $shop = ShopModel::query()->findOrFail($this->formData['shop_id']);

            $skuStatus = count($this->formData['products']) > 1 ? 2 : ($this->formData['products'][0]['quantity'] === 1 ? 0 : 1);

            $orderData = [
                'customer_id' => $custom_id,
                'order_id' => $this->formData['order_no'] . '_' . $shop->id,
                'platform' => $shop->platform,
                'currency' => 'USD',
                'order_status' => Order::STATUS_QUOTE_NO,
                'shop_id' => $shop->id,
                'custom_order_id' => generateOrderId(),
                'payment_info' => [],
                'sku_status' => $skuStatus,
                'name' => $this->formData['name'] ?? '',
                'remark' => $this->formData['remark'] ?? '',
                'current_total_price' => $this->formData['current_total_price'] ?? 0,
            ];
            $order = Order::query()->create($orderData);

            $country = Country::query()->findOrFail($this->formData['country_id']);

            $shippingData = [
                'order_id' => $order->id,
                'first_name' => $this->formData['first_name'],
                'last_name' => $this->formData['last_name'],
                'name' => $this->formData['first_name'] . ' ' . $this->formData['last_name'],
                'country' => $this->formData['country'],
                'country_code' => strtoupper($country->code),
                'province' => $this->formData['province'],
                'city' => $this->formData['city'],
                'address1' => $this->formData['address1'],
                'address2' => $this->formData['address2'],
                'phone' => $this->formData['phone'],
                'zip' => $this->formData['zip'],
                'tax' => $this->formData['tax'] ?? '',
                'email' => $this->formData['email'] ?? '',
            ];
            OrderShippingAddress::create($shippingData);

            $lineItems = [];
            foreach ($this->formData['products'] as $item) {
                $lineItems[] = [
                    'order_id' => $order->id,
                    'name' => $item['product_name'] ?? '',
                    'title' => $item['product_name'] ?? '',
                    'variant_title' => $item['variants'] ?? '',
                    'variant_id' => !empty($item['sku']) ? $item['sku'] . '_' . $shop->id : '',
                    'sku' => $item['sku'] ?? '',
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'total_discount' => $item['total_discount'] ?? 0,
                    'product_url' => $item['product_url'] ?? '',
                    'imgs' => empty($item['image_url']) ? '' : json_encode([$item['image_url']])
                ];
            }

            OrderLineItem::query()->insert($lineItems);

            ShopOrderLogs::addLog([
                'order_id' => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PULL_ORDER,
                'content' => '客户手动添加订单',
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('添加订单失败：' . $e->getMessage());
            logger($e->getFile() . ': ' . $e->getLine());
            throw new AccidentException('Add order failed: ' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 同步订单
     * @return bool
     * @throws Exception
     */
    public function pullPlatformOrders()
    {
        throw_if(
            ExchangeRateModel::query()->where('currency_code', 'CNY')->doesntExist(),
            new AccidentException('The system does not set the CNY exchange rate to allow synchronization of orders, please contact the administrator!', Code::OPERATE_FAIL)
        );

        $shops = ShopModel::query()->where(['customer_id' => getCustomId(), 'status' => ShopModel::STATUS_AUTH, 'enable' => ShopModel::ENABLE])->get();

        foreach ($shops as $shop) {
            dispatch(new AutoPullOrderJob($shop));
        }

        /*foreach ($shops as $shop) {
            $platformShopService = new PlatformShopService($shop);
            $platformShopService->syncOrderList();
        }*/
        return true;
    }

    /**
     * 询问报价
     * @return void
     */
    public function askQuote()
    {
        validator($this->formData, [
            'ids' => 'required|array',
        ], [], [
            'ids' => '订单id',
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '>', OrderModel::STATUS_ALLOCATED)->count(),
            new AccidentException('操作失败，询价的订单中不能有已支付订单', Code::OPERATE_FAIL)
        );

        return $this->model::whereIn('id', $this->formData['ids'])->update(['order_status' => OrderModel::STATUS_QUOTE_ASK]);
    }

    /**
     * 订单支付
     */
    public function payment()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::query()->whereIn('id', $this->formData['ids'])->where('order_status', '<>', OrderModel::STATUS_ALLOCATED)->first(),
            new AccidentException('操作失败，只能支付待支付状态的订单', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            $balance = new BalanceService();
            $commission = new CommissionService();
            $currencyConverter = new CurrencyConverter();
            $custom = Custom::query()->findOrFail(getCustomId());

            foreach ($this->formData['ids'] as $id) {
                $order = $this->model::query()->findOrFail($id);

                //代发订单校验物流渠道
                if ($order->order_type === Order::ORDER_TYPE_PLACE && empty($order->logistics_provider)) {
                    throw new AccidentException('操作失败，未设置物流信息', Code::OPERATE_FAIL);
                }

                //物流费用
                $logisticsFee = $order->logistics_fee;

                //开启商品一口价 物流费用为sku的物流总报价
                if ($order->order_one_price === 1) {
                    $logisticsFee = $order->sku_logistics_fee;
                }

                //支付金额=商品报价+物流报价+其他补价-优惠价格
                $amount = $order->vendor_price + $logisticsFee + $order->other_supplement_price - $order->favourable_price;

                //供应商改价
                if ($order->vendor_change_price > 0) {
                    $amount = $order->vendor_change_price;
                }

//                $amount = $currencyConverter->reversedCurrenciesExchange($amount);

                // 操作支付，扣除用户余额
                $balance->setRelationId($order->id)->deduction($amount, BalanceRecord::SOURCE_ORDER_PAY, $order->name ? $order->name : $order->order_id);

                // 标记为支付状态,记录支付金额
                $order->payment_price = $amount;
                $order->currency = DeclareOrder::CURRENCY_USD;
                $order->order_status = OrderModel::STATUS_PENDING;
                $order->financial_status = Order::FINANCIAL_STATUS_PAID; //财务状态 已支付
                $order->paymented_at = now();
//                $order->refund_price = 0;
                $order->save();

                //新增一条支付日志
                $logData = [
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PAYMENTED,
                    'content' => '客户设置订单批量支付，金额' . $amount,
                ];
                ShopOrderLogs::addLog($logData);

                //订单佣金生成
                $commission->increase($amount, $custom->commission_rate, $order->order_id);

                (new PackageService())->createByOrder($order);

                //使用客户库存 锁定客户库存
                // if ($order->use_customer_stock) {
                //     $this->lockCustomerStocks($order);
                // }
            }

            //余额低于预警值时发送邮件通知
            $balanceData = CustomBalance::query()->select('balance')->where('custom_id', getCustomId())->first();
            $configData = SystemConfig::query()->where('config_key', 'custom_balance')->first();
            if (!empty($balanceData) && !empty($configData) && $balanceData['balance'] <= $configData['config_value'] * 100) {
                dispatch(new SendEmailJob('BalanceNoticeEmail', $custom['custom_email'], ['customer_id' => getCustomId()]));
            }

            // $orders = $this->model::with('lineItems')->whereIn('id', $this->formData['ids'])->get();
            // // 创建订单发货项
            // $adminOrderService = new \App\Services\Admin\OrderService(new Order());
            // $orders->each(function ($order) use ($adminOrderService) {
            //     $adminOrderService->createDeliverData($order);
            // });

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('订单支付失败：' . $e->getMessage());
            logger('========' . $e->getCode());
            if ($e->getCode() === Code::OPERATE_FAIL) {
                throw $e;
            }
            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 锁定客户库存
     */
    public function lockCustomerStocks($order)
    {
        if (empty($order)) {
            return true;
        }

        $adminOrderService = new \App\Services\Admin\OrderService(new Order());

        $order->lineItems->each(function ($item) use ($order, $adminOrderService) {
            $mapping = OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item['variant_id'])->first();
            if (empty($mapping)) throw new AccidentException('订单未映射本地商品', Code::OPERATE_FAIL);

            //查询客户库存
            $stock = Stock::query()->where([
                'sku_id' => $mapping->goods_sku_id,
                'custom_id' => $order->customer_id,
            ])->first();

            if (empty($stock)) {
                throw new AccidentException('库存不足', Code::OPERATE_FAIL);
            }

            if ($stock->quantity < $item->quantity) {
                throw new AccidentException('库存不足' . ':' . $stock->spec_name, Code::OPERATE_FAIL);
            }

            //锁定库存
            $adminOrderService->orderItemUseStock($order, $item, $stock);
        });

        return true;
    }

    /**
     * 订单退款
     */
    public function orderRefund($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '<', OrderModel::STATUS_PENDING)->first(),
            new AccidentException('操作失败，只有处理中状态的订单才支持退款', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            $balance = new BalanceService();
            $currencyConverter = new CurrencyConverter();

            foreach ($params['ids'] as $id) {
                $order = $this->model::query()->findOrFail($id);

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

//                $amount = $currencyConverter->reversedCurrenciesExchange($amount);


                // 操作退款 增加客户余额
                $balance->setRelationId($order->id)->increase($amount, BalanceRecord::SOURCE_ORDER_REFUND, $order->name ? $order->name : $order->order_id);

                // 订单取消状态跟已退款
                $order->order_status_before = $order->order_status; //退款前的订单状态
                $order->order_status = Order::STATUS_CANCELLED;
                $order->financial_status = Order::FINANCIAL_STATUS_FULL_REFUND; //财务状态 全额退款
                $order->save();

                //记录日志
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_REFUND,
                    'content' => "客户操作订单退款，退款金额：$amount USD",
                ]);

                //释放库存
                (new StockService())->setOperateSn($order->order_id)->unlockStockByOperateSn(StockLockLog::SOURCE_ORDER_REFUND);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw new AccidentException('操作失败：' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 取消订单
     * @return void
     */
    public function cancelOrder()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ], [], [
            'ids' => '订单id'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('order_status', '>', OrderModel::STATUS_ALLOCATED)->first(),
            new AccidentException('操作失败，只能取消未支付状态的订单', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () {
            $orders = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
            $orders->each(function ($order) {
                $order->update(['order_status' => OrderModel::STATUS_CANCELLED, 'order_status_before' => $order->order_status]);

                //记录日志
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CANCEL,
                    'content' => '客户操作取消订单，取消前的订单状态为:' . $this->model::getStatusName($order->order_status),
                ]);
            });

            return true;
        });
    }


    /** shopify 订单履约请求
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function fulfillmentRequest($id)
    {
        //开启shopify审核后，履约请求跳转到新的方法，支付跟履约一起实现
        $shopifyAppReviewMode = SystemConfigService::getConfigValue(SystemConfig::SHOPIFY_APP_REVIEW_MODE);
        if ($shopifyAppReviewMode) {
            return $this->paymentAndFulfill($id);
        }

        return (new ShopifyOrder())->requestFulfillment($id);
    }

    /**
     * 订单导入
     * @return array
     * @throws Exception
     */
    public function import()
    {
        $file = request()->file('file');

        if (empty($file)) {
            throw new AccidentException('导入文件不能为空', Code::OPERATE_FAIL);
        }

        $ext = $file->getClientOriginalExtension();

        if (!in_array($ext, ['xlsx', 'xls'])) {
            throw new AccidentException('只能导入excel文件', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($file) {
            $import = new OrderImportMain();
            Excel::import($import, $file);

        });

    }

    /**
     * 导出订单数据
     * @return array
     * @throws Exception
     */
    public function export()
    {
        $orderData = $detailData = $orderIds = [];

        $this->setFilter();
        $this->setQueryCondition();

        $this->query->orderBy('created_at')->chunk(100, function ($orders) use (&$orderData, &$orderIds) {
            foreach ($orders as $order) {
                $orderIds[] = $order->id ?? 0;

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
                $vendorTotalPrice = (float)($vendorPrice + $logisticsFee + $otherSupplementPrice - $favourablePrice);
                $vendorTotalPrice = number_format($vendorTotalPrice, 2, '.', '');
                $package = $order->packages[0] ?? null;
                $platformLogisticsNumber = $order->platformFulfillments->pluck('tracking_number')->toArray();
                $orderData[] = [
                    'order_sn' => $order->order_id ?? '', //订单号
                    'platform' => $platform, //站点
                    'shop_name' => $order->shop->shop_name ?? '', //卖家
                    'name' => $order->name ?? '', //订单序列号
                    'current_total_price' => $orderPrice . ' ' . $orderCurrency, //订单价格
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
                    'total_price' => (float)($vendorTotalPrice + $supplementPrice) - (float)$refundPrice, //订单总金额(USD)
                    'system_remark' => $order->system_remark ?? '', //系统备注
                    'created_at' => (string)($order->created_at ?? ''), //下单时间
                    'paymented_at' => $order->paymented_at ?? '', //付款时间
                    'commited_at' => $order->commited_at ?? '', //提交时间
                    'cancelled_at' => $order->cancelled_at ?? '', //订单取消时间
                    'first_name' => $order->shippingAddress->first_name ?? '', //收货地址-名
                    'last_name' => $order->shippingAddress->last_name ?? '', //收货地址-姓
                    'shipping_address_country' => $order->shippingAddress->country ?? '', //收货地址-国家
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

        $orderCount = count($orderIds);
        throw_if($orderCount > 5000,
            new AccidentException(__('每次只能导出5000个订单，请分批导出'), Code::OPERATE_FAIL)
        );

        //找到所有订单的产品信息
        $items = OrderLineItem::query()
            ->with([
                'shopOrder:id,order_id,currency,order_one_price,name',
                'mapping.goodsSku.goods',
                'shopOrder.shippingAddress',
                'shopOrder.packages.logisticsApply'
            ])
            ->whereIn('order_id', $orderIds)
            ->orderBy('order_id', 'DESC')
            ->get();

        $detailData = $items->map(function ($item) {
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

        $orderHeadings = [
            __('订单号'),
            __('站点'),
            __('卖家'),
            __('平台编号'),
            __('订单价格'),
            __('状态'),
            __('国家'),
            __('物流渠道'),
            __('物流单号'),
            __('产品总报价(USD)'),
            __('物流报价(USD)'),
            __('优惠金额(USD)'),
            __('其他金额(USD)'),
            __('报价总价(USD)'),
            __('补收金额(USD)'),
            __('退款金额(USD)'),
            __('订单总金额(USD)'),
            __('系统备注'),
            __('下单时间'),
            __('付款时间'),
            __('提交时间'),
            __('订单取消时间'),
            __('名'),
            __('姓'),
            __('国家'),
            __('手机号'),
            __('邮编'),
            __('省份'),
            __('城市'),
            __('地址1'),
            __('地址2'),
            __('税号'),
            __('平台物流单号')
        ];

        $detailHeadings = [
            __('订单号'),
            __('平台编号'),
            __('国家'),
            __('物流单号'),
            __('品名'),
            __('规格'),
            __('SKU'),
            __('数量'),
            __('产品原始价格'),
            __('产品报价(USD)'),
            __('产品一口价(USD)'),
            __('本地品名'),
            __('长(cm)'),
            __('宽(cm)'),
            __('高(cm)'),
            __('重量(g)'),
            __('产品规格')
        ];

        $getTmpDir = function () {
            $tmp = ini_get('export_tmp_dir');

            if ($tmp !== False && file_exists($tmp)) {
                return realpath($tmp);
            }

            return realpath(sys_get_temp_dir());
        };

        $config = [
            'path' => $getTmpDir() . '/',
        ];

        $fileName = 'OrderExport.xlsx';
        $xlsxObject = new \Vtiful\Kernel\Excel($config);

        // Init File
        $fileObject = $xlsxObject->fileName($fileName, __('订单信息'));

        // Outptu
        $fileObject->header($orderHeadings)
            ->data($orderData);

        //追加一个工作表
        $filePath = $fileObject->addSheet(__('产品明细'))
            ->header($detailHeadings)
            ->data($detailData)
            ->output();

        // Set Header
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        if (copy($filePath, 'php://output') === false) {
            // Throw exception
        }

        // Delete temporary file
        @unlink($filePath);
        exit();
    }

    public function orderPayDetail($id)
    {
        throw_if(
            $this->model::where('id', $id)->where('order_status', '<>', OrderModel::STATUS_ALLOCATED)->first(),
            new AccidentException('操作失败，只能支付待支付状态的订单', Code::OPERATE_FAIL)
        );

        $order = $this->model::query()->with(['lineItems', 'shippingAddress', 'expressLine.regions'])->findOrFail($id);

//        $currencyConverter = new CurrencyConverter();
//        $order->vendor_price = $currencyConverter->reversedCurrenciesExchange($order->vendor_price);
//        $order->logistics_fee = $currencyConverter->reversedCurrenciesExchange($order->logistics_fee);
//        $order->lineItems->each(function ($sku) use ($currencyConverter) {
//            $sku->price = $currencyConverter->reversedCurrenciesExchange($sku->price);
//            $sku->quote_price = $currencyConverter->reversedCurrenciesExchange($sku->quote_price);
//        });

        return $order;
    }

    public function updateAddress()
    {
        //订单类型 1-代发订单 2-备货订单
        $orderType = $this->formData['order_type'] ?? 1;

        if ((int)$orderType === Order::ORDER_TYPE_PLACE) {
            $validator = [
                'address.first_name' => 'required',
                'address.last_name' => 'required',
                'address.country' => 'required',
                'address.city' => 'required',
                'address.address1' => 'required',
                'address.phone' => 'required',
                'address.zip' => 'required',
            ];
        } else {
            $validator = [
                'warehouse_id' => 'required',
            ];
        }
        validator($this->formData, $validator)->validate();

        throw_if(
            $this->model::where('id', $this->formData['order_id'])->count() === 0,
            new AccidentException('Operation failed, order does not exist', Code::OPERATE_FAIL)
        );

        DB::beginTransaction();
        try {
            if ((int)$orderType === Order::ORDER_TYPE_PLACE) {
                $address = $this->formData['address'];
                $addressData = [
                    'first_name' => $address['first_name'],
                    'last_name' => $address['last_name'],
                    'name' => $address['first_name'] . ' ' . $address['last_name'],
                    'country' => $address['country'],
                    'province' => $address['province'],
                    'city' => $address['city'],
                    'address1' => $address['address1'],
                    'phone' => $address['phone'],
                    'zip' => $address['zip'],
                    'tax' => $address['tax'] ?? '',
                    'email' => $address['email'] ?? '',
                ];

                OrderShippingAddress::where('order_id', $this->formData['order_id'])->update($addressData);
            } else {
                //修改仓库地址
                $this->model::query()->where('id', $this->formData['order_id'])->update(['warehouse_id' => $this->formData['warehouse_id']]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('修改失败：' . $e->getMessage());
            logger($e->getFile() . ': ' . $e->getLine());
            throw new AccidentException('Operation failure', Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 获取支付方式
     * @return array
     */
    public function payMethod()
    {
        //paypal支付方式
        $paypal = PaypalPayment::query()->first();
        $paypal = [
            'enabled' => $paypal->enabled ?? 0,
            'paypal_minimum_payment' => (float)($paypal->minimum_payment ?? 1),//paypal 最低充值金额
            'service_charge_rate' => (float)($paypal->service_charge_rate ?? 0),//paypal 手续费比例
            'service_charge_amount' => (float)($paypal->service_charge_amount ?? 0),//paypal 手续费金额
        ];

        //其他支付方式
        $payments = PaymentSetting::query()->where('enabled', 1)->get();

        $CreditCardTypes = CreditCardTypes::select(['name','status','minimum_payment','service_charge_rate','service_charge_amount'])->where('status', CreditCardTypes::STATUS_NORMAL)->get();

        return [
            'paypal' => $paypal,
            'other' => PaymentSettingInfo::collection($payments),
            'creditCards' => $CreditCardTypes,
        ];
    }

    public function defaultAmount()
    {
        return DefaultRechargeAmount::query()->orderBy('amount')->get();
    }

    public function deleteItems()
    {
        $ids = $this->formData['ids'];
        $orderId = $this->formData['order_id'] ?? 0;

        DB::beginTransaction();
        try {
            if (empty($ids)) {
                throw new AccidentException('Please select the item you want to delete', Code::OPERATE_FAIL);
            }

            //将关联订单的报价金额修改成0
            $items = OrderLineItem::query()->select(['order_id', 'sku'])->whereIn('id', $ids)->get();


            $orderId = intval($orderId);
            if ($orderId) {
                //这里是待付款状态的订单删除产品后需要将订单状态更新为：报价中

                $this->model::query()->where('id', $orderId)
                    ->where('customer_id', getCustomId())
                    ->where('order_status', OrderModel::STATUS_QUOTED)
                    ->update([
                        'vendor_price' => 0,
                        'order_status_before' => OrderModel::STATUS_QUOTED,
                        'order_status' => OrderModel::STATUS_QUOTE_ASK
                    ]);

                ShopOrderLogs::addLog([
                    'order_id' => $orderId,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ROLLBACK_QUOTE_ASK,
                    'content' => "客户删除待付款订单产品后订单打回报价中"
                ]);

            } else {

                $orderIds = $items->pluck('order_id');
                if ($orderIds) $this->model::query()->whereIn('id', $orderIds)->update(['vendor_price' => 0]);

            }

            OrderLineItem::query()->whereIn('id', $ids)->delete();

            $items->each(function ($item) {
                ShopOrderLogs::addLog([
                    'order_id' => $item->order_id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_DELETE_ITEM,
                    'content' => "客户删除订单SKU: {$item->sku}"
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
        $ids = $this->formData['ids'];

        DB::beginTransaction();
        try {
            if (empty($ids)) {
                throw new AccidentException('Please select the item you want to recover', Code::OPERATE_FAIL);
            }

            //将关联订单的报价金额修改成0
            $items = OrderLineItem::onlyTrashed()->select(['order_id', 'sku'])->whereIn('id', $ids)->get();
            $orderIds = $items->pluck('order_id');
            if ($orderIds) $this->model::query()->whereIn('id', $orderIds)->update(['vendor_price' => 0]);

            OrderLineItem::query()->whereIn('id', $ids)->restore();

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

    /**
     * shopify审核模式 支付跟履约一起执行
     */
    public function paymentAndFulfill($id)
    {
        $order = $this->model::query()->findOrFail($id);

        //未报价时直接请求履单接口
        if ($order->order_status < Order::STATUS_QUOTED) {
            return (new ShopifyOrder())->requestFulfillment($id);
        }

        DB::beginTransaction();
        try {
            //未设置物流渠道
            if (empty($order->logistics_provider)) {
                throw new AccidentException('Operation failed, logistics information is not set', Code::OPERATE_FAIL);
            }

            $balance = new BalanceService();
            $commission = new CommissionService();
            $currencyConverter = new CurrencyConverter();

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

//            $amount = $currencyConverter->reversedCurrenciesExchange($amount);


            // 操作支付，扣除用户余额
            $balance->setRelationId($order->id)->deduction($amount, BalanceRecord::SOURCE_ORDER_PAY, $order->name ? $order->name : $order->order_id);
            // 标记为支付状态
            $this->model::where('id', $id)->update(['currency' => DeclareOrder::CURRENCY_USD, 'order_status' => OrderModel::STATUS_PENDING, 'paymented_at' => now()]);

            $customer = Custom::query()->with(['balance'])->findOrFail(getCustomId());
            //订单佣金生成
            $commission->increase($amount, $customer->commission_rate, $order->order_id);

            //客户余额通知
            $balanceNoticePrice = SystemConfigService::getConfigValue(SystemConfig::CUSTOM_BALANCE);
            if ($customer->balance->balance <= $balanceNoticePrice * 100) {
                dispatch(new SendEmailJob('BalanceNoticeEmail', $customer['custom_email'], ['customer_id' => getCustomId()]));
            }

            //执行shopify履约请求
            (new ShopifyOrder())->requestFulfillment($id);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('shopify审核模式-请求履约出现异常：', [$e->getMessage(), $e->getLine(), $e->getFile()]);

            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 获取订单详情
     * @param int $id
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/7 15:48
     */
    public function detail(int $id)
    {
        $this->query->with([
            'custom:id,custom_name',
            'shop:id,shop_name',
            'shippingAddress',
            'expressLine:id,cn_name,en_name',
            'logisticsApply:order_id,way_bill_number',
            'expressOrders.tracking',
            'expressOrders.logistics',
            'lineItems.mapping.goodsSku.goods:id,goods_name',
            'packages.logisticsApply'
        ]);
        $order = $this->query->findOrFail($id);

        $processStatusList = [
            OrderModel::STATUS_PENDING, OrderModel::STATUS_APPLY_NUM, OrderModel::STATUS_SHIPPED, OrderModel::STATUS_DELIVERY_FAILURE,
            OrderModel::STATUS_APPLY_NUM_SUCCESS, OrderModel::STATUS_APPLY_NUM_FAILURE, OrderModel::STATUS_WAIT_PRINT, OrderModel::STATUS_WAIT_PRINT_IN_STOCK,
            OrderModel::STATUS_WAIT_PRINT_OUT_STOCK
        ];

        $currencyConverter = new CurrencyConverter();

        $country_code = $order->toArray()['shipping_address']['country_code'];
        $country_id = Country::query()->where('code', $country_code)->value('id');

        $reference_time = '';
        $express_line = $order->toArray()['express_line'];
        if (!empty($express_line)) {
            $express_line_id = $express_line['id'];
            $reference_time = ExpressLineRegion::query()->where(['express_line_id' => $express_line_id, 'country_id' => $country_id])->value('reference_time');
        }
        $order->reference_time = $reference_time;

        //包含软删除的数据
        $order->lineItems = $order->allLineItems;

        $order->quote_currency = 'USD';
        //通过购物车购买的订单已经是美元，所以这不需要再进行换算
//        $order->vendor_price = $currencyConverter->reversedCurrenciesExchange($order->vendor_price);
//        $order->logistics_fee = $currencyConverter->reversedCurrenciesExchange($order->logistics_fee);
//        $order->other_supplement_price = $currencyConverter->reversedCurrenciesExchange($order->other_supplement_price);
//        $order->vendor_change_price = $currencyConverter->reversedCurrenciesExchange($order->vendor_change_price);
//        $order->favourable_price = $currencyConverter->reversedCurrenciesExchange($order->favourable_price);
//        $order->sku_logistics_fee = $currencyConverter->reversedCurrenciesExchange($order->sku_logistics_fee);
//        $order->supplement_price = $currencyConverter->reversedCurrenciesExchange($order->supplement_price);
//        $order->refund_price = $currencyConverter->reversedCurrenciesExchange($order->refund_price);

        //物流费用
        $logisticsFee = $order->logistics_fee;

        //开启商品一口价 物流费用为sku的物流总报价
        if ($order->order_one_price === 1) {
            $logisticsFee = $order->sku_logistics_fee;
        }

        //原价=商品总报价+物流总报价+其他补价
        $order->original_amount = sprintf("%.2f", ($order->vendor_price + $logisticsFee + $order->other_supplement_price));

        //总报价 = 原价-优惠金额
        $order->total_amount = sprintf("%.2f", ($order->original_amount - $order->favourable_price));

//        $order->lineItems->each(function ($sku) use ($currencyConverter) {
//            $sku->quote_price = $currencyConverter->reversedCurrenciesExchange($sku->quote_price);
//            $sku->logistics_fee = $currencyConverter->reversedCurrenciesExchange($sku->logistics_fee);
//        });


        if ($order->expressOrders) {
            $expressStatusName = $order->expressOrders->sortByDesc('id')->first()->tracking->status_name ?? '';
            $order->express_status_name = $expressStatusName;

            // 包裹物流信息
            $order->logistics_apply_list = $order->expressOrders->where('status', ExpressOrderModel::STATUS_SUCCESS)->map(function ($item) {
                return [
                    'package_sn' => $item->package_sn,
                    'way_bill_number' => $item->logistics->way_bill_number ?? '',
                    'fulfillment_express_line' => $item->logistics->fulfillment_express_line ?? '',
                    'tracking_status_name' => $item->tracking->status_name ?? '',
                ];
            })->toArray();
            $order->logistics_apply_list = array_values($order->logistics_apply_list);
        }

        //查询报价信息
        $data = (new AdminOrderService($this->model))->orderQuotationProcess([$order->toArray()])[0] ?? [];
        if (!empty($data)) {
            $data['status_name'] = in_array($data['order_status'], $processStatusList) ? __('处理中') : $data['status_name'];

            unset($data['payment_info'], $data['status']);
        }

        return $data;
    }


    /**
     * @param $id
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Validation\ValidationException
     */
    public function buyAgain($id, $params)
    {
        validator($params, [
            'shop_id' => 'required|int',
        ]);
        $order = Order::query()->findOrFail($id);
        $orderBaseService = new OrderBaseService(new Order());
        $shippingAddress = [
            'first_name' => $order->shippingAddress->first_name,
            'last_name' => $order->shippingAddress->last_name,
            'country' => $order->shippingAddress->country,
            'country_code' => $order->shippingAddress->country_code,
            'province' => $order->shippingAddress->province,
            'city' => $order->shippingAddress->city,
            'address1' => $order->shippingAddress->address1,
            'address2' => $order->shippingAddress->address2,
            'phone' => $order->shippingAddress->phone,
            'zip' => $order->shippingAddress->zip,
            'tax' => $order->shippingAddress->tax,
            'email' => $order->shippingAddress->email,
        ];
        $lineItems = [];
        foreach ($order->lineItems as $item) {
            $lineItems[] = [
                'name' => $item->name,
                'title' => $item->title,
                'variant_title' => $item->variant_title,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'sku' => $item->sku,
                'product_url' => $item->product_url,
                'image_url' => $item->imgs[0] ?? '',
            ];
        }
        $data = [
            'order_id' => $order->order_id,
            'shop_id' => $params['shop_id'],
            'currency' => $order->currency,
            'payment_info' => is_array($order->payment_info) ? $order->payment_info : [],
            'name' => $order->name,
            'current_total_price' => $order->current_total_price,
            'subtotal_price' => $order->subtotal_price,
            'shipping_address' => $shippingAddress,
            'line_items' => $lineItems,
        ];
        $order = $orderBaseService->create($data, "订单{$order->order_id}再买一单，复制创建成功");
        $order->load(['lineItems.mapping.goodsSku', 'shippingAddress']);

        // 自动报价
        try {
            $orderDataService = new OrderDataService();
            $orderDataService->autoOrderQuote($order);
        } catch (\Exception $exception) {
        }

        return $order;
    }

    /**
     * 设置过滤条件
     * @return true
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/27 16:55
     */
    public function setQueryCondition(): bool
    {
//        if (empty($this->formData['platform'])) {
//            $this->query->where('platform', '!=', Platform::LOCAL);
//        }

        $custom_id = request()->get('custom_id') ?? getCustomId();

        $with = [
            'allLineItems',
            'shippingAddress',
            'shop',
            'logisticsApply',
            'trackInfo',
            'purchaseOrder:id,order_sn,shop_order_id',
            'channel:id,name',
            'expressLine:id,name,en_name',
            'expressOrders:id,package_sn,status',
            'expressOrders.tracking',
            'chargeType',
            'warehouse',
            'packages.logisticsApply'
        ];

        //订单类型 1 代发订单 2 备货订单
        $orderType = $this->formData['order_type'] ?? 1;

        if ((int)$orderType === Order::ORDER_TYPE_STOCK) {
            $with = [
                'allLineItems',
                'warehouse',
                'chargeType',
            ];
            $this->query->where('customer_id', $custom_id);
        } else {
            //['paid', '']兼容excel导入订单
            $this->query->whereHas('shop', function ($query) use ($custom_id) {
                $query->where('customer_id', $custom_id);
            });
        }

        //关联表查询
        $this->query->with($with);

        if (isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            $queryField = match ($this->formData['keyword_type']) {
                '1' => 'order_id',
                '2' => 'custom_order_id',
                '3' => 'name',
                default => null
            };

            if ($queryField) {
                $keywords = array_filter(array_map('trim', explode(',', $this->formData['keyword'])));

                if (count($keywords) === 1) {
                    // 单个值使用 like 查询
                    $this->query->where($queryField, 'like', '%' . $keywords[0] . '%');
                } else {
                    // 多个值使用 whereIn 查询
                    $this->query->whereIn($queryField, $keywords);
                }
            }
        }

        //搜索时间范围类型
        if (!empty($this->formData['time_range_type'] ?? '') && !empty($this->formData['time_range_start'] ?? '') && !empty($this->formData['time_range_end'] ?? '')) {
            $timeArray = [Carbon::parse($this->formData['time_range_start'])->startOfDay(), Carbon::parse($this->formData['time_range_end'])->endOfDay()];

            switch ($this->formData['time_range_type']) {
                case 1:
                    //创建时间
                    $this->query->whereBetween('created_at', $timeArray);
                    break;
                case 2:
                    //更新时间
                    $this->query->whereBetween('updated_at', $timeArray);
                    break;
                case 3:
                    //付款时间
                    $this->query->whereBetween('paymented_at', $timeArray);
                    break;
                case 4:
                    //交运时间
                    $this->query->whereBetween('shipment_time', $timeArray);
                    break;
                case 5:
                    //发货时间
                    $this->query->whereBetween('deliver_time', $timeArray);
                    break;
            }
        }

        //订单状态
        if (isset($this->formData['status']) && !empty($this->formData['status'])) {
            $this->formData['status'] = Arr::wrap($this->formData['status']);

            $this->query->whereIn('order_status', $this->formData['status']);

            //未报价状态只查询平台订单状态为开启、平台支付状态为已付款
            if (count($this->formData['status']) === 1 && $this->formData['status'][0] == Order::STATUS_QUOTE_NO) {
            }
        }


        return true;
    }

    public function rules()
    {
        return [
            'shop_id' => 'required|int',
            'order_no' => 'required',
            'name' => 'sometimes|nullable',
            'current_total_price' => 'sometimes|nullable',
            'remark' => 'sometimes|nullable',
            'first_name' => 'required',
            'last_name' => 'required',
            'country_id' => 'required|int',
            'city' => 'required',
            'address1' => 'required',
            'phone' => 'sometimes|nullable',
            'zip' => 'required',

            'products' => 'required|array',
            'products.*.sku' => 'required',
            'products.*.product_name' => 'required',
            'products.*.variants' => 'required',
            'products.*.quantity' => 'required|int',
            'products.*.product_url' => 'required|string',
            'products.*.image_url' => 'sometimes|nullable',
        ];
    }
}
