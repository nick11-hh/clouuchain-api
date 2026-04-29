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
        Schema::create('dsp_stock_lock_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('stock_id')->comment('库存id');
            $table->bigInteger('stock_item_id')->comment('库存item id');
            $table->bigInteger('location_id')->comment('仓库货位id');
            $table->string('location_code')->comment('仓库货位编码');
            $table->tinyInteger('type')->comment('操作类型 1 锁定 2 释放');
            $table->tinyInteger('source')->comment('操作来源');
            $table->integer('quantity')->comment('数量');
            $table->tinyInteger('status')->default(1)->comment('状态 1 锁定 2 释放');
            $table->string('operate_sn')->comment('操作单号');
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
        Schema::dropIfExists('dsp_stock_lock_logs');
    }
};
