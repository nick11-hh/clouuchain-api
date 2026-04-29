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
        Schema::table('dsp_balance_records', function (Blueprint $table) {
            // 新增 cost_breakdown 字段
            $table->text('cost_breakdown')->nullable()->comment('费用明细');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_balance_records', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn('cost_breakdown');
        });
    }
};
