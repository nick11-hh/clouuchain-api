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
            // 新增 actual_amount 字段
            $table->decimal('actual_amount', 12, 2)->nullable()->default(0.00)->comment('实际金额');
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
            $table->dropColumn('actual_amount');
        });
    }
};
