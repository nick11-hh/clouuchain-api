<?php

namespace App\Jobs;

use App\Models\ExpressLinePrice;
use App\Models\ExpressLineRegion;
use App\Services\Admin\ExpressLinePriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportExpressLinePrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(protected Collection $items, protected Collection $regions)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $regions = $this->regions;
        $items = $this->items;
        $service = new ExpressLinePriceService();

        DB::beginTransaction();
        try {
            foreach ($items as $datum) {
                [$start, $end, $uw, $type] = $service->parseRange($datum);

                $price = $datum->skip(4)->values();
                // 首重价格 单位价格
                if ($start === $end && !$uw) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        $region->prices()
                            ->where('start', $start * 1000)
                            ->where('end', $end * 1000)
                            ->whereIn('type', [ExpressLinePrice::TYPE_FIRST_WEIGHT, ExpressLinePrice::TYPE_UNIT_WEIGHT])
                            ->update(['price' => $price[$count] * 100]);
                    }
                    // 续重价格 和阶梯范围首重 续重价格
                } elseif ($start < $end && ($uw || $type == ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT)) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        // 阶梯范围首重
                        if ($type === ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT) {
                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('first_weight', ($uw ?: 0) * 1000)
                                ->where('type', ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT)
                                ->update(['price' => $price[$count] * 100]);
                        } else {
                            // 普通续重 阶梯范围续重
                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('unit_weight', $uw * 1000)
                                ->whereIn('type', [
                                    ExpressLinePrice::TYPE_NEXT_WEIGHT,
                                    ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT,
                                ])
                                ->update(['price' => $price[$count] * 100]);
                        }
                    }
                    //阶梯价格
                } elseif ($start < $end && !$uw && !$type) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        $region->prices()
                            ->where('start', $start * 1000)
                            ->where('end', $end * 1000)
                            ->whereIn('type', [
                                    ExpressLinePrice::TYPE_GRADE_WEIGHT,
                                    ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND,
                                ]
                            )
                            ->update(['price' => $price[$count] * 100]);
                    }
                    // 阶梯价格的基础价格 和 单价
                } elseif ($start < $end && !$uw && $type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        // 每两个价格为一组价格 基础价格在前 单位价格在后
                        if ($count % 2 == 0) {
                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('type', ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE)
                                ->update(['price' => $price[$count] * 100]);

                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('type', ExpressLinePrice::TYPE_GRADE_WEIGHT)
                                ->update(['price' => $price[$count + 1] * 100]);
                        }
                    }
                    // 多级续重
                } elseif (!$start && !$end && $uw) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        $region->prices()
                            ->where('unit_weight', $uw * 1000)
                            ->where('type', ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT)
                            ->update(['price' => $price[$count] * 100]);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();

            info('价格表导入失败', ['message' => $throwable->getMessage()]);

            return;
        }

        DB::commit();
    }
}
