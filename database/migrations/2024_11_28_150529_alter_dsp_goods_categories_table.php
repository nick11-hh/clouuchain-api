<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_goods_categories', function (Blueprint $table) {
            $table->timestamp('recommended_time')->nullable()->comment('是否推荐');
        });

        DB::statement("ALTER TABLE `dsp_goods_categories`
          MODIFY COLUMN `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序值，越大越靠前' AFTER `status`,
          MODIFY COLUMN `operator` int(11) NULL DEFAULT NULL COMMENT '操作人ID ' AFTER `sort`;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_categories', function (Blueprint $table) {
            $table->dropColumn('recommended_time');
        });
    }
};
