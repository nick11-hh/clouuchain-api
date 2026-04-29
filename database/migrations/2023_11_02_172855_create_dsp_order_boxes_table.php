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
        Schema::create('dsp_order_boxes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->default(0)->comment('订单id');
            $table->string('sn')->default('');
            $table->string('logistics_sn')->default('')->comment('物流单号');
            $table->string('logistics_company')->default('')->comment('物流公司名称(代码)');
            $table->bigInteger('weight')->default(0)->comment('包裹实际称重重量');
            $table->bigInteger('length')->default(0)->comment('包裹长度');
            $table->bigInteger('width')->default(0)->comment('包裹宽度');
            $table->bigInteger('height')->default(0)->comment('包裹高度');
            $table->bigInteger('volume_weight')->default(0)->comment('体积重量');
            $table->bigInteger('payment_weight')->default(0)->comment('计费重量');
            $table->bigInteger('system_box_id')->default(0)->comment('预设打包箱ID');
            $table->bigInteger('system_box_cost_amount')->default(0)->comment('预设打包箱成本价格');
            $table->tinyInteger('is_saved')->default(0)->comment('是否已保存');
            $table->bigInteger('shipment_id')->default(0)->comment('发货单ID');
            $table->bigInteger('shipment_order_id')->default(0)->comment('发货单订单ID');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_order_boxes');
    }
};
