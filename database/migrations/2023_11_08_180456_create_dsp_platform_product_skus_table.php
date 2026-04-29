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
        Schema::create('dsp_platform_product_skus', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id')->comment('产品id');
            $table->bigInteger('platform_product_id')->comment('平台产品id');
            $table->bigInteger('platform_sku_id')->comment('产品sku id');
            $table->string('sku')->comment('产品sku');
            $table->string('barcode')->nullable()->comment('sku编码');
            $table->string('title')->comment('产品id');
            $table->string('option')->comment('产品id');
            $table->decimal('price')->comment('商品价格');
            $table->integer('inventory_quantity')->comment('产品库存');
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
        Schema::dropIfExists('dsp_platform_product_skus');
    }
};
