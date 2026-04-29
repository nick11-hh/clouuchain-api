<?php

namespace App\Listeners;

use App\Events\AfterUpdateLogisticsSn;
use App\Events\UpdatedOrder2InTran;
use App\Jobs\SendSMSNotify;
use App\Jobs\WechatOATplMsg\OrderShippedNotify;
use App\Jobs\WechatOATplMsg\OrderTrackNumberNotify;
use App\Listeners\Traits\ResetQueueMailConf;
use App\Mail\SendOrderInTranEmail;
use App\Models\ApiSmsTemplate;
use App\Models\EmailTemplate;
use App\Models\MiniprogramTemp;
use App\Models\Order;
use App\Models\UserNotificationRecord;
use App\Services\WechatServices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderTrackNumberUpdatedNotify implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    use ResetQueueMailConf;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param UpdatedOrder2InTran $event
     * @return void
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(AfterUpdateLogisticsSn $event)
    {
        app('log')->debug('发送订单物流单号更新消息');

        $orderIds = collect($event->list)->pluck('id')->flatten()->values()->all();

        $orders = Order::query()->with('user')->whereKey($orderIds)->get();

        foreach ($orders as $order) {
            $user = $order->user;
            /*$template = MiniprogramTemp::getTempIDByCompanyIdAndType($order->company_id, MiniprogramTemp::ORDER_SHIPPING);
            if ($template && $user && $user->open_id) {
                $service = new WechatServices($user->company_id);
                $mini = $service->getMiniProgram();

                $data = [         // 模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
                    'character_string6' => [
                        'value' => $order->order_sn,
                    ],
                    'phrase3' => [
                        'value' => '订单已发货',
                    ],
                    'time5' => [
                        'value' => date('Y年m月d日 H:i'),
                    ],
                ];

                if  ($service->isOversea()) {
                    $data = [         // 模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
                        'character_string1' => [
                            'value' => $order->order_sn,
                        ],
                        'phrase2' => [
                            'value' => '订单已发货',
                        ],
                        'time3' => [
                            'value' => date('Y年m月d日 H:i'),
                        ],
                    ];
                }

                $res = $mini->subscribe_message->send([
                    'template_id' => $template->template_id,
                    'touser' => $user->open_id,     // 接收者（用户）的 openid
                    'page' => $template->path . $order->id, // '/pages/index/package/package?type=ruku',
                    'data' => $data,
                ]);
                app('log')->debug('消息发送成功返回结果为：', $res);
            } else {
                app('log')->debug('没有查询到当前用户的 form id 或者没有模板');
            }

            $template = EmailTemplate::where('company_id', $user->company_id)
                ->where('type', EmailTemplate::ORDER_IN_TRAN)->where('enabled', 1)
                ->first();

            if ($template && $user->email) {
                info('当前邮件配置', ['template' => $template->toArray(), 'mail' => $user->email]);
                $this->setMailConf($user->company_id);
                Mail::to($user->email)->send(new SendOrderInTranEmail($order->order_sn, $template, $user));
            }

            if ($user && $user->phone) {
                dispatch(new SendSMSNotify(
                    [
                        'timezone' => $user->timezone,
                        'receiver' => $user->phone,
                        'type' => ApiSmsTemplate::ORDER_IN_TRAN,
                        'params' => [
                            'order' => $order->order_sn,
                        ],
                    ],
                    $user->company_id
                ));
            }*/

            if ($user && $user->oa_open_id) {
                dispatch(new OrderTrackNumberNotify($user->company_id, $user, $order))
                    ->onQueue('notify');
            }
            // FCM消息
            if ($user && $user->pushTokens->isNotEmpty()) {
                dispatch(new \App\Jobs\FCM\OrderLogisticsUpdated($user->company_id, $user, $order));
            }

            UserNotificationRecord::store(
                $user,
                UserNotificationRecord::TYPE_ORDER_LOGISTICS_UPDATED,
                replace: ['order_sn' => $order->order_sn, 'logistics_company' => $order->logistics_company, 'logistics_sn' => $order->logistics_sn],
                data: [
                    'type' => 6,
                    'value' => (string) $order->getKey(),
                ]
            );
        }
    }
}
