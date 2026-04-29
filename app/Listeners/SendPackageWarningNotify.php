<?php

namespace App\Listeners;

use App\Events\UpdatedPackageWarning;
use App\Jobs\SendSMSNotify;
use App\Jobs\SendWhatsAppNotify;
use App\Jobs\WechatOATplMsg\PackageWarning;
use App\Listeners\Traits\ResetQueueMailConf;
use App\Mail\SendPackageWarningEmail;
use App\Models\ApiSmsTemplate;
use App\Models\ApiWtsAppTemplate;
use App\Models\EmailTemplate;
use App\Models\MiniprogramTemp;
use App\Services\WechatServices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPackageWarningNotify implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    use ResetQueueMailConf;

    /**
     * 任务将被发送到的队列的名称。
     *
     * @var string|null
     */
    public $queue = 'notify';

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
     * @param UpdatedPackageWarning $event
     * @return void
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(UpdatedPackageWarning $event)
    {
        info('发送包裹预警消息');
        $package = $event->package;
        $user = $package->owner;

        $template = MiniprogramTemp::getTempIDByCompanyIdAndType(
            $package->company_id,
            MiniprogramTemp::PACKAGE_WARNING
        );

        if ($template && $user && $user->open_id) {
            $mini = (new WechatServices($user->company_id))->getMiniProgram();
            $res = $mini->subscribe_message->send([
                'template_id' => $template->template_id,
                'touser' => $user->open_id,
                'page' => '/pages/index/packages/index?type=1',
                'data' => [
                    'character_string1' => [
                        'value' => $package->express_num,
                    ],
                    'time2' => [
                        'value' => (string) $package->created_at,
                    ],
                    'thing3' => [
                        'value' => '包裹长期未入库，请尽快查询物流或联系客服',
                    ],
                ],
            ]);

            app('log')->debug('消息发送成功返回结果为：', $res);
        } else {
            app('log')->debug('没有查询到当前用户的 form id 或者没有模板');
        }

        $template = EmailTemplate::where('company_id', $user->company_id)
            ->where('type', EmailTemplate::PACKAGE_WARNING)
            ->where('enabled', 1)
            ->first();

        if ($template && $user->email) {
            $res = $this->setMailConf($user->company_id);
            if ($res) {
                Mail::to($user->email)->queue(new SendPackageWarningEmail($package, $template, $user));
            }
        }

        if ($user && $user->phone) {
            dispatch(new SendSMSNotify(
                [
                    'timezone' => $user->timezone,
                    'receiver' => $user->phone,
                    'type' => ApiSmsTemplate::PACKAGE_WARNING,
                    'params' => [
                        'package' => $package->express_num,
                    ],
                ],
                $user->company_id
            ))->onQueue('notify');

            dispatch(new SendWhatsAppNotify(
                [
                    'timezone' => $user->timezone,
                    'receiver' => $user->phone,
                    'type' => ApiWtsAppTemplate::PACKAGE_WARNING,
                    'params' => [
                        'package' => $package->express_num,
                    ],
                ],
                $user->company_id
            ))->onQueue('notify');
        }
        // 公众号模板消息
        if ($user && $user->oa_open_id) {
            dispatch(new PackageWarning($user->company_id, $user, $package))->onQueue('notify');
        }
    }

    /**
     * 获取监听器队列的名称。
     *
     * @return string
     */
    public function viaQueue()
    {
        return 'notify';
    }
}
