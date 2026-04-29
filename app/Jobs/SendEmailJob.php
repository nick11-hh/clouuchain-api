<?php

namespace App\Jobs;

use App\Mail\MailConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $params;
    public string $toEmail;
    public string $className;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($className, $toEmail, $params=[])
    {
        $this->params = $params;
        $this->toEmail = $toEmail;
        $this->className = $className;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MailConfig::getEmailConfig();

        $class = '\App\Mail\\' . $this->className;
        Mail::to($this->toEmail)->send(new $class($this->params));
    }
}
