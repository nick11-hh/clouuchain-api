<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailJob;
use App\Mail\MailConfig;
use App\Models\Custom;
use App\Models\CustomConfig;
use App\Models\EmailTemplate;
use App\Models\Order;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class PendingOrderEmailCommand extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:pending-order-email {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '待支付订单发送邮件';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $doesntExist = EmailTemplate::query()->where('type', EmailTemplate::PENDING_PAYMENT_ORDER)->where('enabled', 1)->doesntExist();

        if ($doesntExist){
            info('待支付订单-发送邮件失败', ['error' => '未找到对应的邮件模板或者邮件模板未启用']);

            return false;
        }

        $orders = Order::query()->selectRaw('count(id) as order_total, customer_id')->groupBy('customer_id')->where('order_status', Order::STATUS_QUOTED)->get();

        if ($orders->isEmpty()) {
            return false;
        }

        $orders = $orders->pluck('order_total', 'customer_id');

        //查询客户是否开启自动支付，未开启自动支付的客户才发送邮件通知
        $filterCustomerIds = CustomConfig::query()->whereIn('custom_id', $orders->keys())->where('is_auto_payment', 1)->pluck('id', 'custom_id');

        //根据客户id过滤掉开启自动支付的订单数据
        $orders = $orders->diffKeys($filterCustomerIds);

        //根据客户id插叙客户邮箱跟客户名称
        $customers = Custom::query()->select(['id', 'custom_email', 'main_user_id'])->with('mainUser:id,username,custom_id')->whereIn('id', $orders->keys())->get();

        MailConfig::getEmailConfig();
        foreach ($customers as $customer) {
            $toEmail = $customer->custom_email ?? '';

            if (empty($toEmail)) {
                continue;
            }

            try {
                $emailParams = [
                    'user_name'      => $customer->mainUser->username ?? '',
                    'order_quantity' => $orders[$customer->id] ?? 0,
                ];

                dispatch(new SendEmailJob('PendingOrderEmail', $toEmail, $emailParams));
            } catch (\Exception $e) {
                info('产品报价-发送邮件异常', [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'msg'  => $e->getMessage()
                ]);
            }
        }

    }
}
