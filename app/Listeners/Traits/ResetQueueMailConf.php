<?php

namespace App\Listeners\Traits;

use App\Models\MailSmtpConfig;
use Illuminate\Support\Facades\Config;

trait ResetQueueMailConf
{
    public function setMailConf($companyID)
    {
        $config = MailSmtpConfig::query()->where('company_id', $companyID)->first();

        if ($config) {
            Config::set('mail.host', $config->host);
            Config::set('mail.port', $config->port);
            Config::set('mail.encryption', $config->encryption);
            Config::set('mail.username', $config->username);
            Config::set('mail.password', $config->password);
            Config::set('mail.from.address', $config->from_address);
            Config::set('mail.from.name', $config->from_name);
            app('log')->channel('single')->info('重置邮件配置 ' . $companyID);

            return true;
        } else {
            app('log')->debug('发送邮件没有合适的配置,公司 id 为:' . $companyID);

            return false;
        }
    }
}
