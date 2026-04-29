<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\Admin;
use App\Models\BalanceRecord;
use App\Models\Custom;
use App\Models\InboundOrder;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\PurchaseOrdersModel;
use App\Models\RechargeApply;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\AccidentException;

class HomeService
{

    /**
     * @return array
     */
    public function todoData()
    {
        $data = [];
        $data['no_quote'] = Order::query()->where('order_status', Order::STATUS_QUOTE_NO)->count();

        $data['wait_purchase'] = PurchaseOrdersModel::query()->where('status', PurchaseOrdersModel::STATUS_PENDING)->count();

        $data['wait_inbound'] = InboundOrder::query()->where('status', InboundOrder::STATUS_WAIT_INBOUND)->count();

        $data['wait_audit'] = RechargeApply::query()->where('status', RechargeApply::STATUS_DEFAULT)->count();
        return $data;
    }

    /**
     * @return array
     */
    public function statisticsData()
    {
        $data = [];
        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();
        $nowMonthDay = Carbon::now()->startOfMonth()->toDateString();
        $lastMonthDay = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $data['today_custom'] = Custom::query()->where('created_at', '>', $today)->count();
        $data['yesterday_custom'] = Custom::query()->whereBetween('created_at', [$yesterday, $today])->count();
        $data['month_custom'] = Custom::query()->where('created_at', '>', $nowMonthDay)->count();
        $data['last_month_custom'] = Custom::query()->whereBetween('created_at', [$lastMonthDay, $nowMonthDay])->count();
        $data['today_recharge'] = BalanceRecord::query()->where('source_type', BalanceRecord::SOURCE_RECHARGE)
            ->where('created_at', '>', $today)->sum('amount') / 100;
        $data['yesterday_recharge'] = BalanceRecord::query()->where('source_type', BalanceRecord::SOURCE_RECHARGE)
            ->whereBetween('created_at', [$yesterday, $today])->sum('amount') / 100;
        return $data;
    }

    /**
     * @param $params
     * @return array
     */
    public function rechargeStatistics($params)
    {
        $dateList = $this->getStatisticsDate($params);
        $data = BalanceRecord::query()->where('source_type', BalanceRecord::SOURCE_RECHARGE)
            ->whereBetween('created_at', [$params['start_date'], $params['end_date']])
            ->selectRaw('SUM(amount) / 100 as num, DATE(created_at) as date')
            ->groupBy('date')->get();
        $data = $data->keyBy('date');
        $result = [];
        foreach ($dateList as $date) {
            $result[] = isset($data[$date]) ? $data[$date] : ['date' => $date, 'num' => 0];
        }
        return $result;
    }

    /**
     * @param $params
     * @return array
     */
    public function customStatistics($params)
    {
        $dateList = $this->getStatisticsDate($params);
        $data = Custom::query()->whereBetween('created_at', [$params['start_date'], $params['end_date']])
            ->selectRaw('COUNT(*) as num, DATE(created_at) as date')
            ->groupBy('date')->get();
        $data = $data->keyBy('date');
        $result = [];
        foreach ($dateList as $date) {
            $result[] = isset($data[$date]) ? $data[$date] : ['date' => $date, 'num' => 0];
        }
        return $result;
    }

    /**
     * @param $params
     * @return array
     */
    public function purchaseStatistics($params)
    {
        $dateList = $this->getStatisticsDate($params);
        $data = PurchaseOrdersModel::query()->whereBetween('created_at', [$params['start_date'], $params['end_date']])
            ->selectRaw('COUNT(*) as num, DATE(created_at) as date')
            ->groupBy('date')->get();
        $data = $data->keyBy('date');
        $result = [];
        foreach ($dateList as $date) {
            $result[] = isset($data[$date]) ? $data[$date] : ['date' => $date, 'num' => 0];
        }
        return $result;
    }

    public function hotSaleGoods($params)
    {
        $dateType = $params['type'] ?? 'week';
        $end = Carbon::now()->toDateTimeString();
        if ($dateType === 'week') {
            $start = Carbon::now()->startOfWeek()->toDateString();
        } else if ($dateType === 'month') {
            $start = Carbon::now()->startOfMonth()->toDateString();
        } else {
            $start = Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $end = Carbon::now()->startOfMonth()->toDateString();
        }
        return OrderLineItem::query()->whereBetween('created_at', [$start, $end])
            ->selectRaw('sum(quantity) as quantity, ANY_VALUE(product_id) as sku, ANY_VALUE(name) as name')
            ->groupBy('product_id')
            ->orderBy('quantity', 'desc')
            ->limit($params['size'] ?? 5)
            ->get();
    }

    /**
     * @param $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function applyExpressFail($params)
    {
        $size = $params['size'] ?? 2;
        $status = $params['status'] ?? 10;
        $list = Order::query()->where('order_status', $status)->latest('id')
            ->paginate($size, ['id', 'order_id', 'current_total_price', 'currency', 'created_at'], 'page', 1);
        foreach ($list as $value) {
            $value->platform = 'shopify';
        }
        return $list;
    }

    /** 获取用户信息
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    public function getAdminUserInfo()
    {
        return Admin::query()->findOrFail(auth('admin')->id());
    }

    /** 更新客户信息
     * @param $params
     * @return bool
     */
    public function updateAdminUser($params)
    {
        validator($params, [
            'email' => 'sometimes|nullable|string',
            'avatar' => 'sometimes|nullable|string',
            'invoice_info' => 'sometimes|nullable|string',
        ])->validate();

        $admin = Admin::query()->findOrFail(auth('admin')->id());
        $admin->email = $params['email'] ?? '';
        $admin->avatar = $params['avatar'] ?? '';
        $admin->invoice_info = $params['invoice_info'] ?? '';
        return $admin->save();
    }

    /** 更新密码
     * @param $params
     * @return bool
     * @throws Exception
     */
    public function updatePassword($params)
    {
        validator($params, [
            'old_password' => 'required|string',
            'new_password' => 'required|string',
            'confirm_password' => 'required|string',
        ])->validate();
        $admin = Admin::query()->findOrFail(auth('admin')->id());
        $credentials = [
            'username' => $admin->username,
            'password' => $params['old_password'],
        ];
        $token = Auth::guard('admin')->attempt($credentials);
        if (!$token) throw new AccidentException('旧密码错误', Code::OPERATE_FAIL);
        if ($params['new_password'] != $params['confirm_password']) throw new AccidentException('两次密码不一致', Code::OPERATE_FAIL);
        $admin->password = bcrypt($params['new_password']);
        return $admin->save();
    }

    protected function getStatisticsDate(&$params)
    {
        validator($params, [
            'start_date' => 'required|string',
            'end_date' => 'required|string',
        ])->validate();
        $startCarbon = Carbon::parse($params['start_date']);
        $endCarbon = Carbon::parse($params['end_date'])->addDay();
        $diff = $startCarbon->diffInDays($endCarbon, false);
        $params['end_date'] = $endCarbon->toDateString();
        $data = [$params['start_date']];
        for ($i = 1; $i < $diff; $i++) {
            $data[] = $startCarbon->addDay()->toDateString();
        }
        return $data;
    }

}
