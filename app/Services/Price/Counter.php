<?php

namespace App\Services\Price;

use App\Lib\Code;
use App\Models\ExpressLineModel;
use App\Models\ExpressLinePrice;
use App\Models\ExpressLineRegion;
use App\Models\MemberLevel;
use App\Models\Order;
use App\Models\SalePrice;
use App\Models\User;
use App\Models\UserGroup;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AccidentException;

class Counter
{
    protected ?User $user = null;

    protected ExpressLineModel $expressLine;

    protected string $discount = '1';

    protected int $type = 1;

    /**
     * 计算原始的计费运费
     *
     * @param ExpressLineRegion $region
     * @param int $countWeight
     * @param bool $split
     * @param bool $noException
     * @param bool $isBoxes
     * @param User|null $user
     * @param bool $origin
     * @return array|float|int|null
     * @throws Exception
     */
    public static function getOriginFreightFee(
        ExpressLineRegion $region,
        int               $countWeight,
        bool              $split = false,
        bool              $noException = false,
        bool              $isBoxes = false,
        ?User             $user = null,
        bool              $origin = false
    )
    {
        return self::getFreightFee($region, $countWeight, $split, $noException, $isBoxes, $user, true);
    }


    /**
     * 根据重量计算预计成本运费
     *
     * @param ExpressLineRegion $region
     * @param int $countWeight
     * @param bool $split
     * @param bool $noException
     * @param bool $isBoxes
     * @param User|null $user
     * @param bool $origin
     * @return int|array
     * @throws Exception
     */
    public static function getFreightFee(
        ExpressLineRegion $region,
        int               $countWeight,
        bool              $split = false,
        bool              $noException = false,
        bool              $isBoxes = false,
        ?User             $user = null,
        bool              $origin = false
    )
    {
        $expressLine = $region->expressLine;
        $prices = $region->prices()->get();

        if ($prices->isEmpty()) {
            throw new AccidentException('当前尚未配置价格表信息', Code::OPERATE_FAIL);
        }

        if ($isBoxes) {
            //多箱计费 计费重量上浮
            if ($expressLine->multi_boxes_ceil) {
                $countWeight = _ceilTo($countWeight, $expressLine->multi_boxes_ceil);
            }
        } else {
            //计费重量上浮
            if ($expressLine->weight_rise) {
                $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
            }
        }

        //忽略最小重量时重量上浮为最小重量
        if ($countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
            $countWeight = $expressLine->min_weight;
        }

        $range = $expressLine->range;
        $mode = $expressLine->mode;
        $minimumChargeableWeight = $region->minimum_chargeable_weight ?? 0;

        //分区最低计费重
        if ($countWeight < $minimumChargeableWeight) {
            $countWeight = $minimumChargeableWeight;
        }
        //获取分区重量区间，根据重量区间获取进位制，并重新计算重量
        $grades = $region->priceRules()->get();

        $scaleWeight = $grades->filter(function ($grade) use ($countWeight, $range, $mode) {
            if ($range && in_array($mode, [ExpressLineModel::MODE_2, ExpressLineModel::MODE_RANGE_FIRST_NEXT], true)) {
                return $countWeight > $grade->start && $countWeight <= $grade->end;
            }

            return $countWeight >= $grade->start && $countWeight < $grade->end;
        })->value('scale_weight');

        if (!empty($scaleWeight)) {
            //$countWeight = _ceilTo($countWeight, $scaleWeight / 1000);
            $countWeight = max($countWeight, $scaleWeight);
        }

        // 计算原始价格时这两个都设置成1即可
        if ($origin) {
            $pd = 1;
            $fd = 1;
        } else {
            $counter = (new self())
                ->setExpressLine($expressLine)
                ->setUser($user)
                ->getPriceDiscount();

            $pd = $counter->priceDiscount();
            $fd = $counter->feeDiscount();
        }

        $price = [0, 0];

        if ($expressLine->mode === ExpressLineModel::MODE_1) {
            $firstPrice = $prices->firstWhere('type', ExpressLinePrice::TYPE_FIRST_WEIGHT);
            $nextPrices = $prices->filter(fn($p) => $p->type === ExpressLinePrice::TYPE_NEXT_WEIGHT)
                ->sortBy(fn($p) => $p->start)
                ->values();

            $nextWeight = $countWeight - $firstPrice->start;

            $firstMoney = self::price($firstPrice->price, $pd);

            /**
             * 续重模式
             * 设首重为 10 / 2kg 续重为 2 - 5  6 / 0.5kg   5 - 10  4 / 0.5kg
             * 那么一个重量为 5.3kg的订单
             * 计算公式为 10 + (3 / 0.5 * 6) + ceil(0.3 / 0.5) * 4 = 10 + 36 + 4 = 50
             */
            $nextMoney = 0;
            if ($nextWeight > 0) {
                $nextPrices->each(function ($p) use (&$nextMoney, $countWeight, &$nextWeight, $pd) {
                    if ($countWeight > $p->start) {
                        if ($countWeight >= $p->end) {
                            $nextMoney += ceil(ceil(($p->end - $p->start) / $p->unit_weight) * self::price($p->price, $pd));
                            $nextWeight -= $p->end - $p->start;
                        } else {
                            $nextMoney += ceil(ceil($nextWeight / $p->unit_weight) * self::price($p->price, $pd));
                        }
                    }
                });
            }

            $price = [$firstMoney, $nextMoney];
        }

        if ($expressLine->mode === ExpressLineModel::MODE_2) {
            $firstMoney = 0;
            $nextMoney = 0;
            $match = 0;
            $min = $prices->sortBy('start')->first();

            foreach ($prices as $grade) {
                if ($range) {
                    $condition = $countWeight > $grade->start && $countWeight <= $grade->end;
                } else {
                    $condition = $countWeight >= $grade->start && $countWeight < $grade->end;
                }

                if ($condition
                    || ($countWeight == $grade->end && $grade->end === $expressLine->max_weight)
                    || ($countWeight == $grade->start && $grade->start === $min->start)
                ) {
                    if ($grade->type === ExpressLinePrice::TYPE_GRADE_WEIGHT) {
                        $nextMoney = $countWeight * self::price($grade->price, $pd) / 1000;
                    }

                    if ($grade->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE) {
                        $firstMoney = self::price($grade->price, $pd);
                    }

                    $match = 1;
                }
            }

            if (! $match && !$noException) {
                throw new AccidentException('未匹配到当前重量（体积）对应价格档', Code::OPERATE_FAIL);
            }

            $price = [$firstMoney, $nextMoney];
        }

        if ($expressLine->mode === ExpressLineModel::MODE_MIX) {
            $firstPrice = $prices->firstWhere('type', ExpressLinePrice::TYPE_UNIT_WEIGHT);
            $nextPrices = $prices->filter(fn($p) => $p->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND);

            $nextMoney = 0;
            foreach ($nextPrices as $grade) {
                if ($countWeight >= $grade->start && $countWeight < $grade->end
                    || ($countWeight == $grade->end && $grade->end === $expressLine->max_weight)
                ) {
                    $nextMoney = self::price($grade->price, $pd);
                    break;
                }
            }
            //每kg多少錢
            $firstMoney = $countWeight * self::price($firstPrice->price, $pd) / 1000;

            info('单位价格加上附加费用', [$firstMoney, $nextMoney]);

            $price = [$firstMoney, $nextMoney];
        }

        if ($expressLine->mode === ExpressLineModel::MODE_GRADE_NEXT) {
            $firstPrice = $prices->firstWhere('type', ExpressLinePrice::TYPE_FIRST_WEIGHT);
            $nextPrices = $prices->filter(fn($p) => $p->type === ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT)
                ->sortByDesc(fn($p) => $p->unit_weight);

            $nextWeight = $countWeight - $firstPrice->start;

            $firstMoney = self::price($firstPrice->price, $pd);

            $nextMoney = 0;
            if ($nextWeight > 0) {
                //多级续重
                foreach ($nextPrices as $key => $grade) {
                    $t = $nextWeight / $grade->unit_weight;
                    $nextWeight = $nextWeight % $grade->unit_weight;

                    if (isset($nextPrices[$key + 1])) {
                        $t = floor($t);
                    } else {
                        $t = ceil($t);
                    }

                    $nextMoney += $t * self::price($grade->price, $pd);
                    if ($nextWeight === 0) {
                        break;
                    }
                }
            }
            $price = [$firstMoney, $nextMoney];
        }
        // 范围首重续重模式
        // 相较于原来的首重续重 这个模式只是增加了范围这个前置条件
        // 每个不同的范围首重续重地设置可能都是不一样的
        if ($expressLine->mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
            $prices = $prices->sortByDesc(fn ($p) => $p->start)->values();

            $end = $prices->first()->end;

            if ($countWeight === $end) {
                $first = $prices->firstWhere('type', ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT);

                $next = $prices->firstWhere('type', ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT);
            } else {
                $first = $prices->filter(function ($p) use ($countWeight, $range) {
                    if ($range) {
                        $condition = $p->start < $countWeight && $countWeight <= $p->end;
                    } else {
                        $condition = $p->start <= $countWeight && $countWeight < $p->end;
                    }

                    return $condition && $p->type === ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT;
                })->first();

                $next = $prices->filter(function ($p) use ($countWeight, $range) {
                    if ($range) {
                        $condition = $p->start < $countWeight && $countWeight <= $p->end;
                    } else {
                        $condition = $p->start <= $countWeight && $countWeight < $p->end;
                    }

                    return $condition && $p->type === ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT;
                })->first();
            }

            if (! $first || ! $next) {
                $firstMoney = $nextMoney = 0;
            } else {
                $firstWeight = $first->first_weight;
                $firstPrice = $first->price;

                $unitWeight = $next->unit_weight;
                $unitPrice = $next->price;

                $nextWeight = $countWeight - $firstWeight;

                $firstMoney = self::price($firstPrice, $pd);
                $nextMoney = 0;
                try {
                    if ($nextWeight > 0) {
                        $nextMoney += ceil($nextWeight / $unitWeight) * self::price($unitPrice, $pd);
                    }
                } catch (\DivisionByZeroError) {
                    info('价格除以零错误', ['price' => $next->toArray()]);

                    throw new AccidentException('价格设置的单位重量为0', Code::OPERATE_FAIL);
                }
            }

            $price = [$firstMoney, $nextMoney];
        }

        if ($split) {
            return array_map(function ($p) use ($fd) {
                return self::price($p, $fd);
            }, $price);
        } else {
            return self::price(array_sum($price), $fd);
        }
    }

