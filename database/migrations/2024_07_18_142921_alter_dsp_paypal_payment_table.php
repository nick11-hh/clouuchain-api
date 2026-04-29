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
        Schema::table('dsp_paypal_payment', function (Blueprint $table) {
            $table->integer('minimum_payment')->default(0)->comment('最低支付金额');
            $table->tinyInteger('sandbox')->default(0)->comment('沙盒模式：0-否 1-是');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_paypal_payment', function (Blueprint $table) {
            $table->dropColumn([
                                   'minimum_payment',
                                   'sandbox',
                               ]);
        });
    }
};
