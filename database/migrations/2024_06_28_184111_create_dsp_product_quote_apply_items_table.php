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
        Schema::create('dsp_product_quote_apply_items', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50)->comment('商品所属平台');
            $table->bigInteger('apply_id')->comment('申请id');
            $table->bigInteger('platform_variant_id')->comment('平台品id');
            $table->bigInteger('goods_sku_id')->comment('平台品id');
            $table->tinyInteger('status')->default(1)->comment('状态 1 待审核  1 审核通过 2 审核拒绝');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_product_quote_apply_items');
    }
};
