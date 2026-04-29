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
        Schema::table('credit_card_recharge_record', function (Blueprint $table) {
            // 新增 amount_received 字段
            $table->decimal('amount_received', 10, 2)->default(0)->comment('入账金额');
            $table->decimal('gross_amount', 10, 2)->default(0)->comment('扣除供应商手续费前的金额');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('credit_card_recharge_record', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn('amount_received');
        });
    }
};
