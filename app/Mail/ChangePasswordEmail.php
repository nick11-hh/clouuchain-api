<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChangePasswordEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected object|array $mailConfig;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->mailConfig = MailConfig::getEmailConfig();
    }

    /**
     * 构建消息
     */
    public function build(): Mailable
    {
        $templateData = EmailTemplate::query()->where('type', EmailTemplate::CHANGE_PASSWORD_MSG)->where('enabled', 1)->first();
        if (empty($templateData)){
            info('更改密码-发送邮件通知失败', ['error' => '未找到对应的邮件模板或者邮件模板未启用']);

            return $this;
        }

        $content = $templateData->content;
        if ($content) {
            $content = json_decode($content, true);
            $content = $content['default'] ?? '';
        }

        $data = [
            'subject' => $templateData->title,
            'content' => $content,
        ];

        info('更改密码-发送邮件通知', ['data' => $data]);
        return $this->view('mail.verification_code')
            ->subject($data['subject'])
            ->from($this->mailConfig['from_address'] ?? env('MAIL_FROM_ADDRESS'), $this->mailConfig['from_name'] ?? env('MAIL_FROM_NAME'))
            ->with([
                'content' => $data['content'],
            ]);
    }
}
