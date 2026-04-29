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
        Schema::create('dsp_express_line_country', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->comment('记录对应的线路 id');
            $table->bigInteger('country_id')->comment('对应的国家 id');
            $table->bigInteger('area_id')->nullable();
            $table->bigInteger('sub_area_id')->nullable();
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
        Schema::dropIfExists('dsp_express_line_country');
    }
};
