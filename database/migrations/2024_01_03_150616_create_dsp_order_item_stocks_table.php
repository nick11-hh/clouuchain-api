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
        Schema::create('dsp_order_item_stocks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->comment('订单id');
            $table->bigInteger('order_item_id')->comment('订单 item id');
            $table->bigInteger('stock_id')->comment('库存id');
            $table->bigInteger('stock_item_id')->comment('库存item id');
            $table->bigInteger('lock_id')->comment('库存锁定ids');
            $table->integer('quantity')->comment('占用数量');
            $table->integer('all_quantity')->comment('总占用数量');
            $table->tinyInteger('status')->default(1)->comment('1 可用 2 异常');
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
        Schema::dropIfExists('dsp_order_item_stocks');
    }
};
