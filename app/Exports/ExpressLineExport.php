<?php

namespace App\Exports;

use App\Services\Admin\LocalizationService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ExpressLineExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    use Exportable;

    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $header = [
            '线路名称',
            '支持国家',
            '支持仓库',
            '参考时效',
            '首重 ($weight)',
            '首费 ($currency)',
            '续重 ($weight)',
            '续费 ($currency)',
            '最小重量 ($weight)',
            '最大重量 ($weight)',
        ];

        $localization = LocalizationService::getCurrentLocalization();

        return array_map(function ($value) use ($localization) {
            $value = str_replace('$weight', $localization['weight_unit'], $value);

            // $value = str_replace('$currency', $localization['currency_unit'], $value);
            // return $value;
            return str_replace('$currency', $localization['currency_unit'], $value);
        }, $header);
    }

    /**
     * @param  mixed  $package
     *
     * @return array
     */
    public function map($package): array
    {
        return [
            $package->name,
            $package->countries->pluck('name')->flatten()->join(','),
            $package->warehouses->pluck('warehouse_name')->flatten()->join(','),
            $package->reference_time,
            $package->first_weight / 1000,
            $package->first_money / 100,
            $package->next_weight / 1000,
            $package->next_money / 100,
            $package->min_weight / 1000,
            $package->max_weight / 1000,
        ];
    }
}
