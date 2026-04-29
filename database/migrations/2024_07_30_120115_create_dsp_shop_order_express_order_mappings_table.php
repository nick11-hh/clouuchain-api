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
        Schema::create('dsp_shop_order_express_order_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('shop_order_id')->default(0)->comment('店铺订单ID');
            $table->integer('express_order_id')->default(0)->comment('物流订单ID');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('店铺订单跟物流订单的映射表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_express_order_mappings');
    }
};
