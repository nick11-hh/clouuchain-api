<?php
namespace App\Exports;

use App\Models\Country;
use App\Models\ExpressLineModel;
use App\Models\ExpressLinePrice;
use App\Models\ExpressLineRegion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpressRegionsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected $params;

    protected $mode;

    protected $lastRow;//列

    protected $column;//总行数
    protected $ruleNum = [];//每个分区的重量区间规则数量

    public function __construct($params)
    {
        $this->params = $params;
        $this->mode = $this->params['mode'] ?? 1;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        $query = ExpressLineRegion::query()->with(['areas', 'country', 'postcodeAreas', 'priceRules', 'prices'])->where('express_line_id', $this->params['express_line_id']);

        $regions = $query->get();

        if ($regions->isEmpty()) {
            return collect();
        }

        $data = [];
        $regions->each(function ($region) use (&$data) {
            //国家名称 默认全国区域
            $countryName = $region->areas->first()->country_name ?? '';
            $countryId = $region->areas->first()->country_id ?? 0;
            $countryCode = Country::query()->find($countryId)->code ?? '';

            //邮编
            $postcodes = '';
            if ($region->type === ExpressLineRegion::TYPE_POSTCODE) {
                $countryName = $region->country->cn_name ?? '';
                $countryCode = $region->country->code ?? '';

                $region->postcodeAreas->map(function ($postcode) use (&$postcodes) {
                    $postcodes .= ($postcode->end ? $postcode->start . '-' . $postcode->end : $postcode->start) . ',';
                });
                $postcodes = trim($postcodes, ',');
            }



            $regionData = [
                'country_name' => $countryName,//国家名称
                'country_code' => $countryCode,//国家名称
                'reference_time' => $region->reference_time ?? '',//参考时效
                'minimum_chargeable_weight' => ($region->minimum_chargeable_weight ?? 0) / 1000,//最低计费重
                'type' => $region->type === ExpressLineRegion::TYPE_AREA ? '全国区域' : $region->name,//区域
                'postcodes' => $postcodes,
            ];

            $sortKey = 5;//起始重量所在的键
            $pricesList = [];

            //首重续重
            if ($this->mode === ExpressLineModel::MODE_1) {
                $region->prices->each(function ($price) use ($region, $regionData, &$pricesList) {
                    $start = $price->start;
                    $end = $price->end;

                    //在价格规则里查询进制
                    $scaleWeight = $region->priceRules->filter(function ($rule) use ($start, $end) {
                        return $rule->start === $start && $rule->end === $end;
                    })->first()->scale_weight ?? 0;

                    $type = '续重';
                    //单价
                    if ($price->type === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                        $start = 0;
                        $type = '首重';
                    }

                    $priceData = [
                        'start' => $start / 1000,//起始重量KG
                        'end' => $end / 1000,//截止重量KG
                        'scale_weight' => $scaleWeight / 1000,//进制
                        'price_type' => $type,//价格类型
                        'unit_weight' => $price->unit_weight / 1000,//单位续重KG
                        'price' => ($price->price ?? 0) / 100,//单价￥
                    ];

                    $pricesList[] = array_values(array_merge($regionData, $priceData));
                });
            }

            //阶梯价格
            if ($this->mode === ExpressLineModel::MODE_2) {
                $unitPricesList = [];
                //先获取单价
                $region->prices->each(function ($price) use ($region, &$unitPricesList) {
                    $start = $price->start;
                    $end = $price->end;

                    //在价格规则里查询进制
                    $scaleWeight = $region->priceRules->filter(function ($rule) use ($start, $end) {
                        return $rule->start === $start && $rule->end === $end;
                    })->first()->scale_weight ?? 0;

                    //单价
                    if ($price->type === ExpressLinePrice::TYPE_GRADE_WEIGHT) {
                        $unitPricesList[] = [
                            'start' => $start / 1000,//起始重量KG
                            'end' => $end / 1000,//截止重量KG
                            'scale_weight' => $scaleWeight / 1000,//进制
                            'price' => ($price->price ?? 0) / 100,//单价￥
                        ];
                    }
                });

                //循环单价, 根据重量区间匹配操作费
                collect($unitPricesList)->each(function ($unitPrices) use ($region, $regionData, &$pricesList) {
                    $basePrice = $region->prices->filter(function ($price) use ($unitPrices) {
                        return $price->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE &&
                            ($price->start / 1000) === ($unitPrices['start'] ?? 0) &&
                            ($price->end / 1000) === ($unitPrices['end'] ?? 0);
                    })->first()->price ?? 0;

                    //操作费
                    $unitPrices['base_price'] = $basePrice / 100;

                    $pricesList[] = array_values(array_merge($regionData, $unitPrices));
                });
            }

            //根据起始重量排序
            $regionData = collect($pricesList)->sortBy($sortKey)->values()->all();

            //合并其他分区的报价
            $data = array_merge($data, $regionData);

            //根据重量规则计算合并单元格的行数 合并的时候减去多的一行
            $this->ruleNum[] = $region->priceRules->count() - 1;
        });

        //最后一列的字母 65为A
        $this->lastRow = chr(count(current($data)) + 64);

        //总行数 +1为累加第一行的标题
        $this->column = count($data) + 1;

        return collect($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $headers = [
            '国家',
            '国家简码',
            '参考时效',
            '最低计费重KG',
            '区域',
            '邮编',
        ];

        $appendHeaders = [];
        //首重续重
        if ($this->mode === ExpressLineModel::MODE_1) {
            $appendHeaders = [
                '起始重量KG',
                '截止重量KG',
                '进位制KG',
                '价格类型',
                '单位续重KG',
                '单价￥',
            ];
        }

        //阶梯价格
        if ($this->mode === ExpressLineModel::MODE_2) {
            $appendHeaders = [
                '起始重量KG',
                '截止重量KG',
                '进位制KG',
                '单价￥',
                '挂号费￥',
            ];
        }

        return array_merge($headers, $appendHeaders);
    }

    /**
     * 表格样式设置
     */
    public function styles(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(20);//设置行高
        $sheet->getStyle("A1:{$this->lastRow}{$this->column}")->getAlignment()->setVertical('center');//垂直居中
        $sheet->getStyle("A1:{$this->lastRow}{$this->column}")->applyFromArray(['alignment' => ['horizontal' => 'center']]);//设置水平居中
        $sheet->getStyle("A1:{$this->lastRow}1")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '279A32']]]);//标题字体设置

        $cell = ['A', 'B', 'C', 'D', 'E'];//需要合并单元格的列
        foreach ($cell as $item) {
            $start = 2;//第二行开始
            foreach ($this->ruleNum as $value) {
                $end = $start + $value;
                $sheet->mergeCells($item . $start . ':' . $item . $end); //合并单元格
                $start = $end + 1;
            }
        }
    }

}
