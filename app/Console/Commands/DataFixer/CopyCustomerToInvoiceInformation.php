<?php

namespace App\Console\Commands\DataFixer;

use App\Models\Custom;
use App\Models\CustomInvoiceAddressModel;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class CopyCustomerToInvoiceInformation extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:copy-customer-info-to-invoice-address {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将客户信息复制到客户发票地址表';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $this->info('开始复制' . now());

        $num = 0;
        Custom::chunk(200, function ($customs) use (&$num) {
            foreach ($customs as $custom) {

                $num ++;

                CustomInvoiceAddressModel::updateOrCreate(
                    ['customer_id' => $custom->id],
                    [
                        'name' => $custom->custom_name,
                        'phone_area_code' => $custom->phone_area_code,
                        'phone_number' => $custom->custom_phone,
                        'email' => $custom->custom_email,
                    ]
                );

            }
        });


        $this->info('结束复制' . now() . '，一共复制了' . $num . '条客户信息');

        return true;
    }
}
