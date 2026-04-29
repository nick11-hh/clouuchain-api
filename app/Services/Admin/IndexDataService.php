<?php

/**
 * @Author: h9471
 * @Created: 2019/9/25 11:41
 */

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\Package;
use App\Models\Shipment;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 员工端 - 首页数据统计服务
 * Class IndexDataService
 * @package App\Services\Admin
 */
class IndexDataService extends BaseService
{
    /**
     * 获得用户统计数据
     * @return array
     */
    public static function getUserCountData(): array
    {
        $sumOfUser = User::query()->count('id');
        $sumOfUserInCurrentMonth = User::query()
            ->whereBetween(
                'created_at',
                [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]
            )->count('id');

        $sumOfUserInCurrentWeek = User::query()
            ->whereBetween(
                'created_at',
                [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]
            )->count('id');

        $data = [
            'current_month' => $sumOfUserInCurrentMonth,
            'current_week' => $sumOfUserInCurrentWeek,
            'total' => $sumOfUser,
        ];

        return self::cacheData(__METHOD__ . auth()->user()->company_id, $data);
    }

    /**
     * 获得包裹统计数据
     * @return array
     */
    public static function getPackageCountData(): array
    {
        $sum = Package::query()->count('id');
        $sumOfCurrentMonth = Package::query()
            ->whereBetween(
                'created_at',
                [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]
            )->count('id');

        $data = [
            'current_month' => $sumOfCurrentMonth,
            'total' => $sum,
        ];

        return self::cacheData(__METHOD__ . auth()->user()->company_id, $data);
    }

    /**
     * 获得订单统计数据
     * @return array
     */
    public static function getOrderCountData(): array
    {
        $sum = Order::query()->count('id');
        $sumOfCurrentMonth = Order::query()
            ->whereBetween(
                'created_at',
                [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]
            )->count('id');

        $data = [
            'current_month' => $sumOfCurrentMonth,
            'total' => $sum,
        ];

        return self::cacheData(__METHOD__ . auth()->user()->company_id, $data);
    }

    /**
     * 获得运单统计数量
     * @return array
     */
    public static function getShipmentCountData(): array
    {
        $sum = Shipment::query()->count('id');
        $sumOfCurrentMonth = Shipment::query()
            ->whereBetween(
                'created_at',
                [
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth(),
                ]
            )->count('id');

        $unShipped = Shipment::query()->where('status', Shipment::STATUS_UN_SHIPPED)
            ->count('id');

        $data = [
            'current_month' => $sumOfCurrentMonth,
            'un_shipped' => $unShipped,
            'total' => $sum,
        ];

        return self::cacheData(__METHOD__ . auth()->user()->company_id, $data);
    }

    /**
     * 获得包裹统计数据
     * @param int $status
     * @return int
     */
    public static function getPackageCountDataByStatus(int $status): int
    {
        return Package::query()->where('status', $status)->count();
    }

    /**
     * 按状态获取订单数量
     * @param mixed $status
     * @return int
     */
    public static function getOrderCountDataByStatus(int|array $status): int
    {
        // 剔除拼团子订单
        return Order::query()->whereIn('status', Arr::wrap($status))->whereNull('parent_id')->count();
    }

    /**
     * 获取包裹统计信息
     * @param int $status 包裹状态
     * @param int $scope 时间范围类型
     * @return Collection
     */
    public static function getPackageStatistics(int $status, int $scope): Collection
    {
        switch ($scope) {
            case 1:
                return self::getWeeklyData($status);
            case 2:
                return self::getMonthlyData($status);
            case 3:
                return self::getSemiannuallyData($status);
            case 4:
                return self::getAnnuallyData($status);
            default:
                return collect([]);
        }
    }

    /**
     * 生成日期范围的数组
     * @param Carbon $start_date
     * @param Carbon $end_date
     * @return array
     */
    public static function generateDateRange(Carbon $start_date, Carbon $end_date): array
    {
        $dates = [];
        for ($date = $start_date; $date->lte($end_date); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * 获取某一天的收益金额，即收入金额
     *
     * @param  \DateTimeInterface  $date
     * @return float|int
     */
    public static function getIncomeByDate(?\DateTimeInterface $date = null)
    {
        $date = $date ? $date : Carbon::now();

        $data = (float) TransactionRecord::query()
            ->whereIn('type', [TransactionRecord::PAY, TransactionRecord::RECHARGE])
            ->whereDate('created_at', '=', $date)
            ->sum('amount') / 100;

        return self::cacheData(__METHOD__ . auth()->user()->company_id, $data);
    }

    /**
     * 获取周数据，按天分组
     * @param int $status
     * @return Collection
     */
    private static function getWeeklyData(int $status): Collection
    {
        $start = Carbon::parse('6 days ago')->StartOfDay();
        $end = Carbon::now()->endOfDay();

        if ($status == 2) {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(in_storage_at,'%Y-%m-%d') as days,count('id') as count")
                ->whereBetween('in_storage_at', [$start, $end])
                ->groupBy(['days'])
                ->get();
        } else {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(created_at,'%Y-%m-%d') as days,count('id') as count")
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '>=', $status)
                ->groupBy(['days'])
                ->get();
        }

        $res = self::generateDataOfZeroDay($data, $start, $end);

        return collect(self::cacheData(__METHOD__ . $status . auth()->user()->company_id, $res->toArray()));
    }

    /**
     * 获取月份数据，按天分组
     * @param int $status
     * @return Collection
     */
    private static function getMonthlyData(int $status): Collection
    {
        $start = Carbon::parse('30 days ago')->StartOfDay();
        $end = Carbon::now()->endOfDay();

        if ($status == 2) {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(in_storage_at,'%Y-%m-%d') as days,count('id') as count")
                ->whereBetween('in_storage_at', [$start, $end])
                ->groupBy(['days'])
                ->get();
        } else {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(created_at,'%Y-%m-%d') as days,count('id') as count")
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '>=', $status)
                ->groupBy(['days'])
                ->get();
        }

        $res = self::generateDataOfZeroDay($data, $start, $end);

        return collect(self::cacheData(__METHOD__ . $status . auth()->user()->company_id, $res->toArray()));
    }

