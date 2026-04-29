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
        Schema::table('dsp_express_line_regions', function (Blueprint $table) {
            // 新增 delivery_min_days 字段
            $table->integer('delivery_min_days')->default(0)->comment('最小送达天数');
            // 新增 delivery_max_days 字段
            $table->integer('delivery_max_days')->default(0)->comment('最大送达天数');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_express_line_regions', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn(['delivery_min_days', 'delivery_max_days']);
        });
    }
};
