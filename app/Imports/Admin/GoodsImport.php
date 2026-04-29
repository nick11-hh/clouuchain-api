<?php

namespace App\Imports\Admin;

use App\Lib\Code;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderShippingAddress;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use App\Models\Goods;
use App\Models\GoodsCategory;
use App\Models\GoodsSku;
use App\Models\GoodsAudit;
use App\Models\CustomsQuoteConfig;
use App\Models\SystemConfig;
use App\Services\Admin\GoodsService;
use App\Services\Base\SystemConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Validators\Failure;
use App\Jobs\PushGoodsToMabangJob;
use App\Exceptions\AccidentException;
use App\Helper\CurrencyConverter;

/**
 * 产品导入处理
 * Class GoodsImport
 * @package App\Imports\Admin
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/12/14 16:38
 */
class GoodsImport implements ToModel, WithValidation, SkipsOnFailure, WithStartRow, WithMapping, WithEvents
{
    use Importable, SkipsFailures;

    protected int $maxRows = 10000;
    protected int $rowCounter = 1;
    protected int $calculateMethod = 0;
    protected int $profitRate = 0;
    protected int $fixedAmount = 0;

    /**
     * @param int $maxRows
     */
    public function __construct(int $maxRows = 0)
    {
        if ($maxRows > 0) {
            $this->maxRows = $maxRows;
        }

        $this->getProductQuoteConfig();
    }

    /**
     * 主方法
     * @param array $row
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/14 16:38
     */
    public function model(array $row)
    {
        $this->rowCounter++;
        info('开始处理第'. $this->rowCounter .'行数据', $row);

        if (empty(array_filter($row))) {
            info('空行数据，自动跳过');
            return null;
        }

        Validator::make($row, $this->getRules())->validate();

        list($categoryData, $spuData, $skuData) = $this->handleRowData($row);

        if ($spuData['goods_type'] == Goods::GOODS_TYPE_PRODUCT) {
            //添加分类数据
            $category = GoodsCategory::query()->where('name', $categoryData['name'])->first();
            if (empty($category)) {
                $categoryInsertData = GoodsCategory::init($categoryData);
                $category = GoodsCategory::query()->create($categoryInsertData);
            } else {
                $category->name = $categoryData['name'];
            }

            $spuData['category_id'] = $category->id;
        }


        $spuData['source_type'] = Goods::GOODS_SOURCE_TYPE_IMPORT;

        //添加商品以及SKU数据
        $goods = Goods::query()->where(['goods_name' => $spuData['goods_name'], 'goods_type' => $spuData['goods_type']])->first();
        $goodsService = new GoodsService();

        if (empty($goods)) {
            $goodsInsertData = Goods::init($spuData);

            $goods = Goods::query()->create($goodsInsertData);

            $minPrice = 0;
            if ($minPrice == 0 || $minPrice > $skuData['sale_price']) $minPrice = $skuData['sale_price'];

            $skuData['goods_id'] = $goods->id;
            $skuData = GoodsSku::init($skuData, goodsData: $spuData);
            $goodsSku = GoodsSku::query()->create($skuData);

            //更新sku报关信息、采购信息
            $goodsService->updateSkuExtend($goods->id, $goodsSku->id, $skuData);

            $goods->goods_lowest_price = $minPrice;
            $goods->save();

            $goodAudit = GoodsAudit::init($goods->id, 1);
            GoodsAudit::create($goodAudit);

            $model = $goodsSku->goods->fresh();

            //这里推送给马帮
            PushGoodsToMabangJob::dispatch(PushGoodsToMabangJob::TYPE_20, $goodsSku, getAdminId());
        } else {
            $options = $goods->options;
            $updateOptions = $spuData['options'];
            unset($spuData['options']);

            $spuData['options'] = $this->handleOptions($options, $updateOptions);

            $goodsUpdateData = Goods::init($spuData, 2);
            $goods->update($goodsUpdateData);

            $minPrice = 0;
            //$skuIds = [];

            if ($minPrice == 0 || $minPrice > $skuData['sale_price']) $minPrice = $skuData['sale_price'];

            $type = PushGoodsToMabangJob::TYPE_21;
            $skuData['goods_id'] = $goods->id;
            if (!empty($skuData['id'])) {
                $updateSkuData = GoodsSku::init($skuData, 2, $goodsUpdateData);
                $goodsSku = GoodsSku::query()->findOrFail($skuData['id']);

                if($goodsSku->sku_id != $skuData['sku_id']){
                    $type = PushGoodsToMabangJob::TYPE_20;
                }

                $goodsSku->update($updateSkuData );
                //$skuIds[] = $skuData['id'];
            } else {
                $skuData['goods_id'] = $goods->id;
                $skuData = GoodsSku::init($skuData, goodsData: $goodsUpdateData);
                $goodsSku = GoodsSku::query()->create($skuData);
                $type = PushGoodsToMabangJob::TYPE_20;
            }
            //更新sku报关信息、采购信息
            $goodsService->updateSkuExtend($goods->id, $goodsSku->id, $skuData);

            /*// 删除多余产品sku
            $oldSkuIds = $goods->skus->pluck('id')->toArray();
            $deleteIds = array_diff($oldSkuIds, $skuIds);

            GoodsSku::query()->whereIn('id', $deleteIds)->delete();*/
            $goods->goods_lowest_price = $minPrice;

            $goods->save();
            $model = $goods->fresh();

            //这里推送给马帮
            PushGoodsToMabangJob::dispatch($type, $goodsSku, getAdminId());
        }
        $goodsService->updateMaxMinPrice($model);

        info('第'. $this->rowCounter .'行数据处理完成');
    }

