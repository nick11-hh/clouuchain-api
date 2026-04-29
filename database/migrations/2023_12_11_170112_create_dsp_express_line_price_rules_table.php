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
        Schema::create('dsp_express_line_price_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id');
            $table->unsignedTinyInteger('type')->default(1)->comment('0 首重价格 1 续重价格 2 区间单位价格 3 区间价格');
            $table->bigInteger('start')->comment('开始重量');
            $table->bigInteger('end')->comment('结束重量');
            $table->bigInteger('unit_weight')->nullable();
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
        Schema::dropIfExists('dsp_express_line_price_rules');
    }
};
