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
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->tinyInteger('goods_type')->default(1)->comment('商品类型 1-产品 2-包材');
            $table->tinyInteger('packing_materials_type')->default(0)->comment('包材类型 1-包装袋 2-纸箱 3-定制盒子 4-贴纸 5-卡片 99-其他');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->dropColumn([
                'goods_type',
                'packing_materials_type',
            ]);
        });
    }
};
