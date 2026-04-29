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
        Schema::create('dsp_custom_balance', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id')->index();
            $table->bigInteger('balance')->comment('余额（分）');
            $table->bigInteger('commission')->comment('佣金余额（分）');
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
        Schema::dropIfExists('dsp_custom_balance');
    }
};
