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
        Schema::create('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->id();
            $table->integer('goods_id')->comment('产品id');
            $table->string('sku_id')->comment('sku编码');
            $table->string('spec_name')->comment('规格名称');
            $table->json('spec_info')->comment('规格详情');
            $table->decimal('sale_price', 10)->comment('销售价格');
            $table->decimal('compare_price', 10)->nullable()->comment('商品比较价格');
            $table->json('images')->comment('sku图片');
            $table->tinyInteger('status')->default(1)->comment('sku状态');
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
        Schema::dropIfExists('dsp_admin_collect_goods_skus');
    }
};
