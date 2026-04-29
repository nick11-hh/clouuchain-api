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
        Schema::create('dsp_outbound_item_order_stock_relates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('outbound_item_id')->comment('出库单item id');
            $table->bigInteger('order_item_stock_id')->comment('订单item 发货库存表id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_outbound_item_order_stock_relates');
    }
};
