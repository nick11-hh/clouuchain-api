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
        Schema::create('dsp_shop_order_packing_materials', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->default(0)->comment('订单ID')->index();
            $table->integer('goods_id')->default(0)->comment('商品ID');
            $table->integer('goods_sku_id')->default(0)->comment('sku主键');
            $table->string('name')->comment('商品名称');
            $table->string('sku')->nullable()->comment('SKU');
            $table->string('spec_name')->nullable()->comment('规格名称');
            $table->integer('quantity')->default(0)->comment('数量');
            $table->json('images')->nullable()->comment('商品图片');
            $table->integer('operator_id')->default(0)->comment('操作人ID');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('订单包材表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_packing_materials');
    }
};
