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
        Schema::create('dsp_shipment_logistics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('shipment_id');
            $table->json('context');
            $table->string('operator', 191)->default('System')->comment('操作人');
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
        Schema::dropIfExists('dsp_shipment_logistics');
    }
};