    /**
     * 计算原始的体积计费运费
     *
     * @param ExpressLineRegion $region
     * @param array|int $size
     * @param bool $split
     * @param bool $noException
     * @param bool $isBoxes
     * @param User|null $user
     * @param int $weightVolume
     * @return array|float|int|null
     * @throws Exception
     */
    public static function getOriginVolumeFreightFee(
        ExpressLineRegion $region,
        array|int             $size,
        bool              $split = false,
        bool              $noException = false,
        bool              $isBoxes = false,
        ?User             $user = null,
        int $weightVolume = 0
    )
    {
        return self::getVolumeFreightFee($region, $size, $split, $noException, $isBoxes, $user, true, $weightVolume);
    }

    /**
     * 根据体积计算运费
     *
     * @param ExpressLineRegion $region
     * @param array|int $size
     * @param bool $split
     * @param bool $noException
     * @param bool $isBoxes
     * @param User|null $user
     * @param bool $origin
     * @param int $weightVolume
     * @return float|array
     * @throws Exception
     */
    public static function getVolumeFreightFee(
        ExpressLineRegion $region,
        array|int $size,
        bool $split = false,
        bool $noException = false,
        bool $isBoxes = false,
        ?User $user = null,
        bool $origin = false,
        int $weightVolume = 0
    )
    {
        if (is_array($size)) {
            /** @var int $volume 可以说是立方分米 */
            $volume = array_reduce($size, fn ($a, $b) => $a * $b, 1) / 1000000 / 1000;
        } else {
            $volume = $size;
        }

        $expressLine = $region->expressLine;
        $prices = $region->prices()->get();

        if ($prices->isEmpty()) {
            throw new AccidentException('当前尚未配置价格表信息', Code::OPERATE_FAIL);
        }

        $volume = max($volume, $weightVolume);

        if ($isBoxes || $expressLine->multi_boxes === ExpressLineModel::MULTI_BOX_EACH_NO_CEIL) {
            //多箱计费 计费重量上浮
            if ($expressLine->multi_boxes_ceil) {
                $volume = _ceilTo($volume, $expressLine->multi_boxes_ceil);
            }
        } else {
            //计费重量上浮
            if ($expressLine->weight_rise) {
                $volume = _ceilTo($volume, $expressLine->weight_rise);
            }
        }

        //忽略最小重量时重量上浮为最小重量
        if ($volume < $expressLine->min_weight && $expressLine->ceil_weight) {
            $volume = $expressLine->min_weight;
        }

        $range = $expressLine->range;
        $mode = $expressLine->mode;
        $minimumChargeableWeight = $region->minimum_chargeable_weight ?? 0;

        //分区最低计费重
        if ($volume < $minimumChargeableWeight) {
            $volume = $minimumChargeableWeight;
        }

        //获取分区重量区间，根据重量区间获取进位制，并重新计算重量
        $grades = $region->priceRules()->get();
        $scaleWeight = $grades->filter(function ($grade) use ($volume, $range, $mode) {
            if ($range && in_array($mode, [ExpressLineModel::MODE_2, ExpressLineModel::MODE_RANGE_FIRST_NEXT], true)) {
                return $volume > $grade->start && $volume <= $grade->end;
            }

            return $volume >= $grade->start && $volume < $grade->end;
        })->value('scale_weight');

        if (!empty($scaleWeight)) {
            $volume = _ceilTo($volume, $scaleWeight / 1000);
        }

        // 计算原始价格时这两个都设置成1即可
        if ($origin) {
            $pd = 1;
            $fd = 1;
        } else {
            $counter = (new self())
                ->setExpressLine($expressLine)
                ->setUser($user)
                ->getPriceDiscount();

            $pd = $counter->priceDiscount();
            $fd = $counter->feeDiscount();
        }

        $price = [];
        if ($expressLine->mode === ExpressLineModel::MODE_1) {
            $firstPrice = $prices->firstWhere('type', ExpressLinePrice::TYPE_FIRST_WEIGHT);
            $nextPrices = $prices->filter(fn($p) => $p->type === ExpressLinePrice::TYPE_NEXT_WEIGHT)
                ->sortBy(fn($p) => $p->start)
                ->values();

            $nextVolume = $volume - $firstPrice->start;
            $firstMoney = self::price($firstPrice->price, $pd);

            $nextMoney = 0;
            if ($nextVolume > 0) {
                $nextPrices->each(function ($p) use (&$nextMoney, $volume, &$nextVolume, $pd) {
                    if ($volume > $p->start) {
                        if ($volume >= $p->end) {
                            $nextMoney += ceil(ceil(($p->end - $p->start) / $p->unit_weight) * self::price($p->price, $pd));
                            $nextVolume -= $p->end - $p->start;
                        } else {
                            $nextMoney += ceil(ceil($nextVolume / $p->unit_weight) * self::price($p->price, $pd));
                        }
                    }
                });
            }

            $price = [$firstMoney, $nextMoney];
        }

        if ($expressLine->mode === ExpressLineModel::MODE_2) {
            $nextMoney = 0;
            $firstMoney = 0;
            $match = 0;
            $min = $prices->sortBy('start')->first();

            foreach ($prices as $grade) {
                if ($range) {
                    $condition = $volume > $grade->start && $volume <= $grade->end;
                } else {
                    $condition = $volume >= $grade->start && $volume < $grade->end;
                }

                if ($condition
                    || ($volume == $grade->end && $grade->end === $expressLine->max_weight)
                    || ($volume == $grade->start && $grade->start === $min->start)
                ) {
                    if ($grade->type === ExpressLinePrice::TYPE_GRADE_WEIGHT) {
                        $nextMoney = $volume * self::price($grade->price, $pd) / 1000;
                    }

                    if ($grade->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE) {
                        $firstMoney = self::price($grade->price, $pd);
                    }

                    $match = 1;
                }
            }

            if (! $match && !$noException) {
                throw new AccidentException('未匹配到当前重量（体积）对应价格档', Code::OPERATE_FAIL);
            }

            $price = [$firstMoney, $nextMoney];
        }
        // 范围首重续重模式
        // 相较于原来的首重续重 这个模式只是增加了范围这个前置条件
        // 每个不同的范围首重续重地设置可能都是不一样的
        if ($expressLine->mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
            $prices = $prices->sortByDesc(fn($p) => $p->start)->values();

            $end = $prices->first()->end;

            if ($volume === $end) {
                $first = $prices->firstWhere('type', ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT);

                $next = $prices->firstWhere('type', ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT);
            } else {
                $first = $prices->filter(function ($p) use ($volume, $range) {
                    if ($range) {
                        $condition = $p->start < $volume && $volume <= $p->end;
                    } else {
                        $condition = $p->start <= $volume && $volume < $p->end;
                    }

                    return $condition && $p->type === ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT;
                })->first();

                $next = $prices->filter(function ($p) use ($volume, $range) {
                    if ($range) {
                        $condition = $p->start < $volume && $volume <= $p->end;
                    } else {
                        $condition = $p->start <= $volume && $volume < $p->end;
                    }

                    return $condition && $p->type === ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT;
                })->first();
            }

            if (!$first || !$next) {
                $firstMoney = $nextMoney = 0;
            } else {
                $firstVolume = $first->first_weight;
                $firstPrice = $first->price;

                $unitWeight = $next->unit_weight;
                $unitPrice = $next->price;

                $nextVolume = $volume - $firstVolume;

                $firstMoney = self::price($firstPrice, $pd);
                $nextMoney = 0;
                try {
                    if ($nextVolume > 0) {
                        $nextMoney += ceil($nextVolume / $unitWeight) * self::price($unitPrice, $pd);
                    }
                } catch (\DivisionByZeroError) {
                    info('价格除以零错误', ['price' => $next->toArray()]);

                    throw new AccidentException('价格设置的单位重量为0', Code::OPERATE_FAIL);
                }
            }

            $price = [$firstMoney, $nextMoney];
        }

        if ($split) {
            return array_map(function ($p) use ($fd) {
                return self::price($p, $fd);
            }, $price);
        } else {
            return self::price(array_sum($price), $fd);
        }
    }

