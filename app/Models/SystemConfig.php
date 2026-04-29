<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dsp_system_configs';

    protected $guarded = [];

    protected $casts = [
        'ext' => 'array'
    ];

    public const SHOPIFY_APP_REVIEW_MODE = 'shopify_app_review_mode';  // shopify 审核模式
    public const ALIBABA_PRICE = 'alibaba_price'; // 1688价格配置
    public const IS_AUTO_ORDER_QUOTE = 'is_auto_order_quote'; // 1688价格配置
    public const CUSTOM_BALANCE = 'custom_balance'; // 客户余额通知配置
    public const FAST_IN_FAST_OUT = 'fast_in_fast_out'; // 仓库设置-快进快出 1开启 0关闭
    public const ORDER_ONE_PRICE = 'order_one_price'; // 默认订单一口价 1开启 0关闭
    public const QUOTE_FAVOURABLE_SETTING = 'quote_favourable_setting';//报价优惠设置
    public const SYNC_WAYBILL_NUMBER = 'sync_waybill_number'; // 同步运单号
    public const USE_CLIENT_TAX = 'use_client_tax';//申请运单使用客户税号 1开启 0关闭
    public const OPEN_CREDIT_LINES = 'open_credit_line';//开启信用额度 1开启 0关闭
    public const OPEN_PRODUCT_DEVELOP_AUDIT = 'open_product_develop_audit';//开启产品开发审核流程 1开启 0关闭
    public const ADMIN_TAB_ICON = 'admin_tab_icon';//管理端标签页icon
    public const ADMIN_TAB_TITLE = 'admin_tab_title';//管理端标签页标题
    public const ADMIN_MENU_LOGO = 'admin_menu_logo';//管理端菜单logo
    public const ADMIN_LOGIN_IMAGE = 'admin_login_image';//管理端登录页图片
    public const COS_CONFIG = 'cos_config';//腾讯云存储cos配置项
    public const SHOW_NOT_QUOTED_SHOP_PRODUCT = 'show_not_quoted_shop_product'; //显示未报价的店铺产品 1开启 0关闭 默认开启
    public const PALLET_ORDERING_MODE = 'pallet_ordering_mode'; //货盘下单模式 1代发+备货 2备货
    public const SYNC_ORDER_FILTER_RULE = 'sync_order_filter_rule'; //同步订单过滤规则 1所有订单 2部分订单(产品已报价)
    public const MANUAL_PULL_ORDER_SWITCH = 'manual_pull_order_switch'; //手动拉单开关
    public const WAREHOUSE_PICKING_TYPE = 'warehouse_picking_type';//仓库出库单-拣货方式 1-波次拣货 2-直接拣货
    public const WAREHOUSE_WEIGHT = 'warehouse_weight';//仓库出库单-称重 1-开启 0-关闭

    //商品报价
    public const PRODUCT_QUOTE_CALCULATE_METHOD = 'product_quote_calculate_method';//产品利润计算方式 1按百分比计算 2按固定金额计算
    public const PRODUCT_QUOTE_DEFAULT_PROFIT_RATE = 'product_quote_default_profit_rate';//商品报价默认利润率
    public const PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT = 'product_quote_default_fixed_amount';//商品报价默固定金额
    // public const PRODUCT_QUOTE_DEFAULT_MINIMUM_PROFIT = 'product_quote_default_minimum_profit';//商品报价默认最低利润

    //运费报价
    public const FREIGHT_QUOTE_AMOUNT_TYPE = 'freight_quote_amount_type';//价格表金额 1实际报价 2物流成本
    public const FREIGHT_QUOTE_CALCULATE_METHOD = 'freight_quote_calculate_method';//物流利润计算方式 1按百分比计算 2按固定金额计算
    public const FREIGHT_QUOTE_DEFAULT_PROFIT_RATE = 'freight_quote_default_profit_rate';//运费报价默认利润率
    public const FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT = 'freight_quote_default_fixed_amount';//运费报价默固定金额
    // public const FREIGHT_QUOTE_DEFAULT_MINIMUM_PROFIT = 'freight_quote_default_minimum_profit';//运费报价默认最低利润


    /**
     * 客户端配置项
     */
    public const CLIENT_URL = 'client_url';//客户端URL
    public const CUSTOMER_SERVICE_DESCRIPTION = 'customer_service_description';//客户端-客户服务描述
    public const CLIENT_1688_MENU = 'client_1688_menu';//客户端1688菜单 1显示 0隐藏
    public const CLIENT_PHONE_REGISTER = 'client_phone_register';//客户端手机号注册 1开启 0关闭
    public const CLIENT_PROMOTION_LINK = 'client_promotion_link';//客户端推广链接 1显示 0隐藏
    public const CLIENT_TAB_ICON = 'client_tab_icon';//客户端标签页icon
    public const CLIENT_TAB_TITLE = 'client_tab_title';//客户端标签页标题
    public const CLIENT_MENU_LOGO = 'client_menu_logo';//客户端菜单logo
    public const CLIENT_LOGIN_IMAGE = 'client_login_image';//客户端登录页图片
    public const CLIENT_CUSTOMER_SERVICE_NAME = 'client_customer_service_name'; //客户端客服名称
    public const CLIENT_CUSTOMER_SERVICE_AVATAR = 'client_customer_service_avatar'; //客户端客服头像
    public const CLIENT_SHOPIFY_PRIVACY_POLICY = 'client_shopify_privacy_policy'; //shopify隐私协议

    public const DEFAULT_VALUE = [
        self::SHOPIFY_APP_REVIEW_MODE => 0,   // 默认不开启
        self::IS_AUTO_ORDER_QUOTE => 0,   // 是否开启默认不开启
        self::ALIBABA_PRICE => ['discount' => 1, 'fixed_price' => 0],
		self::CUSTOM_BALANCE => 0,
        self::FAST_IN_FAST_OUT => 1, //默认开启仓库快进快出
        self::SYNC_WAYBILL_NUMBER => 3, // 1 申请运单号成功 2待打单 3发货成功
        self::ORDER_ONE_PRICE => 0, //订单一口价
        self::QUOTE_FAVOURABLE_SETTING => ['is_open' => 0, 'price' => 0],//报价优惠设置 is_open：1开启 0关闭 price：优惠价格
        self::CUSTOMER_SERVICE_DESCRIPTION => '',
        self::CLIENT_URL => '', //客户端URL
        self::CLIENT_1688_MENU => 0, //关闭客户端1688菜单 0-开启 1-关闭
        self::CLIENT_PHONE_REGISTER => 1, //客户端手机号注册 1开启 0关闭 默认开启
        self::CLIENT_PROMOTION_LINK => 1, //客户端推广链接 1显示 0隐藏 默认显示
        self::USE_CLIENT_TAX => 1,//申请运单使用客户税号 1开启 0关闭 默认开启
        self::OPEN_CREDIT_LINES => 0,//开启信用额度 1开启 0关闭 默认关闭
        self::OPEN_PRODUCT_DEVELOP_AUDIT => 0,//开启产品开发审核流程 1开启 0关闭
        self::ADMIN_TAB_ICON => '', //管理端标签页icon
        self::ADMIN_TAB_TITLE => '', //管理端标签页标题
        self::ADMIN_MENU_LOGO => '', //管理端菜单logo
        self::ADMIN_LOGIN_IMAGE => '', //管理端登录页图片
        self::CLIENT_TAB_ICON => '', //客户端标签页icon
        self::CLIENT_TAB_TITLE => '', //客户端标签页标题
        self::CLIENT_MENU_LOGO => '', //客户端菜单logo
        self::CLIENT_LOGIN_IMAGE => '', //客户端登录页图片
        self::CLIENT_CUSTOMER_SERVICE_NAME => 'DropShipping', //客户端客服名称
        self::CLIENT_CUSTOMER_SERVICE_AVATAR => '', //客户端客服头像
        self::SHOW_NOT_QUOTED_SHOP_PRODUCT => 1, //显示未报价的店铺产品
        self::PALLET_ORDERING_MODE => 1, //货盘下单模式 1代发+备货 2仅备货
        self::CLIENT_SHOPIFY_PRIVACY_POLICY => '', //shopify隐私协议
        self::SYNC_ORDER_FILTER_RULE => 1, //同步订单过滤规则 1所有订单 2部分订单(产品已报价)
        self::MANUAL_PULL_ORDER_SWITCH => 1, //手动拉单开关
        //腾讯云储存 cos
        self::COS_CONFIG => [
            'app_id' => '',
            'secret_id' => '',
            'secret_key' => '',
            'region' => '',
            'bucket' => '',
            'enable' => 0,
        ],
        self::WAREHOUSE_PICKING_TYPE => 1,//仓库出库单-拣货方式 1-波次拣货 2-直接拣货
        self::WAREHOUSE_WEIGHT => 1,//仓库出库单-称重 1-开启 0-关闭
        self::PRODUCT_QUOTE_DEFAULT_PROFIT_RATE => '',
        self::PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT => '',
        self::PRODUCT_QUOTE_CALCULATE_METHOD => CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_1,
        self::FREIGHT_QUOTE_AMOUNT_TYPE => CustomsQuoteConfig::FREIGHT_QUOTE_AMOUNT_TYPE_1,
        self::FREIGHT_QUOTE_CALCULATE_METHOD => CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_1,
        self::FREIGHT_QUOTE_DEFAULT_PROFIT_RATE => '',
        self::FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT => '',
    ];

    public static function configList(): array
    {
        return array_keys(self::DEFAULT_VALUE);
    }

    public static function clientConfigList(): array
    {
        return [
            self::SHOPIFY_APP_REVIEW_MODE,
            self::CUSTOMER_SERVICE_DESCRIPTION,
            self::CLIENT_1688_MENU,
            self::CLIENT_PHONE_REGISTER,
            self::CLIENT_PROMOTION_LINK,
            self::OPEN_CREDIT_LINES,
            self::OPEN_PRODUCT_DEVELOP_AUDIT,
            self::CLIENT_TAB_ICON,
            self::CLIENT_TAB_TITLE,
            self::CLIENT_MENU_LOGO,
            self::CLIENT_LOGIN_IMAGE,
            self::CLIENT_CUSTOMER_SERVICE_NAME,
            self::CLIENT_CUSTOMER_SERVICE_AVATAR,
            self::PALLET_ORDERING_MODE,
            self::CLIENT_SHOPIFY_PRIVACY_POLICY,
        ];
    }

    /** config_value 属性修改器，同时兼容字符串和数组对象存储
     * @return Attribute
     */
    protected function configValue(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
            $data = json_decode($value, true);
            if (is_null($data)) return $value;
            return $data;
        },
            set: function ($value) {
            if (is_array($value)) return json_encode($value);
            return $value;
        }
        );
    }

    /**
     * 系统配置项列表
     * @return string[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/13 16:04
     */
    public static function systemConfigList()
    {
        return [
            self::CUSTOM_BALANCE => '客户余额通知设置',
            self::USE_CLIENT_TAX => '申请运单使用客户税号',
            self::SYNC_WAYBILL_NUMBER => '运单信息何时同步',
            self::OPEN_CREDIT_LINES => '开启信用额度',
            self::OPEN_PRODUCT_DEVELOP_AUDIT => '开启产品开发审核流程',
            self::PALLET_ORDERING_MODE => '货盘下单模式',
            self::SYNC_ORDER_FILTER_RULE => '同步订单规则',
            self::MANUAL_PULL_ORDER_SWITCH  => '手动拉单开关',
            self::PRODUCT_QUOTE_CALCULATE_METHOD => '产品报价利润计算方式',
            self::PRODUCT_QUOTE_DEFAULT_PROFIT_RATE => '产品报价默认利润率',
            self::PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT => '产品报价默认固定金额',
            self::FREIGHT_QUOTE_AMOUNT_TYPE => '运费报价价格表金额为',
            self::FREIGHT_QUOTE_CALCULATE_METHOD => '运费报价利润计算方式',
            self::FREIGHT_QUOTE_DEFAULT_PROFIT_RATE => '运费报价默认利润率',
            self::FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT => '运费报价默认固定金额',
            self::IS_AUTO_ORDER_QUOTE => '新订单自动报价',
            self::ORDER_ONE_PRICE => '默认商品一口价',
            self::QUOTE_FAVOURABLE_SETTING => '报价优惠设置'
        ];
    }

    public static function quoteConfigList()
    {
        return [
            self::PRODUCT_QUOTE_CALCULATE_METHOD,
            self::PRODUCT_QUOTE_DEFAULT_PROFIT_RATE,
            self::PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT,
            self::FREIGHT_QUOTE_AMOUNT_TYPE,
            self::FREIGHT_QUOTE_CALCULATE_METHOD,
            self::FREIGHT_QUOTE_DEFAULT_PROFIT_RATE,
            self::FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT,
            self::IS_AUTO_ORDER_QUOTE,
            self::ORDER_ONE_PRICE,
            self::QUOTE_FAVOURABLE_SETTING
        ];

    }

    /**
     * 系统配置项-设置值列表
     * @return string[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/13 16:04
     */
    public static function systemConfigValueList()
    {
        return [
            self::CUSTOM_BALANCE => [],
            self::USE_CLIENT_TAX => [
                1 => '开启',
                0 => '关闭',
            ],
            self::SYNC_WAYBILL_NUMBER => [
                1 => '运单申请成功',
                2 => '配货',
                3 => '发货',
            ],
            self::OPEN_CREDIT_LINES => [
                1 => '开启',
                0 => '关闭',
            ],
            self::OPEN_PRODUCT_DEVELOP_AUDIT => [
                1 => '开启',
                0 => '关闭',
            ],
            self::PALLET_ORDERING_MODE => [
                1 => '代发+备货',
                2 => '仅备货',
            ],
            self::SYNC_ORDER_FILTER_RULE => [
                1 => '所有订单',
                2 => '部分订单(产品已报价)',
            ],
            self::MANUAL_PULL_ORDER_SWITCH => [
                1 => '开启',
                0 => '关闭',
            ],
            self::PRODUCT_QUOTE_CALCULATE_METHOD => [
                CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_1 => '按百分比计算',
                CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_2 => '按固定金额计算',
            ],
            self::PRODUCT_QUOTE_DEFAULT_PROFIT_RATE => [],
            self::PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT => [],
            self::FREIGHT_QUOTE_AMOUNT_TYPE => [
                CustomsQuoteConfig::FREIGHT_QUOTE_AMOUNT_TYPE_1 => '实际报价',
                CustomsQuoteConfig::FREIGHT_QUOTE_AMOUNT_TYPE_2 => '物流成本',
            ],
            self::FREIGHT_QUOTE_CALCULATE_METHOD => [
                CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_1 => '按百分比计算',
                CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_2 => '按固定金额计算',
            ],
            self::FREIGHT_QUOTE_DEFAULT_PROFIT_RATE => [],
            self::FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT => [],
            self::IS_AUTO_ORDER_QUOTE => [
                1 => '开启',
                0 => '关闭',
            ],
            self::ORDER_ONE_PRICE => [
                1 => '开启',
                0 => '关闭',
            ],
            self::QUOTE_FAVOURABLE_SETTING => [
                1 => '开启',
                0 => '关闭',
            ]
        ];
    }

}
