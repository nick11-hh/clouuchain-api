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
        Schema::create('dsp_stock_change_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('custom_id')->comment('客户id');
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->bigInteger('stock_id')->comment('库存id');
            $table->string('goods_name')->comment('产品名称');
            $table->string('spec_name')->comment('规格名称');
            $table->string('sku')->comment('sku编码');
            $table->bigInteger('location_id')->comment('库位id');
            $table->string('location_code')->comment('库位编码');
            $table->string('operate_sn')->comment('操作单号');
            $table->tinyInteger('source')->comment('操作来源');
            $table->tinyInteger('change_type')->comment('变更类型');
            $table->integer('quantity')->comment('变更数量');
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
        Schema::dropIfExists('dsp_stock_change_logs');
    }
};
