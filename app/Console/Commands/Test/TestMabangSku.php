<?php

namespace App\Console\Commands\Test;

use App\Jobs\PushGoodsToMabangJob;
use App\Models\GoodsSku;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class TestMabangSku extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:mabang-sku {goodsId=0} {type=10} {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试马帮新增库存sku、更新库存sku';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始测试' . now());

        $goodsSku = GoodsSku::with(['goods.developer', 'goodsSuppliers.supplier:id,supplier_name,contact_address', 'logistics', 'purchaser'])->find(intval($this->argument('goodsId')));
        dd($goodsSku->toArray());
        PushGoodsToMabangJob::dispatch(intval($this->argument('type')), $goodsSku, 1);

        $this->info('结束测试' . now());

        return true;
    }
}
