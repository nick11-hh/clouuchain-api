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
        Schema::create('dsp_outbound_order_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('outbound_id')->comment('出库单id');
            $table->string('stock_id')->comment('库存id');
            $table->string('goods_name')->comment('商品名称');
            $table->string('spec_name')->comment('商品sku 规格名称');
            $table->string('sku_image')->comment('商品sku图片');
            $table->string('sku')->comment('商品sku code');
            $table->decimal('price', 10)->nullable()->comment('商品价格');
            $table->integer('quantity')->comment('发货数量');
            $table->integer('picking_quantity')->default(0)->comment('拣货数量');
            $table->string('remark')->default(0)->comment('备注');
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
        Schema::dropIfExists('dsp_outbound_order_items');
    }
};
