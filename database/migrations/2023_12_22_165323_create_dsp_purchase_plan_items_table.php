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
        Schema::create('dsp_purchase_plan_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('plan_id')->comment('计划id');
            $table->string('item_sn')->comment('编号');
            $table->string('item_image')->comment('商品图片');
            $table->string('item_name')->comment('商品名称');
            $table->string('item_spec_name')->comment('商品规格名称');
            $table->string('quantity')->comment('采购数量');
            $table->bigInteger('goods_sku_id')->comment('关联的商品sku id');
            $table->string('order_sn')->default('')->comment('关联的订单编号');
            $table->bigInteger('order_item_id')->default(0)->comment('order_id');
            $table->tinyInteger('status')->default(0)->comment('状态');
            $table->timestamp('purchase_time')->nullable()->comment('预计采购时间');
            $table->timestamp('purchased_at')->nullable()->comment('创建采购单时间');
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
        Schema::dropIfExists('dsp_purchase_plan_items');
    }
};
