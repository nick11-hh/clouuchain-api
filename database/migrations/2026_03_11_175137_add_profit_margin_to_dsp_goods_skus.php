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
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            // 新增 profit_margin 字段
            $table->decimal('profit_margin', 10, 2)->default(20.00)->comment('产品利润率');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn('profit_margin');
        });
    }
};
