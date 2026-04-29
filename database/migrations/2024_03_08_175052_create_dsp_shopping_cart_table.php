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
        Schema::create('dsp_shopping_cart', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->comment('用户ID')->index();
            $table->bigInteger('custom_id')->comment('客户主体ID');
            $table->string('platform')->comment('平台');
            $table->bigInteger('goods_id')->comment('产品ID');
            $table->string('goods_name')->comment('产品名称');
            $table->bigInteger('sku_gross_weight')->default(0)->comment('sku重量(g)');
            $table->string('spu')->comment('产品SPU');
            $table->text('source_url')->nullable()->comment('来源链接');
            $table->string('cover_image')->comment('封面图片');
            $table->json('skus')->nullable()->comment('选购的sku信息');

            $table->timestamps();
            $table->softDeletes();
            $table->comment('购物车');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shopping_cart');
    }
};