    /**
     * @param int|null $price
     * @param string $discount
     * @return int
     */
    public static function price(int|null $price, string $discount)
    {
        if (! $price) {
            return 0;
        }

        if ($discount == 1) {
            return $price;
        }

        return (int) ceil(bcmul($price, $discount, 2));
    }

    /**
     * @return int|string
     */
    public function priceDiscount()
    {
        if ($this->discount == 1 || $this->type === 2) {
            return 1;
        }

        return $this->discount;
    }

    /**
     * @return int|string
     */
    public function feeDiscount()
    {
        if ($this->discount == 1 || $this->type === 1) {
            return 1;
        }

        return $this->discount;
    }

    /**
     * @return Counter
     */
    public function getPriceDiscount()
    {
        $user = $this->user;

        if (! $user) {
            return $this;
        }

        /** @var UserGroup $group */
        $group = $user->load(['group', 'tags'])->group;
        /** @var MemberLevel $memberLevel */
        $memberLevel = $user->member?->level;
        /** @var Collection $tags */
        $tags = $user->tags;

        $prices = SalePrice::query()
            ->where('scope', 0)
            ->where('enabled', 1)
            ->orderBy('index')
            ->usable()
            ->get();

        $prices->add($user->salePrices()
            ->where('enabled', 1)
            ->orderBy('index')
            ->usable()
            ->get());

        $prices->add($group->salePrices()
            ->where('enabled', 1)
            ->orderBy('index')
            ->usable()
            ->get());

        if ($memberLevel) {
            $prices->add($memberLevel->salePrices()
                ->where('enabled', 1)
                ->orderBy('index')
                ->usable()
                ->get());
        }

        if ($tags->isNotEmpty()) {
            $prices->add($tags->map(function ($tag) {
                return $tag->salePrices()
                    ->where('enabled', 1)
                    ->orderBy('index')
                    ->usable()
                    ->get();
            })->flatten()->sortBy('index')->values());
        }

        /** @var SalePrice|null $price */
        $price = $prices->flatten()
            ->values()
            ->filter(fn ($v) => $v)
            ->filter(function ($v) {
                $expIds = $v->expressLines()->select('jiyun_express_line.id')->get()->modelKeys();

                return in_array($this->expressLine->id, $expIds);
            })
            ->sortBy(fn ($v) => $v->index)
            ->first();

        if (! $price) {
            $this->discount = 1;

            Cache::forget("SalePrice-{$user->getKey()}");

            return $this;
        }

        $this->type = $price->discount_type;
        $this->discount = $price->discount;

        Cache::put("SalePrice-{$user->getKey()}", $this->discount, $price->expire_at);

        return $this;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user = null): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param ExpressLineModel $expressLine
     * @return $this
     */
    public function setExpressLine(ExpressLineModel $expressLine): static
    {
        $this->expressLine = $expressLine;

        return $this;
    }

    /**
     * @param Order $order
     * @return $this
     */
    public function setOrder(Order $order): static
    {
        $this->user = $order->load('user')->user;

        return $this;
    }
}
