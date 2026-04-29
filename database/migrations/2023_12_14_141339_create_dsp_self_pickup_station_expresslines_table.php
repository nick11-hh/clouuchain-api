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
        Schema::create('dsp_self_pickup_station_expresslines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('station_id')->index('dsp_self_pickup_station_expresslines_station_id_index');
            $table->bigInteger('express_line_id')->index('dsp_self_pickup_station_expresslines_express_line_id_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_self_pickup_station_expresslines');
    }
};
