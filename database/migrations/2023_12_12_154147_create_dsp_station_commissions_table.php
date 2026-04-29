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
        Schema::create('dsp_station_commissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('status')->default(0);
            $table->bigInteger('order_id')->index('dsp_station_commissions_order_id_index');
            $table->bigInteger('station_id')->index('dsp_station_commissions_station_id_index');
            $table->string('order_sn', 191);
            $table->bigInteger('amount');
            $table->string('rule', 191);
            $table->bigInteger('record_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_station_commissions');
    }
};
