<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChangeEmailCodeEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected object|array $mailConfig;

    protected int $emailType;
    protected int $code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($emailType, $code)
    {
        $this->emailType = $emailType;
        $this->code = $code;
        $this->mailConfig = MailConfig::getEmailConfig();
    }

    /**
     * 构建消息
     *
     * @return $this
     */
    public function build(): Mailable
    {
        $templateData = EmailTemplate::query()->where('type', $this->emailType)->where('enabled', 1)->first();
        if (empty($templateData)){
            info('更改邮箱-发送邮件验证码失败', ['error' => '未找到对应的邮件模板或者邮件模板未启用']);

            return $this;
        }

        $content = $templateData->content;
        if ($content) {
            $content = json_decode($content, true);
            $content = $content['default'] ?? '';
        }

        if (str_contains($content, '{{code}}') && $this->code) {
            $content = str_replace('{{code}}', $this->code, $content);
        }

        $data = [
            'subject' => $templateData->title,
            'content' => $content,
        ];

        info('更改邮箱-发送邮件验证码', ['data' => $data]);
        return $this->view('mail.verification_code')
            ->subject($data['subject'])
            ->from($this->mailConfig['from_address'] ?? env('MAIL_FROM_ADDRESS'), $this->mailConfig['from_name'] ?? env('MAIL_FROM_NAME'))
            ->with([
                'content' => $data['content'],
            ]);
    }
}
