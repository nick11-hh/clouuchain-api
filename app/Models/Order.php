<?php

namespace App\Models;

use App\Models\Traits\CustomerFilter;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use SoftDeletes;
    use CustomerFilter;

    protected $table = 'dsp_shop_order';

    protected $casts = [
        'client_details' => 'array',
        'refunds' => 'array',
        'shipping_lines' => 'array',
        'payment_info' => 'array'
    ];

    public const STATUS_WAIT             = 0;
    public const STATUS_WAIT_DISTRIBUTED = 1;
    public const STATUS_ALLOCATED        = 2;
    public const STATUS_VOIDED           = 4;
    public const STATUS_REJECT           = 5;

    // 订单状态
    const STATUS_QUOTE_NO             = 0; // 未报价
    const STATUS_QUOTE_ASK            = 1; // 请求报价
    const STATUS_QUOTED               = 2; // 已报价待支付
    const STATUS_PENDING              = 3; // 待处理
    const STATUS_APPLY_NUM            = 4; // 配货中
    const STATUS_SHIPPED              = 5; // 已发货
    const STATUS_DELIVERY_SUCCESS     = 6; // 交运成功 （去除）
    const STATUS_DELIVERY_FAILURE     = 7; // 交运失败 （去除）
    const STATUS_CANCELLED            = 8; // 已取消
    const STATUS_APPLY_NUM_SUCCESS    = 9; // 申请运单号成功 （去除）
    const STATUS_APPLY_NUM_FAILURE    = 10; // 申请运单号失败 （去除）
    const STATUS_WAIT_PRINT           = 11; // 配货  （去除）
    const USER_CHECKED                = 12; // 已签收 （去除）
    const STATUS_WAIT_PRINT_IN_STOCK  = 13; // 配货->有货 （去除）
    const STATUS_WAIT_PRINT_OUT_STOCK = 14; // 配货->无货 （去除）
    const STATUS_STOCK_PENDING        = 15; // 备货订单状态 待入库 （去除）
    const STATUS_SHELVE               = 16; // 订单搁置
    const STATUS_NOT_SHIPPING         = 17; // 订单不发货
    const STATUS_DELIVERED            = 18; // 订单已妥投
    const STATUS_ARCHIVE              = 99;  // 已归档

    // 更换物流订单状态
    const STATUS_APPLY_NUM_CHANGE         = 0; // 申请运单号中
    const STATUS_WAIT_PRINT_CHANGE        = 1; // 配货
    const STATUS_DELIVERY_FAILURE_CHANGE  = 2; // 交运失败
    const STATUS_DELIVERY_SUCCESS_CHANGE  = 3; // 交运成功
    const STATUS_APPLY_NUM_SUCCESS_CHANGE = 4; // 申请运单号成功
    const STATUS_APPLY_NUM_FAILURE_CHANGE = 5; // 申请运单号失败

    public const ONE_PRICE_OPEN = 1;//一口价 开启
    public const ONE_PRICE_CLOSE = 2;//一口价 关闭

    public const ORDER_TYPE_PLACE = 1; //订单类型 1-代发订单
    public const ORDER_TYPE_STOCK = 2; //订单类型 2-备货订单

    public const IS_SHIPPING_NO = 0; //是否发货 0-否
    public const IS_SHIPPING_YES = 1; //是否发货 1-是

    public const IS_DISABLE_NO = 0; //禁止处理 0-否
    public const IS_DISABLE_YES = 1; //禁止处理 1-是

    const FULFILLMENT_PUSH_DEFAULT  = 0;
    const FULFILLMENT_PUSH_WAITING  = 1;
    const FULFILLMENT_PUSH_SUCCESS  = 2;
    const FULFILLMENT_PUSH_ERROR  = 3;


    const FULFILLMENT_PUSH_STATUS_LIST = [
        self::FULFILLMENT_PUSH_DEFAULT => '未推送',
        self::FULFILLMENT_PUSH_WAITING => '推送中',
        self::FULFILLMENT_PUSH_SUCCESS => '推送成功',
        self::FULFILLMENT_PUSH_ERROR => '推送失败',
    ];

    //订单来源
    public const ORDER_SOURCE_AUTO_PULL = 1; //自动同步
    public const ORDER_SOURCE_MANUAL_SYNC = 2; //手动同步
    public const ORDER_SOURCE_WEBHOOK = 3; //webhook推送

    //订单财务状态
    public const FINANCIAL_STATUS_UNPAID = 0; //未支付
    public const FINANCIAL_STATUS_PAID = 1; //已支付
    public const FINANCIAL_STATUS_SUPPLEMENT = 2; //补收费用
    public const FINANCIAL_STATUS_REBATES = 3; //部分退款
    public const FINANCIAL_STATUS_FULL_REFUND = 4; //全额退款

    public const FINANCIAL_STATUS_LIST = [
        self::FINANCIAL_STATUS_UNPAID => '未支付',
        self::FINANCIAL_STATUS_PAID => '已支付',
        self::FINANCIAL_STATUS_SUPPLEMENT => '补收费用',
        self::FINANCIAL_STATUS_REBATES => '部分退款',
        self::FINANCIAL_STATUS_FULL_REFUND => '全额退款',
    ];

    const ORDER_STATUS_NORMAL = 0; //正常状态
    const ORDER_STATUS_ABNORMAL = 1; //异常状态

    const QUOTED_BUT_NOT_COMPLETE = [  // 已报价但是未完成状态集合； 相当已支付的open状态
//        self::STATUS_QUOTED,
        self::STATUS_PENDING,
        self::STATUS_APPLY_NUM,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERY_FAILURE,
        self::STATUS_APPLY_NUM_SUCCESS,
        self::STATUS_APPLY_NUM_FAILURE,
        self::STATUS_WAIT_PRINT,
        self::STATUS_WAIT_PRINT_IN_STOCK,
        self::STATUS_WAIT_PRINT_OUT_STOCK,
        self::STATUS_STOCK_PENDING,
        self::STATUS_SHELVE
    ];

    // 订单申请状态
    const LOGISTICS_WAIT_APPLY = 'wait';
    const LOGISTICS_PROGRESSED = 'progressed';
    const LOGISTICS_APPLY_SUCCESS = 'success';
    const LOGISTICS_APPLY_FAILURE = 'failure';
    const LOGISTICS_STATUS_LIST = [
        self::LOGISTICS_WAIT_APPLY => '待申请',
        self::LOGISTICS_PROGRESSED => '申请中',
        self::LOGISTICS_APPLY_SUCCESS => '申请成功',
        self::LOGISTICS_APPLY_FAILURE => '申请失败',
    ];


    // 订单配货状态
    const STOCK_WAIT = 'wait';
    const STOCK_SUCCESS = 'success';
    const STOCK_LACK = 'lack';
    const STOCK_STATUS_LIST = [
        self::STOCK_WAIT => '待配货',
        self::STOCK_SUCCESS => '有货',
        self::STOCK_LACK => '缺货',
    ];

    public const SYNC_TYPE_AUTOMATIC = 1; //自动同步订单
    public const SYNC_TYPE_MANUAL = 2; //手动同步订单

    public const MAX_PULL_ORDER_RANGE_DAYS = 60;//最大拉取订单范围60天

    // 订单平台抽象状态
    const PLATFORM_PENDING_PAYMENT = 'pending_payment';
    const PLATFORM_ON_HOLD = 'on_hold';
    const PLATFORM_WAITING_SHIPMENT = 'wait_shipping';
    const PLATFORM_PARTIALLY_SHIPPING  = 'partially_shipping';
    const PLATFORM_SHIPPING = 'shipping';
    const PLATFORM_COMPLETED = 'completed';
    const PLATFORM_CANCELLED  = 'cancelled';
    const PLATFORM_REFUNDED = 'refunded';

    const PLATFORM_STATUS_LIST = [
        self::PLATFORM_PENDING_PAYMENT    => '待支付',
        self::PLATFORM_ON_HOLD            => '待处理',
        self::PLATFORM_WAITING_SHIPMENT   => '待发货',
        self::PLATFORM_PARTIALLY_SHIPPING => '部分发货',
        self::PLATFORM_SHIPPING           => '已发货',
        self::PLATFORM_COMPLETED          => '已完成',
        self::PLATFORM_CANCELLED          => '已取消',
        self::PLATFORM_REFUNDED          => '已退货',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function lineItems()
    {
        return $this->hasMany(OrderLineItem::class, 'order_id', 'id');
    }

    /**
     * 关联查询包含软删除后数据
     */
    public function allLineItems()
    {
        return $this->hasMany(OrderLineItem::class, 'order_id', 'id')->withTrashed();
    }

    public function shippingAddress()
    {
        return $this->hasOne(OrderShippingAddress::class, 'order_id', 'id');
    }

    public function shippingLine()
    {
        return $this->hasMany(OrderShippingLine::class, 'order_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(ShopModel::class, 'shop_id', 'id');
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'customer_id', 'id');
    }

    public function purchaseOrder()
    {
        return $this->hasMany(PurchaseOrdersModel::class, 'shop_order_id', 'id');
    }

    public function logisticsApply()
    {
        return $this->hasOne(LogisticsApplyModel::class, 'order_id', 'order_id');
    }

    public function logisticsApplyChange()
    {
        return $this->hasOne(LogisticsApplyModel::class, 'order_id', 'order_id')->orderBy('id', 'desc');
    }

    public function trackInfo()
    {
        return $this->hasOne(LogisticsTrackInfoModel::class, 'order_id', 'order_id');
    }

    public function channel()
    {
        return $this->belongsTo(LogisticsChannelModel::class, 'logistics_provider', 'id');
    }

    public function channelChange()
    {
        return $this->belongsTo(LogisticsChannelModel::class, 'change_logistics_provider', 'id');
    }

    public function fulfillmentOrder()
    {
        return $this->hasOne(FulfillmentOrderModel::class, 'order_id', 'order_id')->orderBy('id', 'desc');
    }

    public function handCustoms()
    {
        return $this->hasMany(HandMovementModel::class, 'order_id', 'id');
    }

    public function expressLine()
    {
        return $this->belongsTo(ExpressLineModel::class, 'express_line_id', 'id');
    }

    public function purchasePlan()
    {
        return $this->belongsToMany(PurchasePlan::class, 'dsp_plan_shop_order_relation', 'order_id','plan_id');
    }

    public function orderStock()
    {
        return $this->hasMany(OrderItemStock::class, 'order_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(ShopOrderLogs::class, 'order_id', 'id');
    }

    public function warehouse()
    {
        return $this->hasOne(WarehouseAddress::class, 'id', 'warehouse_id');
    }

    /**
     * 订单包材
     */
    public function packingMaterials()
    {
        return $this->hasMany(OrderPackingMaterialsModel::class, 'order_id', 'id');
    }

    public function staff()
    {
        return $this->hasOne(Admin::class, 'id', 'staff_id');
    }

    public function expressOrders()
    {
        return $this->belongsToMany(ExpressOrderModel::class, 'dsp_shop_order_express_order_mappings', 'shop_order_id','express_order_id');
    }

    public function chargeType()
    {
        return $this->belongsTo(ChargeTypesModel::class, 'charge_type_id', 'id');
    }

    /**
     * 异常关联
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/3 16:51
     */
    public function abnormal()
    {
        return $this->hasMany(ShopOrderAbnormal::class, 'order_id', 'id')->where('deal_status', ShopOrderAbnormal::STATUS_WAIT_DEAL);
    }

    /**
     * 是否有异常判断
     * @return bool
     */
    public function hasAbnormal()
    {
        return ShopOrderAbnormal::query()->where('order_id', $this->id)->where('deal_status', ShopOrderAbnormal::STATUS_WAIT_DEAL)->exists();
    }

    public function outboundOrders()
    {
        return $this->hasManyThrough(OutboundOrder::class,
            OutboundShopOrderRelate::class,
            'shop_order_id',
            'id',
            'id',
            'outbound_order_id'
        );
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'dsp_package_items', 'shop_order_id', 'package_id', 'id', 'id')
            ->where('status', '!=', Package::STATUS_CANCELED)
            ->distinct();
    }

    public function platformFulfillments()
    {
        return $this->hasMany(ShopOrderFulfillments::class, 'order_id', 'id');
    }


    public static function statusList(): array
    {
        return [
            self::STATUS_QUOTE_NO             => __('待报价'),
            self::STATUS_QUOTE_ASK            => __('报价中'),
            self::STATUS_QUOTED               => __('已报价'),
            self::STATUS_PENDING              => __('已付款'),
            self::STATUS_APPLY_NUM            => __('配货中'),
            self::STATUS_SHIPPED              => __('已出库'),
            self::STATUS_DELIVERY_SUCCESS     => __('发货成功'),
            self::STATUS_DELIVERY_FAILURE     => __('发货失败'),
            self::STATUS_CANCELLED            => __('已取消'),
            self::STATUS_APPLY_NUM_SUCCESS    => __('申请运单号-成功'),
            self::STATUS_APPLY_NUM_FAILURE    => __('申请运单号-失败'),
            self::STATUS_WAIT_PRINT           => __('配货'),
            self::USER_CHECKED                => __('已签收'),
            self::STATUS_WAIT_PRINT_IN_STOCK  => __('配货-有货'),
            self::STATUS_WAIT_PRINT_OUT_STOCK => __('配货-无货'),
            self::STATUS_STOCK_PENDING        => __('待入库'),
            self::STATUS_SHELVE               => __('搁置'),
            self::STATUS_NOT_SHIPPING         => __('不发货'),
            self::STATUS_DELIVERED            => __('已妥投'),
            self::STATUS_ARCHIVE              => __('已归档'),
        ];
    }

    public function getStatusNameAttribute()
    {

        $status = self::statusList();

        return $status[$this->order_status] ?? '-';
    }

    //客户端订单状态
    public function getClientStatusNameAttribute()
    {

        return [
            self::STATUS_QUOTE_NO             => '未报价',
            self::STATUS_QUOTE_ASK            => '报价中',
            self::STATUS_QUOTED               => '待付款',

            self::STATUS_PENDING              => '处理中',
            self::STATUS_APPLY_NUM            => '处理中',
            self::STATUS_APPLY_NUM_SUCCESS    => '处理中',
            self::STATUS_APPLY_NUM_FAILURE    => '处理中',
            self::STATUS_WAIT_PRINT           => '处理中',
            self::STATUS_WAIT_PRINT_IN_STOCK  => '处理中',
            self::STATUS_WAIT_PRINT_OUT_STOCK => '处理中',

            self::STATUS_DELIVERY_FAILURE     => '处理中',


            self::STATUS_DELIVERY_SUCCESS     => '已完成',
            self::USER_CHECKED                => '已完成',
            self::STATUS_SHIPPED              => '已完成',

            self::STATUS_STOCK_PENDING        => '待入库',

            self::STATUS_CANCELLED            => '已取消',

            self::STATUS_SHELVE               => '搁置',

            self::STATUS_NOT_SHIPPING         => '不发货',
            self::STATUS_NOT_SHIPPING         => '不发货',
        ][intval($this->order_status)] ?? '-';
    }


    public function getFulfillmentPushStatusNameAttribute()
    {
        return self::FULFILLMENT_PUSH_STATUS_LIST[$this->fulfillment_push_status] ?? '-';
    }

    public function getFulfillmentPlatformNameAttribute()
    {
        return ThirdPartyWarehouseConfig::WAREHOUSE_PLATFORM_LIST[$this->fulfillment_platform] ?? '-';
    }

    public static function getStatusName($orderStatus)
    {
        // $status = [
        //     __('未报价'),
        //     __('待报价'),
        //     __('待支付'),
        //     __('已付款'),
        //     __('运单号申请中'),
        //     __('发货中'),
        //     __('发货成功'),
        //     __('发货失败'),
        //     __('已取消'),
        //     __('申请运单号成功'),
        //     __('申请运单号失败'),
        //     __('配货'),
        //     __('已签收'),
        //     __('有货'),
        //     __('无货'),
        //     __('待入库'),
        // ];

        $status = self::statusList();

        return $status[$orderStatus];
    }

    public function getStatusNameChangeAttribute()
    {
        $status = [
            __('申请中'), __('配货'), __('发货成功'), __('发货失败'), __('申请成功'), __('申请失败')
        ];

        return $status[$this->change_status];
    }

    public function getPlatformStatusNameAttribute()
    {
        return self::PLATFORM_STATUS_LIST[$this->platform_status] ?? '-';
    }


    public static function init($item, $shop)
    {
        isset($item['cancelled_at']) && $item['cancelled_at'] = Carbon::parse($item['cancelled_at'])->format('Y-m-d H:i:s');
        $item['created_at'] && $item['created_at'] = Carbon::parse($item['created_at'])->format('Y-m-d H:i:s');
        $item['updated_at'] && $item['updated_at'] = Carbon::parse($item['updated_at'])->format('Y-m-d H:i:s');
        $sku_status = count($item['line_items']) > 1 ? 2 : ($item['line_items'][0]['quantity'] === 1 ? 0 : 1);

        return [
            'customer_id'                   => $shop->customer_id,
            'order_id'                      => $item['order_id'],
            'platform_order_id'             => $item['platform_order_id'] ?? $item['order_id'],
            'platform'                      => $shop->platform,
            'cancel_reason'                 => $item['cancel_reason'] ?? null,
            'cancelled_at'                  => $item['cancelled_at'] ?? null,
            'created_at'                    => $item['created_at'],
            'updated_at'                    => $item['updated_at'],
            'currency'                      => $item['currency'],
            'current_subtotal_price'        => $item['current_subtotal_price'] ?? 0,
            'current_total_discounts'       => $item['current_total_discounts'] ?? 0,
            'current_total_price'           => $item['current_total_price'] ?? 0,
            'current_total_tax'             => $item['current_total_tax'] ?? 0,
            'subtotal_price'                => $item['subtotal_price'] ?? 0,
            'payment_info'                  => $item['payment_info'] ?? [],
            'shop_id'                       => $shop->id,
            'sku_status'                    => $sku_status,
            'name'                          => $item['name'] ?? '',
            'order_status'                  => $item['order_status'] ?? 0,
            'remark'                        => $item['remark'] ?? '',
            'platform_order_status'         => $item['platform_order_status'] ?? '', //平台订单状态
            'platform_payment_status'       => $item['platform_payment_status'] ?? '', //平台支付状态
            'platform_fulfillment_status'   => $item['platform_fulfillment_status'] ?? '', //平台发货状态
            'platform_status'               => $item['platform_status'] ?? null,  // 平台抽象状态
            'sync_type'                     => $item['sync_type'] ?? 1, //订单同步来源 1自动同步 2手动同步
            'payment_price'                 => $item['payment_price'] ?? 0,
            'logistics_provider'            => $item['logistics_provider'] ?? '',
            'logistics_provider_code'       => $item['logistics_provider_code'] ?? '',
            'express_line_id'               => $item['express_line_id'] ?? 0,
            'platform_order_url'            => $item['platform_order_url'] ?? ''
        ];
    }

    /**
     * @param $condition
     * @param $length
     * @param $width
     * @param $height
     * @param ExpressLineModel|null $expressLine
     * @return bool
     */
    public function isNoThrow($condition, $length, $width, $height, ?ExpressLineModel $expressLine = null, ?int $weight = null): bool
    {
        if ($expressLine && (!$expressLine->has_factor || $expressLine->factor === 0)) {
            return false;
        }

        if (empty($condition)) {
            return false;
        }

        $length = (int)($length / 100);
        $width = (int)($width / 100);
        $height = (int)($height / 100);

        $c = $condition['condition'];
        $v = (int)($condition['value']);

        switch ($condition['type']) {
            case ExpressLineModel::NO_THROW_CONDITION_1 :
            {
                return eval("return ({$length}{$c}{$v} && {$width}{$c}{$v} && {$height}{$c}{$v});");
            }
            case ExpressLineModel::NO_THROW_CONDITION_2 :
            {
                return eval("return ({$length}{$c}{$v} || {$width}{$c}{$v} || {$height}{$c}{$v});");
            }
            case ExpressLineModel::NO_THROW_CONDITION_3 :
            {
                return eval("return ({$length}+{$width}+{$height}){$c}{$v};");
            }
            case ExpressLineModel::NO_THROW_CONDITION_4 :
            {
                return eval("return ({$length}*{$width}*{$height}){$c}{$v};");
            }
            case ExpressLineModel::NO_THROW_CONDITION_5 :
            {
                $actualWeight = $weight ?: ($this->actual_weight ?: 0) ;
                if(!$expressLine){
                    $expressLine = $this->expressLine;
                }
                $volumeWeight = (int)ceil(($length * $width * $height / $expressLine->factor) * 1000);
                $v *= 1000;
                return eval("return ({$volumeWeight}-{$actualWeight}){$c}{$v};");
            }
        }

        return false;
    }

}
