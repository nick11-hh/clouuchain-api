<?php

namespace App\Console\Commands\DataFixer;

use App\Models\BalanceRecord;
use App\Models\RechargeApply;
use App\Models\CommissionWithdraw;
use App\Models\Order;
use App\Models\BalanceRecharge;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class BalanceRecordUpdate extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:balance-records-update {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '交易流水关联单号更新';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $this->info('开始处理' . now());

        $num = 0;
        BalanceRecord::where('source_type', '<>', BalanceRecord::SOURCE_MANUALLY_DEDUCT)
            ->where('relation_id', 0)
            ->chunkById(200, function ($balanceRecords) use (&$num) {

                if($balanceRecords){
                    foreach ($balanceRecords as $key => $balanceRecord) {

                        info('交易流水关联单号更新', [
                            'balanceRecord' => $balanceRecord->toArray()
                        ]);

                        switch ($balanceRecord->source_type) {
                            case BalanceRecord::SOURCE_RECHARGE:
                            case BalanceRecord::SOURCE_COMPLIMENTARY_RECHARGE:
                            case BalanceRecord::SOURCE_RECHARGE_REVOCATION:
                                // RechargeApply

                                $rechargeApplySerialNo = RechargeApply::where('id', $balanceRecord->order_sn)
                                    ->where('custom_id', $balanceRecord->custom_id)
                                    ->value('serial_no');

                                if($rechargeApplySerialNo){

                                    $balanceRecord->update([
                                        'relation_id' => $balanceRecord->order_sn,
                                        'order_sn' => $rechargeApplySerialNo
                                    ]);

                                    $num ++;
                                }

                                break;
                            
                            case BalanceRecord::SOURCE_WITHDRAW:
                                // CommissionWithdraw

                                $commissionWithdrawId = CommissionWithdraw::where('custom_id', $balanceRecord->custom_id)
                                    ->where('serial_no', $balanceRecord->order_sn)
                                    ->value('id');

                                if($commissionWithdrawId){

                                    $balanceRecord->update([
                                        'relation_id' => $commissionWithdrawId,
                                    ]);

                                    $num ++;
                                }

                                break;

                            case BalanceRecord::SOURCE_ORDER_PAY:
                            case BalanceRecord::SOURCE_ORDER_REFUND:
                            case BalanceRecord::SOURCE_SUPPLEMENT_FEE:
                                // Order

                                $order = Order::withTrashed()
                                    ->where('customer_id', $balanceRecord->custom_id)
                                    ->where('order_id', $balanceRecord->order_sn)
                                    ->first(['id', 'order_id', 'name']);

                                if($order){

                                    $updateArr['relation_id'] = $order->id;
                                    
                                    if($order->name){
                                        $updateArr['order_sn'] = $order->name;
                                    }

                                    $balanceRecord->update($updateArr);

                                    $num ++;
                                }

                                break;

                            case BalanceRecord::SOURCE_PAYPAL_RECHARGE:
                                // BalanceRecharge

                                $balanceRechargeId = BalanceRecharge::where('custom_id', $balanceRecord->custom_id)
                                    ->where('out_trade_no', $balanceRecord->order_sn)
                                    ->value('id');

                                if($balanceRechargeId){

                                    $balanceRecord->update([
                                        'relation_id' => $balanceRechargeId,
                                    ]);

                                    $num ++;
                                }

                                break;
                        }

                    }
                }
            });

        $this->info('结束处理' . now() . '，一共处理了' . $num . '条交易流水信息');

        return true;
    }

}
