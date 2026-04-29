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
        Schema::create('dsp_track_geo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('context')->unique('dsp_track_geo_context_unique')->comment('地址信息');
            $table->string('latitude')->comment('地址对应的纬度');
            $table->string('longitude')->comment('地址对应的经度');
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
        Schema::dropIfExists('dsp_track_geo');
    }
};
