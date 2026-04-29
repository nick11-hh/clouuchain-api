<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * 发货提醒邮件类
 * Class ShippingReminderEmail
 * @package App\Mail
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/18 14:20
 */
class ShippingReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected object|array $mailConfig;

    protected array $params;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $this->params = $params;
        $this->mailConfig = MailConfig::getEmailConfig();
    }

    /**
     * 构建消息
     */
    public function build(): Mailable
    {
        $templateData = EmailTemplate::query()->where('type', EmailTemplate::SHIPPING_REMINDER)->where('enabled', 1)->first();
        if (empty($templateData)){
            info('发货提醒-发送邮件通知', ['error' => '未找到对应的邮件模板或者邮件模板未启用']);

            return $this;
        }

        $content = $templateData->content;
        if ($content) {
            $content = json_decode($content, true);
            $content = $content['default'] ?? '';
        }

        //占位符替换对应的值
        foreach ($this->params as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            if (str_contains($content, $placeholder)) {
                $content = str_replace($placeholder, $this->params[$key], $content);
            }
        }

        $data = [
            'subject' => $templateData->title,
            'content' => $content,
        ];

        info('发货提醒-发送邮件通知', ['data' => $data]);
        return $this->view('mail.verification_code')
            ->subject($data['subject'])
            ->from($this->mailConfig['from_address'] ?? env('MAIL_FROM_ADDRESS'), $this->mailConfig['from_name'] ?? env('MAIL_FROM_NAME'))
            ->with([
                'content' => $data['content'],
            ]);
    }
}
