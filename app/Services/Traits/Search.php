<?php

namespace App\Services\Traits;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait Search
{
    protected static string $prefix;

    public static array $expressSnStarts = ['jd', 'yt', 'sf', 'JD', 'YT', 'SF'];

    //支持 = like in between四种模式查询
    public static function buildQuery(Builder $query, array $conditions)
    {
        foreach ($conditions as $k => $v) {
            $type = '=';
            $value = $v;
            if (is_array($v) && $k !== 'andQuery') {
                [$type, $value] = $v;
            }
            !is_array($value) ? $value = trim($value ?? '') : 1;
            //如果是like搜索，但是值为空，跳过
            if ($type === 'like' && $value === '') {
                continue;
            }
            //in
            if ($type === 'in' && is_array($value)) {
                $query->whereIn($k, $value);
                continue;
            }
            //FIND_IN_SET
            if(($type === 'find') && ($value !== '')) {
                $query->whereRaw("FIND_IN_SET({$value}, `{$k}`)");
                continue;
            }
            //关联表 单表单字段 users:id
            if (strpos($k, ':') && !strpos($k, ',')) {
                $k = explode(':', $k);
                $query->whereHas($k[0], function ($query) use ($k, $value, $type) {
                    if($type === 'like') {
                        $query->where($k[1], 'like', '%'.$value.'%');
                    } else if($type === '=') {
                        $query->where($k[1], $value);
                    }
                });
                continue;
            }
            //如果是between， 按时间过滤
            if ($type === 'between' && is_array($value)) {
                if (empty($value[0]) || empty($value[1])) {
                    continue;
                }
                //关联表
                if (strpos($k, ':')) {
                    $k = explode(':', $k);
                    $query->whereHas($k[0], function ($query) use ($k, $value) {
                        if (strpos($value[0], '-') && strpos($value[1], '-')) {
                            $query->whereBetween($k[1], [
                                Carbon::parse($value[0])->startOfDay(),
                                Carbon::parse($value[1])->endOfDay(),
                            ]);
                        } else {
                            $query->whereBetween($k[1], [$value[0], $value[1]]);
                        }
                    });
                    continue;
                }

                //主表过滤
                if (strpos($value[0], '-') && strpos($value[1], '-')) {
                    $query->whereBetween($k, [
                        Carbon::parse($value[0])->startOfDay(),
                        Carbon::parse($value[1])->endOfDay(),
                    ]);
                } else {
                    $query->whereBetween($k, [$value[0], $value[1]]);
                }
                //如果是多个字段联合搜索
            } elseif (strpos($k, ',')) {
                //对于是用户ID的搜索，优先查询并且只查询用户ID字段
                if (strpos($k, 'user_id') && preg_match('/^[1-9]\d{5}$/', $value)) {
                    // 判断存在这个用户
                    // 并且表里面存在user_id这和字段
                    if (User::query()->whereKey($value)->exists()
                        && Schema::hasColumn($query->getModel()->getTable(), 'user_id')
                    ) {
                        // if ($query->getModel() instanceof Order) {
                        //     $sub = Order::query()->selectRaw('id as sId, parent_id as sPId, user_id as sUid')
                        //         ->whereNotNull('parent_id');
                        //
                        //     $query->where(function ($query) use ($value) {
                        //             $query->where('user_id', '=', $value)
                        //                 ->orWhere('sUid', $value);
                        //         })
                        //         ->leftJoinSub($sub, 'sub', 'sub.sPId', '=', 'jiyun_order.id');
                        // } else {
                        // }
                        $query->where('user_id', '=', $value);
                        continue;
                    }
                }
                //对于是订单号的搜索，优先查询并且只订单号字段
                // if (strpos($k, 'order_sn') && str_starts_with($value, self::$prefix)) {
                //     // 判断存在这个用户
                //     // 并且表里面存在user_id这和字段
                //     if (Schema::hasColumn($query->getModel()->getTable(), 'order_sn')) {
                //         if ($query->getModel() instanceof Order) {
                //             // 不带 - 的肯定只需要查主订单 节约子查询时间
                //             if (strpos($value, '-')) {
                //                 $query->where(function (Builder $query) use ($value) {
                //                     $query->whereHas('subOrders', function ($query) use ($value) {
                //                         $query->where('order_sn', "$value");
                //                     })->orWhere('order_sn', 'like', "$value%");
                //                 });
                //             } else {
                //                 $query->where('order_sn', 'like', "$value%");
                //             }
                //         } else {
                //             $query->where('order_sn', 'like', "$value%");
                //         }
                //         continue;
                //     }
                // }
                //对于包裹单号的搜索，优先查询
                // if (preg_match('/.+-\d+-\d+$/', $value)
                //     && !Str::startsWith($value, self::$expressSnStarts)
                //     && Schema::hasColumn($query->getModel()->getTable(), 'location')
                // ) {
                //     // 优先查询包裹
                //     $query->where('location', 'like', "$value%");
                //
                //     continue;
                // }
                //对于包裹单号的搜索，优先查询
                // if (Str::startsWith($value, self::$expressSnStarts)
                //     || (preg_match('/[\dA-Za-z\-_]{4,}/', $value) && strpos($k, 'express_num'))
                // ) {
                //     // 优先查询包裹
                //     if (Schema::hasColumn($query->getModel()->getTable(), 'express_num')
                //         && $query->getModel() instanceof Package
                //     ) {
                //         $query->where(function ($query) use ($value) {
                //             $rValue = strrev($value);
                //             $query->where('express_num', 'like', "$value%")
                //                 ->orWhere('r_express_num', 'like', "$rValue%");
                //
                //             if (CompanyProp::spuEnabled(self::getCompanyId())) {
                //                 $parent = Package::query()
                //                     ->where('is_spu', 3)
                //                     ->where('r_express_num', 'like', "$rValue%")
                //                     ->first();
                //
                //                 if ($parent) {
                //                     $query->orWhere('express_num', 'like', "$parent->express_num-%");
                //                 }
                //             }
                //         });
                //
                //         continue;
                //     }
                //
                //     if ($query->getModel() instanceof Order) {
                //         $sub = Package::query()
                //             ->selectRaw('id as pId, order_id, express_num, r_express_num');
                //
                //         $query->leftJoinSub($sub, 'sub', 'sub.order_id', '=', 'jiyun_order.id')
                //             ->where(function ($query) use ($value) {
                //                 $query->where('express_num', 'like', "$value%")
                //                     ->orWhere('r_express_num', 'like', strrev("%$value"));
                //             });
                //
                //         continue;
                //     }
                // }
                // 中文搜索，查找包裹全文索引
                // if ($query->getModel() instanceof Package && preg_match('/[\x{4e00}-\x{9fa5}]+/u', $value)) {
                //     $query->whereFullText(['package_name', 'location', 'remark', 'code'], "+$value", ['mode' => 'boolean']);
                //
                //     continue;
                // }

                //形如：packages:name,remark;name,value
                if (strpos($k, ':')) {
                    $k = explode(';', $k);
                    $query->where(function ($query) use ($k, $value) {
                        foreach ($k as $key) {
                            //如果是关联搜索
                            if (strpos($key, ':')) {
                                $kk = explode(':', $key);

                                $query->orWhereHas($kk[0], function ($q) use ($kk, $value) {
                                    $q->where(function ($query) use ($kk, $value) {
                                        foreach (explode(',', $kk[1]) as $item) {
                                            // if (str_starts_with($value, self::$prefix)) {
                                            //     $query->orWhere($item, 'like', "{$value}%");
                                            // } else {
                                                $query->orWhere($item, 'like', "%{$value}%");
                                            // }
                                        }
                                    });
                                });
                            } else {
                                $query->orWhere(function ($query) use ($key, $value) {
                                    foreach (explode(',', $key) as $item) {
                                        // if (str_starts_with($value, self::$prefix)) {
                                        //     $query->orWhere($item, 'like', "{$value}%");
                                        // } else {
                                            $query->orWhere($item, 'like', "%{$value}%");
                                        // }
                                    }
                                });
                            }
                        }
                    });

                    continue;
                }

                $query->where(function ($query) use ($k, $value) {
                    foreach (explode(',', $k) as $item) {
                        // if (str_starts_with($value, self::$prefix)) {
                        //     $query->orWhere($item, 'like', "{$value}%");
                        // } else {
                            $query->orWhere($item, 'like', "%{$value}%");
                        // }
                    }
                });
            } else { //普通类型
                if ($value === '') {
                    continue;
                }

                if ($type === 'like') {
                    // if (str_starts_with($value, self::$prefix)) {
                    //     $query->orWhere($k, 'like', "{$value}%");
                    // } else {
                    // }
                    $query->where($k, 'like', "%{$value}%");
                } else {
                    $query->where($k, $type, $value);
                }
            }
        }
    }
}
