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
        Schema::create('dsp_inbound_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custom_id')->comment('客户id');
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->string('inbound_sn')->comment('入库单号');
            $table->tinyInteger('status')->default(1)->comment('状态  1 待入库 2 收货中 3 已收货 4 已上架 5 取消');
            $table->string('logistics_sn')->comment('物流单号');
            $table->timestamp('expect_time')->nullable()->comment('预计到货时间');
            $table->timestamp('sign_time')->nullable()->comment('签收时间');
            $table->text('remark')->nullable()->comment('备注信息');
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
        Schema::dropIfExists('dsp_inbound_orders');
    }
};
