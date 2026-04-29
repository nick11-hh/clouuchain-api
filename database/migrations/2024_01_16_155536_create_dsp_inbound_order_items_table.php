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
        Schema::create('dsp_inbound_order_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('inbound_id')->comment('入库单id');
            $table->bigInteger('goods_id')->comment('商品 id');
            $table->bigInteger('goods_sku_id')->comment('商品sku id');
            $table->string('goods_name')->comment('商品sku id');
            $table->string('goods_sku')->comment('商品sku 编码');
            $table->string('spec_name')->comment('商品规格名称');
            $table->tinyText('sku_image')->comment('商品规格图片');
            $table->integer('quantity')->comment('预计入库数量');
            $table->integer('sign_quantity')->default(0)->comment('签收数量');
            $table->integer('inbound_quantity')->default(0)->comment('入库数量');
            $table->json('inbound_info')->nullable()->comment('入库信息');
            $table->string('remark')->nullable()->comment('备注');
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
        Schema::dropIfExists('dsp_inbound_order_items');
    }
};
