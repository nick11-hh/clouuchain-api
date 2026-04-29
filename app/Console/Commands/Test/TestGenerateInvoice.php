<?php

namespace App\Console\Commands\Test;

use App\Models\Custom;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class TestGenerateInvoice extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:generate-invoice {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试生成订单发票';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        app()->setLocale('ar_SA');
        $data = [
            'custom_id'     => 1,
            'invoice_no'    => 'TEST20250513160055',
            'source_type'   => 1,
            'file_type'     => 1,
            'created_at'    => now()->toDateTimeString(),
            'status'        => 0,
            'client_menu_logo' => '',
            'seller_info' => ["tongxiao","shengzheng","bantian jiannan sidai dasa"],
            'buyer_info' => Custom::query()->with(['balance', 'invoiceAddress'])->find(1)->toArray(),
            'list' => [],
            'sub_total' => 111
        ];
        // dd($data);
        $html = view('invoice.order', ['data' => $data])->render();
        dd($html);

    }
}
