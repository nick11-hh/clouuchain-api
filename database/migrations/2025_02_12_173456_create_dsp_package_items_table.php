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
        Schema::create('dsp_package_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('package_id')->comment('包裹id');
            $table->bigInteger('shop_order_id')->comment('订单id');
            $table->bigInteger('shop_order_item_id')->comment('订单item id');
            $table->integer('quantity')->comment('商品数量');
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
        Schema::dropIfExists('dsp_package_items');
    }
};