    /**
     * 获取半年数据，按月分组
     * @param int $status
     * @return Collection
     */
    private static function getSemiannuallyData(int $status): Collection
    {
        $start = Carbon::parse('5 months ago')->startOfMonth();
        $end = Carbon::now()->endOfDay();

        if ($status == 2) {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(in_storage_at,'%Y-%m') as months,count('id') as count")
                ->whereBetween('in_storage_at', [$start, $end])
                ->groupBy(['months'])
                ->get();
        } else {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as months,count('id') as count")
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '>=', $status)
                ->groupBy(['months'])
                ->get();
        }

        $res = self::generateDataOfZeroMonth($data, $start, $end);

        return collect(self::cacheData(__METHOD__ . $status . auth()->user()->company_id, $res->toArray()));
    }

    /**
     * 按年获取数据并按月份分组
     * @param int $status
     * @return Collection
     */
    private static function getAnnuallyData(int $status): Collection
    {
        $start = Carbon::parse('11 months ago')->startOfMonth();
        $end = Carbon::now()->endOfDay();

        if ($status == 2) {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(in_storage_at,'%Y-%m') as months,count('id') as count")
                ->whereBetween('in_storage_at', [$start, $end])
                ->groupBy(['months'])
                ->get();
        } else {
            $data = Package::query()
                ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as months,count('id') as count")
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '>=', $status)
                ->groupBy(['months'])
                ->get();
        }

        $res = self::generateDataOfZeroMonth($data, $start, $end);

        return collect(self::cacheData(__METHOD__ . $status . auth()->user()->company_id, $res->toArray()));
    }

    /**
     * 数据为空的日期生成零数据
     * @param Collection $data
     * @param Carbon $start
     * @param Carbon $end
     * @return Collection
     */
    public static function generateDataOfZeroDay(Collection $data, Carbon $start, Carbon $end): Collection
    {
        $dates = self::generateDateRange($start, $end);

        $realDates = [];

        $data->flatMap(function ($value) use (&$realDates) {
            $realDates[] = $value->days;
        });

        $dates = array_diff($dates, $realDates);

        foreach ($dates as $date) {
            $data->prepend(['days' => $date, 'count' => 0]);
        }

        return $data->sortBy('days')->values();
    }

    /**
     * 数据为空的月份生成零数据
     * @param Collection $data
     * @param Carbon $start
     * @param Carbon $end
     * @return Collection
     */
    private static function generateDataOfZeroMonth(Collection $data, Carbon $start, Carbon $end): Collection
    {
        $months = self::generateMonthRange($start, $end);

        $realDates = [];

        $data->flatMap(function ($value) use (&$realDates) {
            $realDates[] = $value->months;
        });

        $months = array_diff($months, $realDates);

        foreach ($months as $month) {
            $data->prepend(['months' => $month, 'count' => 0]);
        }

        return $data->sortBy('months')->values();
    }

    /**
     * 生成月份范围的数组
     * @param Carbon $start_date
     * @param Carbon $end_date
     * @return array
     */
    private static function generateMonthRange(Carbon $start_date, Carbon $end_date): array
    {
        $months = [];
        for ($month = $start_date; $month->lte($end_date); $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }

    /**
     * 缓存数据
     * @param string $method
     * @param  mixed  $data
     * @return mixed
     */
    private static function cacheData(string $method, $data)
    {
        return Cache::get($method, function () use ($data, $method) {
            Cache::put($method, $data, 30);

            return $data;
        });
    }

    /**
     * 获取首页工单统计数据
     */
    public static function getWorkOrderCountData()
    {
        $data = [];
        $admin_id = auth()->id();

        // 只取待处理
        $data['to_do_work_order'] = WorkOrder::query()->whereNull('parent_id')
            ->where(function ($query) use ($admin_id) {
                $query->where(['assigned_by' => $admin_id, 'status' => WorkOrder::STATUS_PROCESSING])
                    ->orWhereHas('workOrder', function ($query) use ($admin_id) {
                        $query->where(['assigned_by' => $admin_id, 'status' => WorkOrder::STATUS_PROCESSING]);
                    });
            })->count();

        $data['finish_work_order'] = WorkOrder::query()->whereNull('parent_id')
            ->where(function ($query) use ($admin_id) {
                $query->where('status', WorkOrder::STATUS_FINISH)->whereHas('logs', function ($query) use($admin_id) {
                    $query->where('assigned_by', $admin_id);
                })->orWhereHas('workOrder' , function ($query) use($admin_id) {
                    $query->where('status', WorkOrder::STATUS_FINISH)->whereHas('logs', function ($q) use($admin_id) {
                        $q->where('assigned_by', $admin_id);
                    });
                });
            })->count();

        $data['close_work_order'] = WorkOrder::query()->whereNull('parent_id')
            ->where(function ($query) use ($admin_id) {
                $query->where('status', WorkOrder::STATUS_CLOSE)
                    ->whereHas('logs', function ($query) use($admin_id) {
                    $query->where('assigned_by', $admin_id);
                })->orWhereHas('workOrder' , function ($query) use($admin_id) {
                    $query->where('status', WorkOrder::STATUS_CLOSE)
                        ->whereHas('logs', function ($q) use($admin_id) {
                        $q->where('assigned_by', $admin_id);
                    });
                });
            })->count();

        return $data;
    }

}
