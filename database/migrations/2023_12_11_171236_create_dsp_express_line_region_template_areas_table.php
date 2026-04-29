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
        Schema::create('dsp_express_line_region_template_areas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('template_id');
            $table->bigInteger('region_id')->index('dsp_express_line_region_template_areas_region_id_index');
            $table->bigInteger('country_id');
            $table->json('country_name');
            $table->bigInteger('area_id')->nullable();
            $table->json('area_name')->nullable();
            $table->bigInteger('sub_area_id')->nullable();
            $table->json('sub_area_name')->nullable();
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
        Schema::dropIfExists('dsp_express_line_region_template_areas');
    }
};
