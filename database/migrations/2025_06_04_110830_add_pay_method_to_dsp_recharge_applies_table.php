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
            $table->integer('pay_method')->nullable()->comment('支付方式');
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
            $table->dropColumn('pay_method');
        });
    }
};
