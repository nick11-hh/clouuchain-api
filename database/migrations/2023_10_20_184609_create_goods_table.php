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
        Schema::create('dsp_goods', function (Blueprint $table) {
            $table->id();
            $table->string('goods_name')->comment('产品名称')->index();
            $table->string('spu')->comment('产品SPU')->index();
            $table->integer('category_id')->comment('分类id')->index();
            $table->string('brand')->nullable()->comment('品牌');
            $table->string('unit')->nullable()->comment('单位');
            $table->decimal('purchase_price', 10)->nullable()->comment('采购价格');
            $table->string('purchase_url')->nullable()->comment('采购链接');
            $table->string('alias')->nullable()->comment('产品别名');
            $table->string('cover_image')->comment('封面图片');
            $table->json('main_images')->comment('产品主图');
            $table->json('options')->nullable()->comment('产品规格');
            $table->json('props')->nullable()->comment('属性参数');
            $table->longText('detail')->nullable()->comment('产品详情信息');
            $table->decimal('goods_lowest_price', 10)->nullable()->comment('产品的最低价格');
            $table->integer('sale_count')->default(0)->comment('产品销售数量');
            $table->tinyInteger('status')->default(0)->comment('产品上架状态 1 已上架 0 未上架');
            $table->tinyInteger('is_hot')->default(0)->comment('产品热销状态 1 已推送到热销 0 未推送到热销');
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
        Schema::dropIfExists('dsp_goods');
    }
};
