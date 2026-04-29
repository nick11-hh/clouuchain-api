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
        Schema::create('dsp_quotation_record', function (Blueprint $table) {
            $table->id();
            $table->string('variant_id')->default('')->comment('订单sku表中的variant_id');
            $table->string('sku_id')->default('')->comment('dsp_goods_skus表中的id');
            $table->decimal('goods_price', 10)->default(0)->comment('商品价格');
            $table->index('variant_id');
            $table->index('sku_id');
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
        Schema::dropIfExists('dsp_quotation_record');
    }
};
