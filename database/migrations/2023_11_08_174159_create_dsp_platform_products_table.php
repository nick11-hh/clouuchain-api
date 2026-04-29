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
        Schema::create('dsp_platform_products', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id');
            $table->integer('shop_id')->comment('店铺id');
            $table->string('shop_type')->comment('店铺类型');
            $table->bigInteger('product_id')->comment('平台产品id');
            $table->string('product_name')->comment('产品名称');
            $table->string('product_type')->comment('产品类型');
            $table->string('tags')->comment('tags 标签');
            $table->string('status')->comment('产品状态');
            $table->json('options')->nullable()->comment('产品规格');
            $table->json('images')->nullable()->comment('产品主图');
            $table->longText('detail')->nullable()->comment('产品详情');
            $table->timestamp('published_at')->nullable()->comment('产品发布时间');
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
        Schema::dropIfExists('dsp_platform_products');
    }
};
