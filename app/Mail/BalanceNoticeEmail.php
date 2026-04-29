<?php

namespace App\Mail;

use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BalanceNoticeEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected object|array $mailConfig;

    protected array $params;

    protected int $customId;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $this->customId = $params['customer_id'] ?? 0;
        $this->mailConfig = MailConfig::getEmailConfig();
    }

    /**
     * 构建消息
     *
     * @return $this
     */
    public function build()
    {

        $templateData = EmailTemplate::query()->where('type', EmailTemplate::INSUFFICIENT_BALANCE)->where('enabled', 1)->first();
        $title = $templateData['title'] ?? '';
        $content = $templateData['content'] ?? '';
        if ($content) {
            $content = json_decode($content, true);
            $content = $content['default'] ?: '';
        }

        $customData = Custom::query()->find($this->customId);
        $balanceData = CustomBalance::query()->where('custom_id', $this->customId)->first();
        $name = $customData['custom_name'] ?? '';
        $balance = ($balanceData['balance'] ?? 0.00) / 100;
        if (strpos($content, '{{user_name}}') !== false) {
            $content = str_replace('{{user_name}}', $name, $content);
        }
        if (strpos($content, '{{balance}}') !== false) {
            $content = str_replace('{{balance}}', $balance, $content);
        }
        $data = [
            'subject' => $title ?: (isEn() ? '[Yun Lian Tiao] Your account balance is insufficient. Please recharge as soon as possible!' : '[云链条] 您的账户余额不足，请尽快充值！'),
            'content' => $content ?: (isEn() ?
                '<p>Dear [' . $name . '],</p>

                <p>How are you?</p>

               <p> We have noticed that the balance in your account on our platform is insufficient. We kindly remind you to check and recharge promptly to avoid any disruption to your regular business operations.

                Currently, your account balance is: ' . $balance . '.</p>' :
                '<p>尊敬的【' . ($name ?: '客户') . '】：</p>

                  <p>您好！</p>

                <p>我们注意到您在我们平台的账户余额已经不足，特此提醒您关注并及时充值，以免影响您的正常业务进行。

                目前，您的账户余额为：' . $balance . '</p>')
        ];

        info('余额不足', ['data' => $data]);
        return $this->view('mail.verification_code')
            ->subject($data['subject'])
            ->from($this->mailConfig['from_address'] ?? env('MAIL_FROM_ADDRESS'), $this->mailConfig['from_name'] ?? env('MAIL_FROM_NAME'))
            ->with([
                'content' => $data['content'],
            ]);
    }
}
