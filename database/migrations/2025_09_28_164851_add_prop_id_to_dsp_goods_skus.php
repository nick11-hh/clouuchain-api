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
            // 新增 prop_id 字段
            $table->integer('prop_id')->default(1)->comment('属性ID');
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
            $table->dropColumn('prop_id');
        });
    }
};
