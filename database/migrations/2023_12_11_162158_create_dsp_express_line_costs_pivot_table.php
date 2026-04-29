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
        Schema::create('dsp_express_line_costs_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->index('dsp_express_line_costs_pivot_express_line_id_index')->comment('快递线路ID');
            $table->unsignedTinyInteger('type')->default(0);
            $table->bigInteger('express_line_cost_id')->index('dsp_express_line_costs_pivot_express_line_cost_id_index')->comment('快递线路费用ID');
            $table->bigInteger('price')->comment('线路费用价格');
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
        Schema::dropIfExists('dsp_express_line_costs_pivot');
    }
};
