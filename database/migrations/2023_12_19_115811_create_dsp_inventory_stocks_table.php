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
        Schema::create('dsp_inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->string('order_sn')->comment('盘点单号');
            $table->tinyInteger('type')->comment('盘点类型');
            $table->tinyInteger('method')->comment('盘点方式 1-盲盘 2-明盘');
            $table->tinyInteger('is_zero')->comment('是否包含0库存');
            $table->tinyInteger('status')->default(0)->comment('盘点单状态 0 待盘点 1 盘点中 2 盘点完成');
            $table->bigInteger('operator_id')->comment('盘点单状态');
            $table->string('operator')->comment('盘点单状态');
            $table->timestamp('finish_time')->nullable()->comment('盘点单状态');
            $table->string('remark')->nullable()->comment('备注');
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
        Schema::dropIfExists('dsp_inventory_stocks');
    }
};
