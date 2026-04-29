<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCreditCardRechargeRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credit_card_recharge_record', function (Blueprint $table) {
            // 新增 external_id
            $table->string('external_id', 255)
                ->comment('信用卡唯一id')
                ->after('type');
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
            // 回滚 external_id
            $table->dropColumn('external_id');
        });
    }
}
