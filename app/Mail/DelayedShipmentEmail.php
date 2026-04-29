<?php
namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DelayedShipmentEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected object|array $mailConfig;

    protected array $params;

    public function __construct($params)
    {
        $this->params = $params;
        $this->mailConfig = MailConfig::getEmailConfig();
    }

    public function build(): Mailable
    {
        $templateData = EmailTemplate::query()->where('type', EmailTemplate::DELAYED_SHIPMENT)->where('enabled', 1)->first();
        if (empty($templateData)){
            info('延迟发货提醒-发送邮件通知', ['error' => '未找到对应的邮件模板或者邮件模板未启用']);

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

        info('延迟发货-发送邮件通知', ['data' => $data]);
        return $this->view('mail.verification_code')
            ->subject($data['subject'])
            ->from($this->mailConfig['from_address'] ?? env('MAIL_FROM_ADDRESS'), $this->mailConfig['from_name'] ?? env('MAIL_FROM_NAME'))
            ->with([
                'content' => $data['content'],
            ]);
    }
}
