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
        Schema::create('dsp_plan_shop_order_relation', function (Blueprint $table) {
            $table->id();
            $table->integer('plan_id')->default(0)->comment('采购计划id');
            $table->integer('plan_item_id')->default(0)->comment('采购计划items_id');
            $table->integer('order_id')->default(0)->comment('订单id');
            $table->integer('order_item_id')->default(0)->comment('订单商品id');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('订单&采购计划关系表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_plan_shop_order_relation');
    }
};
