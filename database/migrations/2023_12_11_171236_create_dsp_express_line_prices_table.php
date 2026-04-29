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
        Schema::create('dsp_express_line_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->index('dsp_express_line_prices_express_line_id_index');
            $table->bigInteger('region_id')->index('dsp_express_line_prices_region_id_index');
            $table->unsignedTinyInteger('type')->comment('0 首重价格 1 续重价格 2 区间单位价格 3 区间价格');
            $table->bigInteger('start')->comment('开始重量');
            $table->bigInteger('end')->comment('结束重量');
            $table->bigInteger('price')->nullable()->comment('价格');
            $table->bigInteger('cost_price')->nullable()->comment('成本价格');
            $table->bigInteger('unit_weight')->nullable()->comment('单位重量');
            $table->bigInteger('first_weight')->nullable();
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
        Schema::dropIfExists('dsp_express_line_prices');
    }
};
