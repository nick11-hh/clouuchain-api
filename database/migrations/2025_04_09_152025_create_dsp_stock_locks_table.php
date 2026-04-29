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
        Schema::create('dsp_stock_locks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('stock_id')->comment('库存id');
            $table->bigInteger('type')->comment('操作类型 1 锁定 2 释放');
            $table->bigInteger('source')->comment('操作来源');
            $table->bigInteger('quantity')->comment('数量');
            $table->bigInteger('status')->default(1)->comment('状态 1 锁定 2 释放');
            $table->bigInteger('operate_id')->comment('操作id');
            $table->timestamp('unlocked_at')->comment('释放时间');
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
        Schema::dropIfExists('dsp_stock_locks');
    }
};
