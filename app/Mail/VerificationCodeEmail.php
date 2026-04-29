<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected object|array $mailConfig;

    protected array $params;

    protected int $emailTemplate;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($params, $emailTemplate = EmailTemplate::REGISTER_CHECK)
    {
        $this->params = $params;
        $this->emailTemplate = $emailTemplate;

        $this->mailConfig = MailConfig::getEmailConfig();
    }

    /**
     * 构建消息
     *
     * @return $this
     */
    public function build(): Mailable
    {

        $templateData = EmailTemplate::query()->where('type', $this->emailTemplate)->where('enabled', 1)->first();

        $title = $templateData->title ?? '';
        $content = $templateData->content ?? '';

        if ($content) {
            $content = json_decode($content, true);
            $content = $content['default'] ?: '';
        }

        //占位符替换对应的值
        foreach ($this->params as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            if (str_contains($content, $placeholder)) {
                $content = str_replace($placeholder, $this->params[$key], $content);
            }
        }

        $code = $this->params['code'] ?? '';

        $defaultTitle = "【Dropshipping】Please verify your email";

        $defaultContent = "<p>Dear users, hello!</p>";
        $defaultContent .= "<p>You are using the verification code, please fill in the following verification code within 5 minutes, if you are not using the email, please ignore the email</p>";
        $defaultContent .= "<p>" . $code . "</p>";
        $defaultContent .= "<p>Note: This action may change your login password or email address. This is a system email, please do not reply!</p>";

        $data = [
            'subject' => $title ?: $defaultTitle,
            'content' => $content ?: $defaultContent,
        ];

        info('验证码邮件', ['data' => $data]);
        return $this->view('mail.verification_code')
            ->subject($data['subject'])
            ->from($this->mailConfig['from_address'] ?? env('MAIL_FROM_ADDRESS'), $this->mailConfig['from_name'] ?? env('MAIL_FROM_NAME'))
            ->with([
                'content' => $data['content'],
            ]);
    }
}
