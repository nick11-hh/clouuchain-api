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
        Schema::create('dsp_express_line_service_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->comment('线路ID');
            $table->bigInteger('service_id')->index('dsp_express_line_service_prices_service_id_index')->comment('线路增值服务ID');
            $table->bigInteger('region_id')->index('dsp_express_line_service_prices_region_id_index')->comment('区域ID');
            $table->bigInteger('value')->comment('值 可能是一个价格 也可能是一个比列等等');
            $table->bigInteger('base_value')->default(0)->comment('基础值');
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
        Schema::dropIfExists('dsp_express_line_service_prices');
    }
};
