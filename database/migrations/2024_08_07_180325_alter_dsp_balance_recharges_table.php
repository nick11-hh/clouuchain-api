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
        Schema::table('dsp_balance_recharges', function (Blueprint $table) {
            $table->decimal('service_charge_amount', 10)->default(0)->comment('手续费金额');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_balance_recharges', function (Blueprint $table) {
            $table->dropColumn(['service_charge_amount']);
        });
    }
};
