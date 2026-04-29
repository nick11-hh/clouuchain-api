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
            $table->decimal('service_charge_rate')->change();
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
            $table->string('service_charge_rate')->change();
        });
    }
};
