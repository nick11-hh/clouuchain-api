<?php

namespace App\Services\Client;

use App\Jobs\BrevoEmailJob;
use App\Jobs\SendEmailJob;
use App\Lib\Code;
use App\Mail\ChangePasswordEmail;
use App\Mail\MailConfig;
use App\Models\ClientGoods;
use App\Models\Custom;
use App\Models\CustomConfig;
use App\Models\Order;
use App\Models\OrderResourcesModel;
use App\Models\ThirdPartySystemConfigModel;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Exceptions\AccidentException;

class HomeService
{
    /** 订单数据统计
     * @return array
     */
    public function orderStatistics()
    {
        $mappings = [
            'no_quote' => 0,
            'wait_pay' => 2,
            'wait_deal' => 3,
        ];
        $data = (new OrderService(new Order()))->statusCount();

        $result = [];
        foreach ($data as $value) {
            foreach ($mappings as $key => $status) {
                if ($status === $value['status']) {
                    $result[$key] = $value['count'];
                }
            }
        }

        //寻源下单-待确认报价
        $result['wait_quote_confirm'] = OrderResourcesModel::query()->where(['customer_id' => getCustomId(), 'status' => OrderResourcesModel::STATUS_WAIT_CONFIRMED])->count();

        //已选择产品-未推送商品数
        $result['no_push'] = ClientGoods::query()->where(['custom_id' => getCustomId(), 'status' => ClientGoods::STATUS_DEFAULT, 'goods_type' => 1])->count();

        return $result;
    }

    /** 产品数统计
     * @return int[]
     */
    public function productStatistics()
    {
        $data = ClientGoods::query()
            ->where([
                'custom_id' => getCustomId(),
                'goods_type' => 1
                    ])
            ->selectRaw('count(*) as num, status')
            ->groupBy('status')->get();
        $result = ['all' => 0, 'published' => 0, 'wait_publish' => 0];
        foreach ($data as $value) {
            $result['all'] += $value->num;
            if ($value->status === ClientGoods::STATUS_PUBLISHED) {
                $result['published'] += $value->num;
            } else {
                $result['wait_publish'] += $value->num;
            }
        }
        return $result;
    }

    /** 收入支出统计
     * @param $params
     * @return array
     */
    public function incomeStatistics($params)
    {
        $dateList = $this->getStatisticsDate($params);
        $data = Order::query()->where('customer_id', getCustomId())
            ->whereBetween('paymented_at', [$params['start_date'], $params['end_date']])
            ->selectRaw('SUM(current_total_price) as income_amount,
            (SUM(vendor_price) + SUM(logistics_fee) + SUM(other_supplement_price) - SUM(favourable_price) + SUM(supplement_price) - SUM(refund_price)) as cost_amount,
            DATE(paymented_at) as date')->groupBy('date')->get();
        $data = $data->keyBy('date');
        $result = [];
        foreach ($dateList as $date) {
            $result[] = isset($data[$date]) ? $data[$date] : ['date' => $date, 'income_amount' => 0, 'cost_amount' => 0];
        }
        return $result;
    }

    /** 订单量统计
     * @param $params
     * @return array
     */
    public function orderTotalStatistics($params)
    {
        $dateList = $this->getStatisticsDate($params);
        $data = Order::query()->where('customer_id', getCustomId())
                              ->where('order_type', Order::ORDER_TYPE_PLACE)
                              ->whereBetween('created_at', [$params['start_date'], $params['end_date']])
                              ->selectRaw('COUNT(*) as order_total, DATE(created_at) as date')->groupBy('date')->get();

        $data = $data->keyBy('date');

        $result = [];
        foreach ($dateList as $date) {
            $result[] = isset($data[$date]) ? $data[$date] : ['date' => $date, 'order_total' => 0];
        }
        return $result;
    }


    /** 获取客户信息
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    public function getCustomInfo()
    {
        return Custom::query()->findOrFail(getCustomId());
    }

    /** 更新客户信息
     * @param $params
     * @return bool
     */
    public function updateCustom($params)
    {
        validator($params, $this->rules())->validate();
        $custom = Custom::query()->findOrFail(getCustomId());
        $custom->custom_name = $params['company_name'];
        $custom->custom_phone = $params['phone'];
        $custom->custom_address = $params['address'] ?? '';
        $custom->default_language = $params['default_language'] ?? '';
        $custom->phone_area_code = $params['phone_area_code'] ?? '';
        $custom->save();

        //推送brevo邮件营销
        $brevo = ThirdPartySystemConfigModel::getBrevoConfig();
        if ($custom->custom_email && $brevo) {
            $data = [
                'type' => 'createContact',
                'custom_ids' => [$custom->id],
            ];

            dispatch(new BrevoEmailJob($data));
        }

        return true;
    }

    /** 更新头像数据
     * @param $params
     * @return bool
     */
    public function updateAvatar($params)
    {
        validator($params, [
            'avatar' => 'required|string',
        ])->validate();
        $user = User::query()->findOrFail(auth('client')->id());
        $user->avatar = $params['avatar'];
        return $user->save();
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
        $user = User::query()->findOrFail(auth('client')->id());
        $credentials = [
            'username' => $user->username,
            'password' => $params['old_password'],
        ];
        $token = Auth::guard('client')->attempt($credentials);
        if (!$token) throw new AccidentException('旧密码错误', Code::OPERATE_FAIL);
        if ($params['new_password'] != $params['confirm_password']) throw new AccidentException('两次密码不一致', Code::OPERATE_FAIL);
        $user->password = bcrypt($params['new_password']);

        //更改密码-发送邮件通知
        try {
            $custom = Custom::query()->findOrFail(getCustomId());

            dispatch(new SendEmailJob('ChangePasswordEmail', $custom->custom_email));
        } catch (\Exception $e) {
            info('更改密码-发送邮件通知失败', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'msg' => $e->getMessage()
            ]);
        }

        return $user->save();
    }

