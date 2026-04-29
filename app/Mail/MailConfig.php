<?php

namespace App\Mail;


use App\Models\MailSmtpConfig;
use Illuminate\Support\Facades\Config;

class MailConfig
{
    public static function getEmailConfig(): array|object
    {
        if ($emailConfigData = MailSmtpConfig::query()->first()) {
            Config::set('mail.mailers.smtp.host', $emailConfigData['host']);
            Config::set('mail.mailers.smtp.port', $emailConfigData['port']);
            Config::set('mail.mailers.smtp.encryption', $emailConfigData['encryption']);
            Config::set('mail.mailers.smtp.username', $emailConfigData['username']);
            Config::set('mail.mailers.smtp.password', $emailConfigData['password']);
            Config::set('mail.from.from_address', $emailConfigData['from_address']);
            Config::set('mail.from.from_name', $emailConfigData['from_name']);
        }

        if (empty($emailConfigData)) {
            $emailConfigData = array_merge(Config::get('mail.mailers.smtp'), Config::get('mail.from'));
        }

        return $emailConfigData;
    }

}