    /**
     * 注册事件
     * @return mixed
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/17 11:38
     */
    public function registerEvents(): array
    {
        // TODO: Implement registerEvents() method.
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $highestRow = $event->sheet->getHighestRow();

                //减去标题行的行数为实际数据行
                $dataRow = $highestRow - 1;
                if ($dataRow > $this->maxRows) {
                    throw new AccidentException('批量导入失败，最多支持'. $this->maxRows .'行数据导入,请减少数据行重试', Code::OPERATE_FAIL);
                }
            }
        ];
    }

    /**
     * 开始处理的行数
     * @return int
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/14 16:38
     */
    public function startRow(): int
    {
        return 2;
    }


    /**
     * 循环处理数据
     * @throws \Exception
     */
    public function map($row): array
    {
        if (count($row) < 21) {
            throw new AccidentException('The Excel data missing', Code::OPERATE_FAIL);
        }

        //返回的数据
        return [
            'goods_name'            => trim($row[0]),
            'goods_type'            => trim($row[1]),
            'category_name'         => trim($row[2]),
            'main_images'           => $row[3],
            'unit'                  => trim($row[4] ?: ''),
            'spu_purchase_price'    => $row[5] ?: 0,
            'purchase_url'          => $row[6] ?: '',
            'developer_name'        => trim($row[7]),
            'spec1_name'            => trim($row[8]),
            'spec1_value'           => trim($row[9]),
            'spec2_name'            => trim($row[10] ?? ''),
            'spec2_value'           => trim($row[11] ?? ''),
            'sku_images'            => $row[12],
            'sku_id'                => $row[13],
            // 'quote_price'           => $row[14],
            'purchase_price'        => $row[14],
            'profit_rate'           => $row[15] ?: 0,
            'fixed_amount'          => $row[16] ?: 0,
            'length'                => $row[17] ?: 0,
            'width'                 => $row[18] ?: 0,
            'height'                => $row[19] ?: 0,
            'weight'                => $row[20] ?: 0,
            'self_goods'            => $row[21] ?: 0,
        ];
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * 验证规则
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/14 16:37
     */
    public function getRules(): array
    {
        return [
            'goods_name'            => 'required|string',
            'goods_type'            => 'required|string',
            'category_name'         => 'required|string',
            'main_images'           => 'required|string',
            'unit'                  => 'sometimes|nullable',
            'spu_purchase_price'    => 'sometimes|nullable',
            'purchase_url'          => 'sometimes|nullable',
            'developer_name'        => 'required|string',
            'spec1_name'            => 'required|string',
            'spec1_value'           => 'required|string',
            'spec2_name'            => 'sometimes|nullable|string',
            'spec2_value'           => 'sometimes|nullable|string',
            'sku_images'            => 'required|string',
            'sku_id'                => 'sometimes',
            // 'quote_price'           => 'required|numeric',
            'purchase_price'        => 'required|numeric',
            'profit_rate'           => 'sometimes|nullable|numeric',
            'fixed_amount'          => 'sometimes|nullable|numeric',
            'length'                => 'sometimes|nullable',
            'width'                 => 'sometimes|nullable',
            'height'                => 'sometimes|nullable',
            'weight'                => 'sometimes|nullable',
        ];
    }

    /**
     * 处理商品选项数据
     * @param $options
     * @param $updateOptions
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/14 18:02
     */
    public function handleOptions($options, $updateOptions)
    {
        //创建一个映射来快速查找已存在的规格
        $specMap = [];
        foreach ($options as $optionIndex => $option) {
            foreach ($option['specs'] as $specIndex => $spec) {
                $key = $option['name'] . ':' . $spec['name'];
                $specMap[$key] = ['optionIndex' => $optionIndex, 'specIndex' => $specIndex];
            }
        }

        foreach ($updateOptions as $updateOption) {
            $optionExists = false;

            foreach ($options as $optionIndex => $option) {
                if ($option['name'] == $updateOption['name']) {
                    $optionExists = true;
                    //添加或者更新选项规格
                    foreach ($updateOption['specs'] as $updateSpec) {
                        $key = $option['name'] . ':' . $updateSpec['name'];
                        if (isset($specMap[$key])) {
                            //存在即更新规格名称
                            $options[$optionIndex]['specs'][$specMap[$key]['specIndex']]['name'] = $updateSpec['name'];
                        } else {
                            //不存在添加新的规格
                            $options[$optionIndex]['specs'][] = $updateSpec;

                            $specMap[$key] = ['optionIndex' => $optionIndex, 'specIndex' => count($options[$optionIndex]['specs']) - 1];
                        }
                    }

                    //已找到对应选项，跳出循环
                    break;
                }
            }

            //如果整个选项不存在，则整个更新
            /*if (!$optionExists) {
                $options[] = $updateOption;
            }*/
        }

        return $options;
    }

    /**
     * 处理数据集
     * @param array $row
     * @return array[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/14 16:37
     */
    public function handleRowData(array $row)
    {
        //分类数据
        $categoryData = [
            'name' => $row['category_name'],
        ];

        if (!in_array($row['goods_type'], array_values(Goods::goodsTypeList()))) {
            throw new AccidentException('产品类型只能填写产品或包材，请重新填写', Code::OPERATE_FAIL);
        }
        $goodsType = trim($row['goods_type']) == '产品' ? Goods::GOODS_TYPE_PRODUCT : Goods::GOODS_TYPE_PACKING_MATERIALS;

        $packingMaterialsTypeList = Goods::packingMaterialsTypeList();
        $packingMaterialsType = 0;

        if ($goodsType == Goods::GOODS_TYPE_PACKING_MATERIALS) {
            $packingMaterialsTypeIds = array_keys($packingMaterialsTypeList);
            $packingMaterialsTypeName = array_values($packingMaterialsTypeList);

            $resultKey = array_search($row['category_name'], $packingMaterialsTypeName);
            if ($resultKey !== false) {
                $packingMaterialsType = $packingMaterialsTypeIds[$resultKey];
            } else {
                throw new AccidentException('包材类型不正确，请重新填写', Code::OPERATE_FAIL);
            }
        }

        $developerId = Admin::query()->where('username', $row['developer_name'])->value('id');
        if (empty($developerId)) {
            throw new AccidentException('请填写正确的开发人员名称,错误名称：'. $row['developer_name'], Code::OPERATE_FAIL);
        }

        // 自动生成SKU逻辑，减少数据库查询次数
        if (empty($row['sku_id'])) {
            // 使用时间戳和随机数组合生成初始SKU
            $timestamp = date('YmdHis');
            $microtime = substr(microtime(), 2, 6);
            $random = mt_rand(100, 999);
            
            $row['sku_id'] = 'SPU' . $timestamp . $microtime . $random;
        }

        //spu数据
        $spuData = [
            'goods_name'                => $row['goods_name'],
            'main_images'               => str_contains($row['main_images'], "\n") ? explode("\n", $row['main_images']) : [$row['main_images']],
            'purchase_price'            => $row['purchase_price'],
            'purchase_url'              => $row['purchase_url'],
            'props'                     => $row['props'] ?? [],
            'unit'                      => $row['unit'],
            'detail'                    => $row['detail'] ?? '',
            'developer_id'              => $developerId,
            'goods_type'                => $goodsType,
            'main_video'                => $row['main_video'] ?? [],
            'packing_materials_type'    => $packingMaterialsType,
            'self_goods'                => $row['self_goods'] ?? 0,
        ];

        foreach ($spuData['main_images'] as $key => $spuImage) {
            if (empty($spuImage)) {
                unset($spuData['main_images'][$key]);
            }
        }
        $spuData['cover_image'] = $spuData['main_images'][0];

        //sku数据
        $specInfo = [
            [
                'name' => $row['spec1_name'],
                'value' => $row['spec1_value'],
            ],
        ];
        $specName = $row['spec1_value'];

        $row['quote_price'] = $row['quote_price_rmb'] = $row['profit'] = 0;

        $currencyConverter = new CurrencyConverter();

        if($this->calculateMethod == CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_1){
            #按百分比计算：采购成本 / (1 - 利润率) / 汇率

            $productProfitRate = ($row['profit_rate'] > 0) ? $row['profit_rate'] : $this->profitRate;

            $row['quote_price_rmb'] = $row['purchase_price'] / (1 - (float) $productProfitRate / 100);

            //人民币报价转换成美元
            $row['quote_price'] = $currencyConverter->reversedCurrenciesExchange($row['quote_price_rmb']);

            //利润计算规则：应该是采购成本/（1-利润%）*利润%=12/(1-10%）*10%=1.33
            $row['profit'] = (float) $row['purchase_price'] / (1 - (float) $productProfitRate / 100) * ((float) $productProfitRate / 100);
        }

        if($this->calculateMethod == CustomsQuoteConfig::PRODUCT_QUOTE_CALCULATE_METHOD_2){
            #按固定金额计算：(物流成本 + 固定金额(人民币)) / 汇率

            $productFixedAmount = ($row['fixed_amount'] > 0) ? $row['fixed_amount'] : $this->fixedAmount;

            $row['quote_price_rmb'] = (float) $row['purchase_price'] + (float) $productFixedAmount;

            //人民币报价转换成美元
            $row['quote_price'] = $currencyConverter->reversedCurrenciesExchange($row['quote_price_rmb']);

            $row['profit'] = $row['quote_price_rmb'] - $row['purchase_price'];
        }


        $skuData = [
            'id'                => GoodsSku::query()->where('sku_id', $row['sku_id'])->value('id') ?: 0,
            'sku_id'            => $row['sku_id'],
            'spec_name'         => $specName,
            'spec_info'         => $specInfo,
            'sku_images'        => str_contains($row['sku_images'], "\n") ? explode("\n", $row['sku_images']) : [$row['sku_images']],
            'original_price'    => $row['original_price'] ?? 0,
            'quote_price'       => $row['quote_price'],
            'purchase_price'    => $row['purchase_price'],
            'sale_price'        => $row['quote_price'],
            'profit'            => max($row['profit'], 0),
            'length'            => $row['length'],
            'width'             => $row['width'],
            'height'            => $row['height'],
            'weight'            => $row['weight'],
        ];

        foreach ($skuData['sku_images'] as $key => $skuImage) {
            if (empty($skuImage)) {
                unset($skuData['sku_images'][$key]);
            }
        }

        //spu 选项数据
        $spuData['options'] = [
            [
                'name' => $row['spec1_name'],
                'specs' => [
                    [
                        'old' => false,
                        'name' => $row['spec1_value'],
                        'image' => $skuData['sku_images'],
                    ]
                ],
            ],
        ];

        if ($row['spec2_name'] && $row['spec2_value']) {
            $skuData['spec_info'][1] = [
                'name' => $row['spec2_name'],
                'value' => $row['spec2_value'],
            ];
            $skuData['spec_name'] = $row['spec1_value'] . '/' . $row['spec2_value'];

            $spuData['options'][1] = [
                'name' => $row['spec2_name'],
                'specs' => [
                    [
                        'old' => false,
                        'name' => $row['spec2_value'],
                        'image' => $skuData['sku_images'],
                    ]
                ],
            ];
        }

        return [
            $categoryData,
            $spuData,
            $skuData,
        ];
    }

    protected function getProductQuoteConfig()
    {
        //找出基础配置里的商品报价默认配置
        $keyList = [
            SystemConfig::PRODUCT_QUOTE_DEFAULT_PROFIT_RATE,
            SystemConfig::PRODUCT_QUOTE_DEFAULT_FIXED_AMOUNT,
            SystemConfig::PRODUCT_QUOTE_CALCULATE_METHOD,
        ];

        $systemConfig = SystemConfigService::getMultipleConfig($keyList);

        $this->calculateMethod = $systemConfig['product_quote_calculate_method'];
        $this->profitRate = $systemConfig['product_quote_default_profit_rate'] ?: 0;
        $this->fixedAmount = $systemConfig['product_quote_default_fixed_amount'] ?: 0;
    }

}
