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
        Schema::create('dsp_shipment_props', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_id')->comment('发货单ID');
            $table->bigInteger('prop_id')->comment('物品熟悉ID');
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
        Schema::dropIfExists('dsp_shipment_props');
    }
};
