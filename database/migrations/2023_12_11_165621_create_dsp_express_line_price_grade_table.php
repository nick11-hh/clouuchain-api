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
        Schema::create('dsp_express_line_price_grade', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('start')->nullable()->default(0)->comment('价格档开始');
            $table->bigInteger('end')->nullable()->default(0)->comment('价格档结束');
            $table->bigInteger('cost_price')->nullable()->default(0)->comment('成本单价');
            $table->bigInteger('sale_price')->nullable()->default(0)->comment('销售单价');
            $table->bigInteger('express_line_id')->nullable()->default(0)->comment('对应的线路 id');
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
        Schema::dropIfExists('dsp_express_line_price_grade');
    }
};
