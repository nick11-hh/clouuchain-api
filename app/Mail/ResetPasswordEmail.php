<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordEmail extends Mailable
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
    public function __construct($params)
    {
        $this->params = $params;
        $this->mailConfig = MailConfig::getEmailConfig();
    }

    /**
     * 构建消息
     *
     * @return $this
     */
    public function build(): Mailable
    {
        $link = $this->params['link'];

        $content = <<<EMAIL
        <p>Hi there,</p>
        <p>You've requested to reset your MATE password.</p>
        <p>Click the link below to choose a new one:</p>
        <p>
            <a href="{$link}" target="_blank">[{$link}]</a>
        </p>
        <p>This link will expire in 1 hour. If you didn't request this, please ignore this email.</p>
        <p>Thanks,</p>
        <p>The MATE Team</p>
EMAIL;
        $data = [
            'subject' => 'Reset Your MATE Password' ,
            'content' => $content

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
