<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_outbound_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id')->comment('出库单号');
            $table->bigInteger('custom_id')->comment('出库单号');
            $table->string('outbound_sn')->comment('出库单号');
            $table->bigInteger('picking_id')->nullable()->comment('拣货单id');
            $table->tinyInteger('type')->comment('出库单类型  1 单sku单件 2 单sku多件 3 多sku多件');
            $table->tinyInteger('sale_platform')->comment('销售平台');
            $table->string('logistics_provider')->comment('物流服务商');
            $table->string('tracking_number')->comment('物流跟踪号');
            $table->string('shipment_pdf')->comment('发货面单');
            $table->tinyInteger('status')->default(1)->comment('出库单状态  1 待处理 2 待拣货 3 待打包 4 待发货  5 已发货  6 异常 7 已取消');
            $table->text('remark')->nullable()->comment('备注');
            $table->float('long', 12, 4)->default(0)->comment('包裹长');
            $table->float('width', 12, 4)->default(0)->comment('包裹宽');
            $table->float('height', 12, 4)->default(0)->comment('包裹高');
            $table->float('weight', 12, 4)->default(0)->comment('包裹重量');
            $table->timestamp('add_picking_time')->nullable()->comment('加入拣货单时间');
            $table->timestamp('picking_time')->nullable()->comment('拣货时间');
            $table->timestamp('packaged_time')->nullable()->comment('打包时间');
            $table->timestamp('outbound_time')->nullable()->comment('出库时间');
            $table->timestamp('cancel_time')->nullable()->comment('取消时间');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_outbound_orders');
    }
};