    /**
     * @desc 更改邮箱
     * @param $params
     * @return bool
     */
    public function updateEmail($params)
    {
        validator($params, [
            'email' => 'required|email',
            'email_verification_code' => 'required|integer',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $uuid = getCurrentUuid();
            if (!AuthService::checkEmailVerificationCode($uuid, $params['email_verification_code'], $params['email'])) {
                throw new AccidentException('邮箱验证码错误', Code::OPERATE_FAIL);
            }

            $user = User::query()->findOrFail(auth('client')->id());

            $user->email = $params['email'];

            Custom::query()->where(['id' => getCustomId()])->update(['custom_email' => $params['email']]);

            Cache::forget(config('dropshipping.cache_prefix.client_verify_code') . $uuid . ':' . $params['email']);

            return $user->save();
        });
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

    public function rules()
    {
        return [
            'company_name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|string',
            'address' => 'sometimes|nullable|string',
            'default_language' => 'sometimes|nullable|string',
            'phone_area_code' => 'sometimes|nullable|string',
        ];
    }

    public function getCustomConfig()
    {
        return CustomConfig::query()->where('custom_id', getCustomId())->first();
    }

    public function saveCustomConfig($params)
    {
        validator($params, [
            'default_original_price_ratio' => 'required|numeric',
            'default_compare_original_price_ratio' => 'required|numeric',
        ])->validate();

        $configData = CustomConfig::query()->where('custom_id', getCustomId())->first();
        $row = CustomConfig::init($params);

        if ($configData) {
            $configData->update($row);
        } else {
            CustomConfig::query()->create($row);
        }

        return true;
    }

    /**
     * @desc 今日统计
     * @return array
     */
    public function todayStatistics():array
    {
        $mappings = [
            'no_quote' => [Order::STATUS_QUOTE_NO],//未报价订单数
            'wait_pay' => [Order::STATUS_QUOTED],//待支付订单数
            'wait_deal' => [Order::STATUS_PENDING], //处理中订单数
        ];
        $data = Order::query()->where('customer_id', getCustomId())->whereIn('order_status', array_merge_recursive($mappings))
                     ->selectRaw('count(*) as num, order_status as status')->groupBy('order_status')->get();
        $result = [];
        foreach ($mappings as $key => $map) {
            $result[$key] = 0;
        }
        foreach ($data as $value) {
            foreach ($mappings as $key => $mapping) {
                if (in_array($value->status, $mapping)) {
                    isset($result[$key]) ? $result[$key] += $value->num : $result[$key] = $value->num;
                }
            }
        }

        //寻源下单-待确认报价
        $result['wait_confirm'] = OrderResourcesModel::query()->where(['customer_id' => getCustomId(), 'status' => OrderResourcesModel::STATUS_WAIT_CONFIRMED])->count();

        //已选择产品-未推送商品数
        $result['wait_publish'] = ClientGoods::query()->where(['custom_id' => getCustomId(), 'status' => ClientGoods::STATUS_DEFAULT])->count();

        return $result;
    }

    /**
     * 我的营收数据(统计，今日和昨日数据)
     * @return array[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/16 18:26
     */
    public function revenueDataStatistics()
    {
        $data = [
            'today' => [
                'total_order'   => 0,
                'income_amount' => 0,
                'cost_amount'   => 0,
                'profit'        => 0,
            ],
            'yesterday' => [
                'total_order'   => 0,
                'income_amount' => 0,
                'cost_amount'   => 0,
                'profit'        => 0,
            ],
        ];

        $selectRaw = "count(*) `total_order`
        ,(sum(vendor_price) + sum(logistics_fee) + sum(other_supplement_price) + sum(supplement_price) - sum(favourable_price) - sum(refund_price)) `income_amount`
        ,(sum(payment_price) + sum(supplement_price) - sum(refund_price)) as `cost_amount`
        ,date(deliver_time) `create_date`
        ";

        $orderData = Order::query()->where('customer_id', getCustomId())
            ->selectRaw($selectRaw)
            ->whereBetween('deliver_time', [Carbon::yesterday()->toDateTimeString(), Carbon::today()->toDateString() . ' 23:59:59'])
            ->groupBy('create_date')
            ->get();

        if (!empty($orderData)) {
            $orderData = $orderData->toArray();
            foreach ($orderData as $key => &$value) {
                $value['profit'] = max(($value['income_amount'] - $value['cost_amount']), 0);
                if ($key === 0) {
                    $data['yesterday'] = $value;
                } else {
                    $data['today'] = $value;
                }
            }

        }

        return $data;
    }

    public function worldCountries()
    {
        $locale = isZh() ? 'zh-cn' : 'en';

        $data = DB::table('world_countries as countries')
                  ->leftJoin('world_countries_locale as locale', 'countries.id' ,'=', 'country_id')
                  ->where('locale.locale', $locale)
                  ->select('countries.id', 'locale.name as local_name', 'countries.code as code')
                  ->get();

        return $data;

    }

}
