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
        Schema::create('dsp_order_docking_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->index('dsp_order_docking_records_order_id_index');
            $table->unsignedTinyInteger('type')->default(1);
            $table->json('data');
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
        Schema::dropIfExists('dsp_order_docking_records');
    }
};
