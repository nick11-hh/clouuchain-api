<?php

namespace App\Jobs;

use App\Lib\Code;
use App\Models\ThirdPartyWarehouseConfig;
use App\Models\OrderThirdPartyFulfillmentLogs;
use App\Models\GoodsSku;
use App\Models\GoodsCategory;
use App\Models\WarehouseAddress;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class PushGoodsToMabangJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const TYPE_10 = 10;//添加产品
    const TYPE_11 = 11;//手动推送
    const TYPE_12 = 12;//更新产品-新增
    const TYPE_13 = 13;//更新产品-修改
    const TYPE_14 = 14;//产品作废
    const TYPE_20 = 20;//批量导入产品-新增
    const TYPE_21 = 21;//批量导入产品-修改
    const TYPE_30 = 30;//批量更新报关信息

    protected int $type;
    protected GoodsSku $goodsSku;
    protected int $adminId;
    protected string $opLogPrefix;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $type, GoodsSku $goodsSku, int $adminId)
    {

        $this->type = $type;
        $this->goodsSku = $goodsSku->loadMissing(['goods.developer', 'goodsSuppliers.supplier:id,supplier_name,contact_address', 'logistics', 'purchaser']);
        // $this->goodsSku = $goodsSku->withoutRelations();
        $this->adminId = $adminId;

        $this->setType();

        $this->onQueue('push-goods-to-mabang');
    }

    protected function setType()
    {

        match ($this->type) {
            self::TYPE_10 => $this->opLogPrefix = '添加产品',
            self::TYPE_11 => $this->opLogPrefix = '手动推送',
            self::TYPE_12 => $this->opLogPrefix = '更新产品-新增',
            self::TYPE_13 => $this->opLogPrefix = '更新产品-修改',
            self::TYPE_14 => $this->opLogPrefix = '产品作废',
            self::TYPE_20 => $this->opLogPrefix = '批量导入产品-新增',
            self::TYPE_21 => $this->opLogPrefix = '批量导入产品-修改',
            self::TYPE_30 => $this->opLogPrefix = '批量更新报关信息',
            default => '',
        };

    }

    /**
     * Execute the job.
     *
     * @return true
     */
    public function handle()
    {
        //
        if(in_array($this->type, [self::TYPE_10, self::TYPE_11, self::TYPE_12, self::TYPE_20]) && !$this->goodsSku->mabang_stock_id){
            if ($this->goodsSku->is_group) {
                return (new ThirdPartyWarehouseService())->addMabangComboSku($this->goodsSku);
            }
            $this->addGoods();
        }

        if(in_array($this->type, [self::TYPE_11, self::TYPE_13, self::TYPE_14, self::TYPE_21, self::TYPE_30]) && $this->goodsSku->mabang_stock_id){
            if ($this->goodsSku->is_group) {
                return (new ThirdPartyWarehouseService())->updateMabangComboSku($this->goodsSku);
            }
            $this->updateGoods();
        }

        return true;
    }

    protected function makeParams(array $warehouses, bool $isUpdate=false)
    {
        $params = [
            'stockSku' => $this->goodsSku->sku_id,//库存sku
            // 'salesSku' => $this->goodsSku->goods?->spu,//主SKU
            'nameCN' => $this->goodsSku->goods->goods_name_cn  .  ($this->goodsSku->spec_name_cn ?: $this->goodsSku->spec_name),//商品名称，建议中文名称
            'nameEN' =>  $this->goodsSku->goods?->goods_name . '-'.  $this->goodsSku->spec_name,//备用商品名称，建议英文名称
            'status' => 3,//商品状态：1.自动创建 2.待开发 3.正常 4.清仓 5.停止销售
            'picture' => $this->goodsSku->images ? $this->goodsSku->images[0] : '',//商品图片
            'length' => $this->goodsSku->length,//长，最长限制小数点前7位，单位：cm
            'width' => $this->goodsSku->width,//宽，最长限制小数点前7位，单位：cm
            'height' => $this->goodsSku->height,//高，最长限制小数点前7位，单位：cm
            'weight' => $this->goodsSku->weight,//重量，单位：g
            // 'originalSku' => '',//原厂sku
            'declareEname' => $this->goodsSku->logistics?->en_name,//申报英文名称
            'declareName' => $this->goodsSku->logistics?->cn_name,//申报中文名称
            'declareValue' => $this->goodsSku->logistics?->unit_price,//申报价值
            // 'noLiquidCosmetic' => '',//0:非液体,2:液体(化妆品),1:非液体(化妆品),3:液体(非化妆品)
            'purchasePrice' => $this->goodsSku->goods?->purchase_price,//最新采购价
            // 'brandName' => '',//子品牌名称
            // 'hasBattery' => '',//带电池 1.是 2.否
            // 'isTort' => '',//侵权 1.是 2.否
            // 'magnetic' => '',//带磁 1.是 2.否
            // 'powder' => '',//粉末 1.是 2.否
            // 'ispaste' => '',//膏体：1是、2否
            'salePrice' => $this->goodsSku->sale_price,//售价
            // 'defaultCost' => $this->goodsSku->purchase_price,//统一成本价
            'remark' => $this->goodsSku->sku_remark,//商品备注
            'purchaseRemark' => $this->goodsSku->purchase_remark,//采购备注
            'buyerId' => $this->goodsSku->purchaser?->name,//采购员名称
            // 'artDesignerName' => '',//美工名称
            'developerName' => $this->goodsSku->goods?->developer?->name,//开发员名称
            // 'salesName' => '',//销售员名称
            // 'is_flammables' => '',//是否为易燃品 1: 是 2：不是，默认为2
            // 'is_knife' => '',//是否为刀具 1：是 2：不是，默认为2
            'stockDetailImg' => extract_image_links($this->goodsSku->goods?->detail),//产品细节图（最多可张8张链接（多个用逗号隔开），仅支持JPG、JPEG、PNG格式）
        ];

        $categoryLevel = GoodsCategory::getCategoryLevel($this->goodsSku->goods?->category_id);
        if($categoryLevel){

            $params['parentCategoryName'] = $categoryLevel['category_level_1']['name'];//一级目录名称

            if(isset($categoryLevel['category_level_2']['name'])){

                $params['categoryName'] = $categoryLevel['category_level_2']['name'];//二级目录名称
            }

        }

        if($isUpdate){

            // $params['purchaseDays'] = '';//采购天数
        }else{

            if($categoryLevel){

                if(isset($categoryLevel['category_level_3']['name'])){

                    $params['thirdCategoryName'] = $categoryLevel['category_level_3']['name'];//三级目录名称
                }
            }

            $params['declareCode'] = $this->goodsSku->logistics?->code;//报关编码
            // 'parentBrandName' = '';//主品牌名称（子品牌填写时，此为必填项）
        }

        #如果有供应商信息
        if($this->goodsSku->goodsSuppliers->isNotEmpty()){

            if($isUpdate){

                $params['supplierSyn'] = 1;//供应商不存在，是否自动创建供应商，默认2不创建
            }else{

                $params['autoCreateSupplier'] = 1;//供应商不存在，是否自动创建供应商，默认2不创建
            }

            foreach ($this->goodsSku->goodsSuppliers as $key => $goodsSupplier) {

                $params['suppliersData'][] = [
                    'name' => $goodsSupplier->supplier?->supplier_name,//供应商名称,必填
                    'productLinkAddress' => $goodsSupplier->purchase_url,//商品地址
                    'flag' => $goodsSupplier?->is_default ? 1 : 2, //1:默认供应商 2.非默认供应商
                ];
            }


            $params['suppliersData'] = json_encode($params['suppliersData']);
        }

        foreach ($warehouses as $key => $warehouse) {
            $params['warehouseData'][] = [
                'name' => $warehouse['warehouse_name']
            ];
        }

        $params['warehouseData'] = json_encode($params['warehouseData']);

        return $params;
    }


    protected function addGoods()
    {
        if($this->goodsSku){

            try {

                $service = new ThirdPartyWarehouseService();

                if(!$service->config->push_product){
                    throw new AccidentException('马帮ERP配置推送产品未开启', Code::OPERATE_FAIL);
                }

                #找到关联仓库
                $warehouses = WarehouseAddress::whereIn('id', $service->config->relation_warehouse_id??[])->get();
                if($warehouses->isEmpty()){
                    throw new AccidentException('马帮ERP配置未关联仓库', Code::OPERATE_FAIL);
                }

                $params = $this->makeParams($warehouses->toArray());

                Log::channel('mabang')->info($this->opLogPrefix . '新增库存sku参数', ['params' => $params]);

                $result = $service->addMabangStock($params);

                Log::channel('mabang')->info($this->opLogPrefix . '新增库存sku返回', ['result' => $result]);

                if($result['code'] == 200){
                    #推送成功

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS,
                        'content' => $this->opLogPrefix . '调用马帮ERP 新增库存sku API成功。',
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);

                    #保存马帮ERP商品编号
                    GoodsSku::where('id', $this->goodsSku->id)
                        ->where('sku_id', $result['data']['stockSku'])
                        ->update([
                            'mabang_stock_id' => $result['data']['stockId']
                        ]);

//                    $url = $this->goodsSku->goods?->purchase_url;
//
//                    #如果有产品链接就绑定第三方商品链接
//                    if($url){
//
//                        $linkParams = [
//                            'stockSku' => $this->goodsSku->sku_id,//库存sku
//                            'url' => $url,//第三方商品链接
//                            'type' => 1,//平台类型 1：1688
//                        ];
//
//                        Log::channel('mabang')->info($this->opLogPrefix . '绑定第三方商品链接参数', ['linkParams' => $linkParams]);
//
//                        $result2 = $service->skuLinkBindMabangStock($linkParams);
//
//                        Log::channel('mabang')->info($this->opLogPrefix . '绑定第三方商品链接返回', ['result' => $result2]);
//
//                        if($result2['code'] == 200){
//                            #成功
//
//                            #保存推送日志
//                            OrderThirdPartyFulfillmentLogs::query()->create([
//                                'order_id' => $this->goodsSku->sku_id,
//                                'platform_order_no' => $this->goodsSku->goods?->spu,
//                                'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
//                                'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
//                                'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS,
//                                'content' => $this->opLogPrefix . '调用马帮ERP 绑定第三方商品链接 API成功。',
//                                'operate_id' => $this->adminId,
//                                'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
//                            ]);
//
//                        }else{
//                            #失败
//
//                            #保存推送日志
//                            OrderThirdPartyFulfillmentLogs::query()->create([
//                                'order_id' => $this->goodsSku->sku_id,
//                                'platform_order_no' => $this->goodsSku->goods?->spu,
//                                'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
//                                'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
//                                'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
//                                'content' => $this->opLogPrefix . '调用马帮ERP 绑定第三方商品链接 API失败：' . ($result2['message']??'商品链接绑定马帮库存sku API 未返回错误提示'),
//                                'operate_id' => $this->adminId,
//                                'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
//                            ]);
//                        }
//                    }

                }else{
                    #没有推送成功

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
                        'content' => $this->opLogPrefix . '调用马帮ERP 新增库存sku API失败：' . ($result['message']??'新增库存sku API 未返回错误提示'),
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);

                }

            } catch (\Exception $e) {

                Log::channel('mabang')
                    ->error($this->opLogPrefix . '新增库存sku错误信息', [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'msg' => $e->getMessage()
                    ]);

                #保存推送日志
                OrderThirdPartyFulfillmentLogs::query()->create([
                    'order_id' => $this->goodsSku->sku_id,
                    'platform_order_no' => $this->goodsSku->goods?->spu,
                    'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . ($this->goodsSku->spec_name_cn ?: $this->goodsSku->spec_name),
                    'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                    'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
                    'content' => $this->opLogPrefix . '推送到马帮ERP失败：' . $e->getMessage(),
                    'operate_id' => $this->adminId,
                    'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                ]);
            }
        }
    }


    public function updateGoods()
    {

        if(in_array($this->type, [self::TYPE_14])){
            #如果是作废

            try {

                $params = [
                    'stockSku' => $this->goodsSku->sku_id,//库存sku
                    'status' => 5,//商品状态：1.自动创建 2.待开发 3.正常 4.清仓 5.停止销售
                ];

                Log::channel('mabang')->info($this->opLogPrefix . '作废库存sku参数', ['params' => $params]);

                $service = new ThirdPartyWarehouseService();

                if(!$service->config->product_update_sync){
                    throw new AccidentException('马帮ERP配置产品变更同步未开启', Code::OPERATE_FAIL);
                }

                $result = $service->updateMabangStock($params);

                Log::channel('mabang')->info($this->opLogPrefix . '作废库存sku返回', ['result' => $result]);
                if($result['code'] == 200){

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS,
                        'content' => $this->opLogPrefix . '调用马帮ERP 修改库存sku API成功。',
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);

                }else{

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
                        'content' => $this->opLogPrefix . '调用马帮ERP 修改库存sku API失败：' . ($result['message']??'修改库存sku API 未返回错误提示'),
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);
                }

            } catch (\Exception $e) {

                Log::channel('mabang')
                    ->error($this->opLogPrefix . '作废库存sku错误信息', [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'msg' => $e->getMessage()
                    ]);

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
                        'content' => $this->opLogPrefix . '调用马帮ERP API失败：' . $e->getMessage(),
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);
            }
        }

        if(in_array($this->type, [self::TYPE_13, self::TYPE_21, self::TYPE_30])){

            try {

                $service = new ThirdPartyWarehouseService();

                if(!$service->config->product_update_sync){
                    throw new AccidentException('马帮ERP配置产品变更同步未开启', Code::OPERATE_FAIL);
                }

                #找到关联仓库
                $warehouses = WarehouseAddress::whereIn('id', $service->config->relation_warehouse_id??[])->get();
                if($warehouses->isEmpty()){
                    throw new AccidentException('马帮ERP配置未关联仓库', Code::OPERATE_FAIL);
                }

                $params = $this->makeParams($warehouses->toArray(), true);

                Log::channel('mabang')->info($this->opLogPrefix . '修改库存sku参数', ['params' => $params]);

                $result = $service->updateMabangStock($params);

                Log::channel('mabang')->info($this->opLogPrefix . '修改库存sku返回', ['result' => $result]);

                if($result['code'] == 200){
                    #推送成功

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS,
                        'content' => $this->opLogPrefix . '调用马帮ERP 修改库存sku API成功。',
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);

                    GoodsSku::where('id', $this->goodsSku->id)
                        ->update([
                            'mabang_stock_id' => $result['data']['stockId']
                        ]);

                    $url = $this->goodsSku->goods?->purchase_url;

                    Log::channel('mabang')->info($this->opLogPrefix . '商品链接已更新', [
                        'is_update' => $this->goodsSku->goods->isDirty('purchase_url'),
                        'stockSku' => $this->goodsSku->sku_id,
                    ]);

                    #如果有产品链接并且被更新过，就绑定第三方商品链接
//                    if($url && $this->goodsSku->goods->isDirty('purchase_url')){
//
//                        $linkParams = [
//                            'stockSku' => $this->goodsSku->sku_id,//库存sku
//                            'url' => $url,//第三方商品链接
//                            'type' => 1,//平台类型 1：1688
//                        ];
//
//                        Log::channel('mabang')->info($this->opLogPrefix . '绑定第三方商品链接参数', ['linkParams' => $linkParams]);
//
//                        $result2 = $service->skuLinkBindMabangStock($linkParams);
//
//                        Log::channel('mabang')->info($this->opLogPrefix . '绑定第三方商品链接返回', ['result' => $result2]);
//
//                        if($result2['code'] == 200){
//                            #成功
//
//                            #保存推送日志
//                            OrderThirdPartyFulfillmentLogs::query()->create([
//                                'order_id' => $this->goodsSku->sku_id,
//                                'platform_order_no' => $this->goodsSku->goods?->spu,
//                                'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
//                                'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
//                                'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS,
//                                'content' => $this->opLogPrefix . '调用马帮ERP 绑定第三方商品链接 API成功。',
//                                'operate_id' => $this->adminId,
//                                'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
//                            ]);
//
//                        }else{
//                            #失败
//
//                            #保存推送日志
//                            OrderThirdPartyFulfillmentLogs::query()->create([
//                                'order_id' => $this->goodsSku->sku_id,
//                                'platform_order_no' => $this->goodsSku->goods?->spu,
//                                'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
//                                'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
//                                'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
//                                'content' => $this->opLogPrefix . '调用马帮ERP 绑定第三方商品链接 API失败：' . ($result2['message']??'绑定第三方商品链接 API 未返回错误提示'),
//                                'operate_id' => $this->adminId,
//                                'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
//                            ]);
//                        }
//                    }

                }else{
                    #没有推送成功

                    if($result['message'] == '库存sku[' . $this->goodsSku->sku_id . ']不存在'){
                        return $this->addGoods();
                    }

                    #保存推送日志
                    OrderThirdPartyFulfillmentLogs::query()->create([
                        'order_id' => $this->goodsSku->sku_id,
                        'platform_order_no' => $this->goodsSku->goods?->spu,
                        'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                        'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                        'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
                        'content' => $this->opLogPrefix . '调用马帮ERP 修改库存sku API失败：' . ($result['message']??'修改库存sku API 未返回错误提示'),
                        'operate_id' => $this->adminId,
                        'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                    ]);

                }

            } catch (\Exception $e) {

                Log::channel('mabang')
                    ->error($this->opLogPrefix . '修改库存sku错误信息', [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'msg' => $e->getMessage()
                    ]);

                #保存推送日志
                OrderThirdPartyFulfillmentLogs::query()->create([
                    'order_id' => $this->goodsSku->sku_id,
                    'platform_order_no' => $this->goodsSku->goods?->spu,
                    'order_info' => '产品SKU：' . $this->goodsSku->sku_id . '，规格：' . $this->goodsSku->spec_name,
                    'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
                    'status' => OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR,
                    'content' => $this->opLogPrefix . '调用马帮ERP API失败：' . $e->getMessage(),
                    'operate_id' => $this->adminId,
                    'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
                ]);
            }
        }
    }
}
