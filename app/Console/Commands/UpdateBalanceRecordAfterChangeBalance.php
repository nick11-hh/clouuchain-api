<?php

namespace App\Console\Commands;

use App\Models\CustomBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Custom;
use App\Models\BalanceRecord;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

/**
 * 修复历史余额流水变动后的记录
 * Class UpdateBalanceRecordAfterChangeBalance
 * @package App\Console\Commands
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/12/23 17:16
 */
class UpdateBalanceRecordAfterChangeBalance extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update_balance_record_after_change_balance {--tenant=*} {--customer_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复历史余额流水变动后的记录';

    /**
     * Execute the console command.
     *
     * @return true
     */
    public function handle()
    {
        $customerId = $this->option('customer_id');
        $customerList = Custom::query()->when($customerId, function ($query) use ($customerId) {
            $query->where('id', $customerId);
        })->get();
        foreach ($customerList as $customer) {
            $list = BalanceRecord::query()->where('custom_id', $customer->id)->orderBy('id')->get();
            $balance = 0;

            $startMessage = "------------------客户ID：{$customer->id}，开始修复变动后金额------------------";
            $this->info($startMessage);

            $list->each(function ($item) use (&$balance) {
                if ($item->type == BalanceRecord::CHANGE_INCREASE) {
                    $balance += $item->amount;
                    $operator = '+';
                } else {
                    $balance -= $item->amount;
                    $operator = '-';
                }

                $item->after_change_balance = $balance;
                dump($balance);
                $item->save();

                $message = "客户ID：{$item->custom_id}，记录ID：{$item->id}，操作金额 {$operator}". $item->amount / 100 ."，修复变动后金额为：". $balance / 100;
                $this->info($message);
            });

            $customBalance = CustomBalance::query()->where('custom_id', $customer->id)->first();
            $customBalance->balance = $balance;
            $customBalance->save();

            $endMessage = "------------------客户ID：{$customer->id}，完成修复变动后金额，SUCCESS------------------";
            $this->info($endMessage);
        }

        return true;
    }

    /**
     * 获取客户IDS
     * @return \Illuminate\Support\Collection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/23 17:06
     */
    public function getCustomIds()
    {
        return BalanceRecord::query()->groupBy('custom_id')->pluck('custom_id');
    }
}
