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
        Schema::table('dsp_recharge_applies', function (Blueprint $table) {
            $table->bigInteger('pay_amount')->comment('支付金额（分）');
            $table->string('currency')->default('USD')->comment('货币代码');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_recharge_applies', function (Blueprint $table) {
            $table->dropColumn([
                                   'pay_amount',
                                   'currency',
                               ]);
        });
    }
};
