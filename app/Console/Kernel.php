<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 改为每小时执行一次自动拉取平台订单，避免频率过高拉取订单报错429 too many requests
        $schedule->command('dsp:auto-pull-order')->everyThreeHours()->withoutOverlapping();
        // 每10分钟执行一次拉取订单图片
//        $schedule->command('dsp:pull-shopify-sku-img')->everyThreeHours();
        // 每天3点执行一次同步1688物流信息任务
        $schedule->command('dsp:update-1688-logistics-information')->dailyAt('03:00')->withoutOverlapping();
        // 每小时执行一次需要自动支付的订单
//        $schedule->command('dsp:auto-order-payment')->hourly();
        // 每天10点执行一次待支付订单发送邮件
        $schedule->command('dsp:pending-order-email')->dailyAt('10:00')->withoutOverlapping();
        // 每天清理telescope记录，只保留24小时内的记录
        $schedule->command('telescope:prune --hours=24')->daily()->withoutOverlapping();
        // 每小时同步第三方（马帮）仓库发货状态
        $schedule->command('sync:order-warehouse-status')->hourly()->unlessBetween('5:00', '7:00')->withoutOverlapping();
        // 每天7点执行一次查询物流轨迹
        $schedule->command('dsp:query-tracking-command')->dailyAt('07:00')->withoutOverlapping();
        // 每五分钟同步客户信息
        $schedule->command('sync:hronize-customer-information')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        // 每天同步员工信息
        $schedule->command('sync:wecom-users')->dailyAt('12:00')->withoutOverlapping()->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
