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
        Schema::create('dsp_outbound_shop_order_relates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('outbound_order_id')->comment('出库单id');
            $table->bigInteger('shop_order_id')->comment('店铺订单id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_outbound_shop_order_relates');
    }
};
