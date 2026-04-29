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
        Schema::create('dsp_admin_collect_goods', function (Blueprint $table) {
            $table->id();
            $table->string('goods_name')->comment('产品名称')->index();
            $table->string('spu')->comment('产品SPU')->index();
            $table->integer('category_id')->comment('分类id')->index();
            $table->string('category_name')->nullable()->comment('分类名称')->index();
            $table->string('brand')->nullable()->comment('品牌');
            $table->string('unit')->nullable()->comment('单位');
            $table->string('purchase_price')->nullable()->comment('采购价格');
            $table->text('collect_url')->nullable()->comment('采集链接');
            $table->string('collect_platform')->nullable()->comment('采集平台');
            $table->string('cover_image')->comment('封面图片');
            $table->json('main_images')->comment('产品主图');
            $table->json('options')->nullable()->comment('产品规格');
            $table->json('props')->nullable()->comment('属性参数');
            $table->longText('detail')->nullable()->comment('产品详情信息');
            $table->tinyInteger('status')->default(0)->comment('产品认领状态 1 已认领 0 未认领');
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
        Schema::dropIfExists('dsp_admin_collect_goods');
    }
};
