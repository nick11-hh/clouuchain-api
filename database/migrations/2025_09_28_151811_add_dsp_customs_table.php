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
        Schema::table('dsp_customs', function (Blueprint $table) {
            //新增 frozen_limit 字段
            $table->decimal('frozen_limit', 12, 2)->nullable()->default(0.00)->comment('冻结额度');
            //新增 cumulative_frozen 字段
            $table->decimal('cumulative_frozen', 12, 2)->nullable()->default(0.00)->comment('累计冻结金额');
            //新增 cumulative_unfrozen 字段
            $table->decimal('cumulative_unfrozen', 12, 2)->nullable()->default(0.00)->comment('累计解冻金额');
            //新增 cumulative_top_up 字段
            $table->decimal('cumulative_top_up', 12, 2)->nullable()->default(0.00)->comment('累计充值金额');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_customs', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn(['frozen_limit', 'cumulative_frozen', 'cumulative_unfrozen', 'cumulative_top_up']);
        });
    }
};
