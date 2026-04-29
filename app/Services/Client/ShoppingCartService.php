<?php

namespace App\Services\Client;


use App\Helper\CurrencyConverter;
use App\Http\Controllers\Client\ExpressPriceController;
use App\Lib\Code;
use App\Lib\Platform;
use App\Models\ClientGoods;
use App\Models\Goods;
use App\Models\GoodsAudit;
use App\Models\GoodsSku;
use App\Models\LogisticsChannelModel;
use App\Models\LogisticsCustomsDeclarationModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\OrderItemMapping;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\ShopModel;
use App\Models\ShoppingCart;
use App\Models\Stock;
use App\Models\SystemConfig;
use App\Models\WarehouseAddress;
use App\Services\Admin\GoodsService;
use App\Services\Base\SystemConfigService;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class ShoppingCartService extends BaseService
{
    public $orderBy = [
        'created_at' => 'desc'
    ];

    public function __construct(ShoppingCart $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
    }

    public function index()
    {
        $this->query->where(['user_id' => getUserId()]);

        $this->query->with(['goods' => function ($query) {
            $query->with('goodsAudit');
        }]);

        $list = parent::index();

        $currencyConverter = new CurrencyConverter();
        //匹配库存
        $list->map(function ($item) use ($currencyConverter) {
            $skus = $item->skus;
            foreach ($skus as &$sku) {
                $goodsSku = GoodsSku::query()->where('sku_id', $sku['sku_id'])->first();
                $sku['weight'] = $goodsSku->weight ?? ($sku['weight'] ?? 0);

                $sku['stock'] = (int)Stock::query()->where(['sku' => $sku['sku_id'], 'custom_id' => getCustomId()])->value('quantity');

                //CNY => USD
//                $sku['sale_price'] = $currencyConverter->reversedCurrenciesExchange($sku['sale_price']);
            }
            $item->skus = $skus;
            return $item;
        });

        return $list;
    }

    public function add()
    {
        validator($this->formData, [
            'goods_id' => 'sometimes|nullable|string',
            'spu' => 'required|string',
            'platform' => 'required|string',
            'sku_gross_weight' => 'integer',
            'select_attrs' => 'required|array',
            'select_attrs.*.select_num' => 'required|integer',
        ])->validate();

        $selfGoods = 0;
        //商品先加入用户已选择产品
        switch ($this->formData['platform']) {
            case Platform::LOCAL:
                $spu = Goods::query()->where('id', $this->formData['goods_id'])->value('spu');
                $clientGoodsId = ClientGoods::query()->where('spu', $spu)->latest('id')->value('id');
                $goods = Goods::query()->with(['category', 'skus'])->where('spu', $this->formData['spu'])->first();
                if (empty($goods)) throw new AccidentException('Product not found');
                $goodsId = $clientGoodsId ?: (new AdminGoodsService())->addToClientGoods($goods);
                $selfGoods = $goods->self_goods;
                break;
            case '1688':
                //根据商品ID判断此商品是否加入了本地品，未加入的话先添加审核，审核通过后才能继续加入购物车
                $spu = $this->add1688ToGoods($this->formData['spu']);

                //根据spu查询客户产品ID，不存在则新增客户产品
                $clientGoodsId = ClientGoods::query()->where('spu', $spu)->latest('id')->value('id');
                if ($clientGoodsId) {
                    $goodsId = $clientGoodsId;
                } else {
                    $goods = Goods::query()->where('spu', $spu)->first();
                    $goodsId = (new AdminGoodsService())->addToClientGoods($goods);
                }
                break;
            default:
                $goodsId = 0;
        }

        if (empty($goodsId)) {
            throw new AccidentException('添加购物车失败，请稍后再试', Code::OPERATE_FAIL);
        }

        $goodsDetail = (new ClientGoodsService())->show($goodsId);

        return DB::transaction(function () use ($goodsDetail, $selfGoods) {
            $skus = $goodsDetail->skus->keyBy('sku_id');
            $cartsSkus = [];
            foreach ($this->formData['select_attrs'] as $attr) {
                $goodsSku = $skus[$attr['sku_id']] ?? [];
                if (empty($goodsSku)) throw  new AccidentException("SKU ID {$attr['sku_id']} 不存在", Code::OPERATE_FAIL);
                $skuInfo = $goodsSku->toArray();
                // $skuInfo['sale_price'] = $currencyConverter->reversedCurrenciesExchange($goodsSku->quote_price);
                $skuInfo['sale_price'] =$goodsSku->sale_price; // 商品价格 取产品报价
                $skuInfo['carts_num'] = $attr['select_num'];
                $cartsSkus[] = $skuInfo;
            }
            $carts = $this->model::query()->where('spu', $goodsDetail['spu'])->first();
            if ($carts) {
                $oldCartsSkus = array_column($carts->skus,null,'sku_id');
                foreach ($cartsSkus as &$cartsSku) {
                    $skuId = $cartsSku['sku_id'];
                    if (isset($oldCartsSkus[$skuId])) {
                        $cartsSku['carts_num'] += $oldCartsSkus[$skuId]['carts_num'];

                        unset($oldCartsSkus[$skuId]);
                    }
                }
                if ($oldCartsSkus) {
                    $cartsSkus = array_merge($cartsSkus, $oldCartsSkus);
                }
                $carts->delete();
            }
            $goodsDetail = $goodsDetail->toArray();
            $goodsDetail['platform'] = $this->formData['platform'];
            $goodsDetail['goods_id'] = $goodsDetail['id'];
            $goodsDetail['sku_gross_weight'] = 0;
            $goodsDetail['skus'] = array_values($cartsSkus);
            $goodsDetail['self_goods'] = $selfGoods;

            $cartsData = $this->model::init($goodsDetail);
            $this->model::query()->create($cartsData);

            return $this->model::getCartsNum();
        });

    }

    public function storeOrder()
    {
        $orderType = $this->formData['carts']['order_type'] ?? 1;

        //备货订单
        if ((int)$orderType === Order::ORDER_TYPE_STOCK) {
            return $this->storeStockOrder();
        }

        validator($this->formData, $this->rules())->validate();

        DB::beginTransaction();
        try {
            $shopId = $this->formData['carts']['shop_id'] ?? 0;
            if (empty($shopId)) {
                $shop = ShopModel::create([
                    'shop_name' => 'local_shop'.getCustomId(),
                    'platform' => Platform::LOCAL,
                    'customer_id' => getCustomId(),
                    'platform_shop_id' => 0
                ]);
                $shopId = $shop->id;
            }

            //使用客户库存
            $useCustomerStock = $this->formData['carts']['use_customer_stock'] ?? 0;

            $lineItems = [];
            $goodsPrice = $totalWeight = 0;
            foreach ($this->formData['products'] as $item) {
                foreach ($item['skus'] as $sku) {
                    //使用客户库存 校验库存
                    if ($useCustomerStock) {
                        $stock = (int)Stock::query()->where(['sku' => $sku['sku_id'], 'custom_id' => getCustomId()])->value('quantity');
                        if ($sku['carts_num'] > $stock) {
                            throw new AccidentException('库存不足', Code::OPERATE_FAIL);
                        }
                    }

                    //获取产品价格
                    $quotePrice = GoodsSku::query()->where('sku_id', $sku['sku_id'])->value('quote_price');
                    if (empty($quotePrice)) {
                        throw new AccidentException('The price of the item is incorrect', Code::OPERATE_FAIL);
                    }

                    //CNY => USD
                    //$quotePrice = (new CurrencyConverter())->reversedCurrenciesExchange($quotePrice);

                    $lineItems[] = [
                        'name' => $item['goods_name'],
                        'title' => $item['goods_name'],
                        'quantity' => $sku['carts_num'],
                        'price' => $quotePrice,
                        'product_id' => $sku['goods_id'],
                        'sku' => $sku['sku_id'],
                        'total_discount' => $sku['carts_num'] * $quotePrice,
                        'variant_id' => $sku['id'],
                        'variant_title' => $sku['spec_name'],
                        'line_item_id' => $sku['sku_id'],
                        'imgs' => empty($sku['images']) ? [] : json_encode($sku['images']),
                        'quote_price' => $quotePrice,
                    ];

                    $goodsPrice += $sku['carts_num'] * $quotePrice;
                    $totalWeight += $sku['carts_num'] * $sku['weight'];
                }
            }

            //sku状态：0-单sku单数 1-单sku多数 2-多sku
            if (count($lineItems) > 1) {
                $skuStatus = 2;
            } else {
                $skuStatus = $lineItems[0]['quantity'] > 1 ? 1 : 0;
            }

            //计算物流费用 start
            $expressParams = $this->formData['express'];
            $expressParams['weight'] = $totalWeight;

            $logisticsFee = 0;
            $expressPriceList = (new ExpressPriceController())->query($expressParams, false);
            foreach ($expressPriceList as $expressPrice) {
                if ($expressPrice['id'] == $this->formData['logistics']['id']) {
                    $logisticsFee = $expressPrice['expire_fee'] / 100;
                    break;
                }
            }

//            if (empty($logisticsFee)) {
//                throw new AccidentException('计算物流费用失败，请重新选择物流', Code::OPERATE_FAIL);
//            }

            //$logisticsFee = (new CurrencyConverter())->reversedCurrenciesExchange($logisticsFee);// CNY => USD
            //计算物流费用 end

            $orderId = generateOrderId();

            //物流信息
            $channel = LogisticsChannelModel::with('expressCompanies')->where('code', $this->formData['logistics']['channel_code'])->first();

            //使用客户库存 不计算商品金额
            if ($useCustomerStock) {
                $goodsPrice = 0;
            }

            $orderData = [
                'customer_id' => getCustomId(),
                'order_id' => $orderId,
                'platform' => Platform::LOCAL,
                'currency' => 'USD',
                'current_subtotal_price' => $goodsPrice,//税前金额
                'current_total_price' => $goodsPrice,//合计金额
                'subtotal_price' => $goodsPrice,//小计
                'vendor_price' => $goodsPrice,//报价金额
                'order_status' => Order::STATUS_QUOTED,
                'shop_id' => $shopId,
                'custom_order_id' => $orderId,
                'logistics_fee' => $logisticsFee,
                'express_line_id' => $this->formData['logistics']['id'] ?? 0,
                'logistics_provider' => $channel->id ?? 0,//dsp_logistics_channel.id
                'logistics_provider_code' => $channel->expressCompanies->code ?? '',//物流商编码 dsp_express_companies.code
                'payment_info' => [],
                'sku_status' => $skuStatus,
                'use_customer_stock' => $useCustomerStock,
            ];
            $order = Order::query()->create($orderData);

            $address = $this->formData['address'];
            $countryCode = $address['country']['code'] ?? '';
            if (empty($countryCode)) {
                $countryCode = DB::table('world_countries_locale')
                    ->join('world_countries', 'world_countries.id', '=', 'world_countries_locale.country_id')
                    ->where('world_countries_locale.name', $address['country']['name'])
                    ->value('world_countries.code');
            }

            $addressData = [
                'order_id'          => $order->id,
                'first_name'        => $address['first_name'],
                'last_name'         => $address['last_name'],
                'name'              => $address['first_name'] . ' ' . $address['last_name'],
                'country'           => $address['country']['name'],
                'country_code'      => strtoupper($countryCode),
                'province'          => $address['province'],
                'city'              => $address['city'],
                'address1'          => $address['address_detail'],
                'phone'             => $address['phone'],
                'zip'               => $address['zip'],
                'tax'               => $address['tax_id'] ?? '',
                'email'             => $address['email'] ?? '',
            ];
            OrderShippingAddress::query()->create($addressData);

            foreach ($lineItems as &$line) {
                $line['order_id'] = $order->id;
            }
            OrderLineItem::query()->insert($lineItems);

            $lineItems = OrderLineItem::query()->where('order_id', $order->id)->get();

            $lineItems->each(function ($item) {
                $goodsSku = GoodsSku::query()->where('sku_id', $item->sku)->first();

                //关联sku
                $mapping = OrderItemMapping::query()->where([
                    'platform_variant_id' => $item->variant_id,
                    'platform' => Platform::LOCAL,
                ])->first();
                if (empty($mapping)) {
                    $mapping = new OrderItemMapping();
                }
                $mapping->platform = Platform::LOCAL;
                $mapping->platform_variant_id = $item->variant_id;
                $mapping->goods_sku_id = $goodsSku->id ?? 0;
                $mapping->save();
                $item->update(['goods_sku_id' => $mapping->goods_sku_id]);

                //报关信息
                // 获取商品报关信息
                $goodsDeclaration = LogisticsCustomsDeclarationModel::query()->where('goods_sku_id', $mapping->goods_sku_id)->first();
                if (!empty($goodsDeclaration)) {
                    $orderDeclarationData = [
                        'order_item_id' => $item->id,
                        'cn_name' => $goodsDeclaration['cn_name'] ?: $item->name,
                        'en_name' => $goodsDeclaration['en_name'],
                        'unit_price' => empty($goodsDeclaration['unit_price']) ? $goodsDeclaration['unit_price'] : $item->price,
                        'code' => $goodsDeclaration['code'],
                        'weight' => $goodsDeclaration['weight'] ?: ($goodsSku->weight ?? 0),
                        'attributes' => $goodsDeclaration['attributes'],
                        'material' => $goodsDeclaration['material'],
                        'use_to' => $goodsDeclaration['use_to'],
                    ];
                } else {
                    $orderDeclarationData = [
                        'order_item_id' => $item->id,
                        'cn_name' => $item->name,
                        'unit_price' => $item->price,
                        'weight' => $goodsSku->weight ?? 0,
                    ];
                }

                OrderDeclarationModel::query()->create($orderDeclarationData);
            });

            $deleteCartIds = array_column($this->formData['products'], 'id');
            $this->model::query()->whereIn('id', $deleteCartIds)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            logger('购物车添加订单失败：',[
                'msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            throw new AccidentException('订单提交失败:' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        $return = [
            'order_id' => $order->id,
            'carts_num' => ShoppingCart::getCartsNum(),
        ];

        return $return;
    }

    /**
     * 备货订单
     */
    public function storeStockOrder(): array
    {
        validator($this->formData, [
            'carts.warehouse_id' => 'required',
        ])->validate();

        DB::beginTransaction();
        try {
            $lineItems = [];
            $goodsPrice = 0;
            foreach ($this->formData['products'] as $item) {
                foreach ($item['skus'] as $sku) {
                    //获取产品价格
                    $quotePrice = GoodsSku::query()->where('sku_id', $sku['sku_id'])->value('quote_price');
                    if (empty($quotePrice)) {
                        throw new AccidentException('The price of the item is incorrect', Code::OPERATE_FAIL);
                    }

                    //CNY => USD
                    //$quotePrice = (new CurrencyConverter())->reversedCurrenciesExchange($quotePrice);

                    $lineItems[] = [
                        'name' => $item['goods_name'],
                        'title' => $item['goods_name'],
                        'quantity' => $sku['carts_num'],
                        'price' => $quotePrice,
                        'product_id' => $sku['goods_id'],
                        'sku' => $sku['sku_id'],
                        'total_discount' => $sku['carts_num'] * $quotePrice,
                        'variant_id' => $sku['id'],
                        'variant_title' => $sku['spec_name'],
                        'line_item_id' => $sku['sku_id'],
                        'imgs' => empty($sku['images']) ? [] : json_encode($sku['images'])
                    ];

                    $goodsPrice += $sku['carts_num'] * $quotePrice;
                }
            }

            //校验仓库ID
            $warehouseId = $this->formData['carts']['warehouse_id'];
            WarehouseAddress::query()->findOrFail($warehouseId);

            //sku状态：0-单sku单数 1-单sku多数 2-多sku
            if (count($lineItems) > 1) {
                $skuStatus = 2;
            } else {
                $skuStatus = $lineItems[0]['quantity'] > 1 ? 1 : 0;
            }

            $orderId = generateStockOrderId();

            $orderData = [
                'customer_id' => getCustomId(),
                'order_id' => $orderId,
                'platform' => Platform::LOCAL,//平台
                'currency' => 'USD',//币种
                'current_subtotal_price' => $goodsPrice,//税前金额
                'current_total_price' => $goodsPrice,//合计金额
                'subtotal_price' => $goodsPrice,//小计
                'vendor_price' => $goodsPrice,//报价金额
                'order_status' => Order::STATUS_QUOTED,
                'shop_id' => 0,//店铺ID
                'custom_order_id' => $orderId,
                'logistics_fee' => 0,//物流费用
                'express_line_id' => 0,//渠道ID
                'logistics_provider' => 0,//服务商
                'logistics_provider_code' => '',//服务商code
                'payment_info' => [],
                'sku_status' => $skuStatus,
                'order_type' => Order::ORDER_TYPE_STOCK,
                'warehouse_id' => $warehouseId,
            ];
            $order = Order::query()->create($orderData);

            foreach ($lineItems as &$line) {
                $line['order_id'] = $order->id;
            }
            OrderLineItem::query()->insert($lineItems);

            $lineItems = OrderLineItem::query()->where('order_id', $order->id)->get();

            $lineItems->each(function ($item) {
                $goodsSku = GoodsSku::query()->with('goods')->where('sku_id', $item->sku)->first();

                //关联sku
                $mapping = OrderItemMapping::query()->where('platform_variant_id', $item->variant_id)->where('platform', Platform::LOCAL)->first();
                if (empty($mapping)) {
                    $mapping = new OrderItemMapping();
                }

                $mapping->platform = Platform::LOCAL;
                $mapping->platform_variant_id = $item->variant_id;
                $mapping->goods_sku_id = $goodsSku->id ?? 0;
                $mapping->save();
            });


            $deleteCartIds = array_column($this->formData['products'], 'id');
            $this->model::query()->whereIn('id', $deleteCartIds)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            logger('购物车添加订单失败：',[
                'msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            throw new AccidentException('Order submission failure:' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        $return = [
            'order_id' => $order->id,
            'carts_num' => ShoppingCart::getCartsNum(),
        ];

        return $return;
    }

    public function deletes($params)
    {
        $ids = $params['ids'];

        if (empty($ids)) {
            throw new AccidentException('请选择需要删除的产品', Code::OPERATE_FAIL);
        }

        $skuId = $params['sku_id'] ?? 0;

        $deleteIds = true;
        if ($skuId) {
            $cartData = $this->model::find(current($ids));

            $cartData->skus = array_filter($cartData->skus, static function ($sku) use ($skuId) {
                return $sku['id'] != $skuId;
            });

            if (!empty($cartData->skus)) {
                $deleteIds = false;

                $cartData->skus = array_values($cartData->skus);
                $cartData->save();
            }
        }

        if ($deleteIds) {
            $this->model::whereIn('id', $ids)->delete();
        }

        return ['carts_num' => ShoppingCart::getCartsNum()];
    }

    public function rules()
    {
        return [
            'address.first_name' => 'required',
            'address.last_name' => 'required',
            'address.country' => 'required',
            'address.city' => 'required',
            'address.address_detail' => 'required',
            'address.phone' => 'required',
            'address.zip' => 'required',
        ];
    }

    public function add1688ToGoods($goodsId)
    {
        //1、校验1688商品是否已经添加过
        $goods = Goods::query()->with(['goodsAudit'])->where('spu', $goodsId)->first();

        $auditStatus = $goods->goodsAudit->audit_status ?? '';//审核状态
        if ($auditStatus === GoodsAudit::GOOD_AUDIT_APPROVED) {
            return $goodsId;//审核通过直接返回spu
        }

        /*if ($auditStatus === GoodsAudit::GOOD_AUDIT_WAITING) {
            //审核拒绝
            throw new AccidentException('产品正在审核中，请咨询管理员!', Code::OPERATE_FAIL);
        }*/

        if ($auditStatus === GoodsAudit::GOOD_AUDIT_REJECTED) {
            //审核拒绝
            throw new AccidentException('产品审核不通过，请联系管理员!', Code::OPERATE_FAIL);
        }

        //2、采集1688商品 只获取
        $goodsDetail = (new Y1688Service())->claim($goodsId, false);

        if (empty($goods)) {
            //3、添加审核产品
            $goodsData = $this->transformGoods($goodsDetail);
            //开启产品开发审核流程
            $autoAudit = SystemConfigService::getConfigValue(SystemConfig::OPEN_PRODUCT_DEVELOP_AUDIT);
            if (empty($autoAudit)) {
                SystemConfigService::setConfig(SystemConfig::OPEN_PRODUCT_DEVELOP_AUDIT, 1);
            }

            (new GoodsService())->store($goodsData);
        }

        return $goodsId;

        //该产品首次购买需审核。请稍后，我们正在审核中。
        //throw new AccidentException('该产品首次购买需审核。请稍后，我们正在审核中。', Code::OPERATE_FAIL);
    }

    public function transformGoods($goods): array
    {
        $skuList = [];
        foreach ($goods['sku_list'] as $sku) {
            $skuList[] = [
                'sku_id' => $sku['sku_id'],
                'spec_name' => $sku['spec_name'],
                'sale_price' => $sku['sale_price'],//售价
                'original_price' => $sku['compare_price'],//原价
                'purchase_price' => $sku['sale_price'],//采购价
                'profit' => 0,//利润
                'quote_price' => $sku['sale_price'],//报价
                'images' => $sku['images'],
                'spec_info' => $sku['spec_info'],
                'status' => 1,
                'purchase_spec_id' => $sku['spec_id'],
                'length' => 0,
                'width' => 0,
                'height' => 0,
                'weight' => 0,
            ];
        }
        return [
            'spu' => $goods['goods_id'],
            'goods_name' => $goods['goods_name'],
            'category_id' => 0,
            'cover_image' => $goods['cover_image'],
            'main_images' => $goods['main_images'],
            'detail' => $goods['detail'],
            'options' => $goods['options'],
            'purchase_url' => $goods['collect_url'],
            'purchase_price' => $goods['price'],
            'purchase_platform' => $goods['collect_platform'],
            'purchase_product_id' => $goods['goods_id'],
            'skus' => $skuList,
            'goods_type' => 1,
            'packing_materials_type' => 0,
        ];
    }

}
