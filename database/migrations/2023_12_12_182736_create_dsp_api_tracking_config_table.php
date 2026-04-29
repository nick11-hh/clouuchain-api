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
        Schema::create('dsp_api_tracking_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kd100_customer_id', 191)->default('');
            $table->string('kd100_key', 191)->default('');
            $table->string('51tracking_app_key', 191)->default('');
            $table->string('17track_app_key', 191)->default('');
            $table->tinyInteger('native_subscribe')->nullable()->comment('国内物流订阅1-快递1002-51tracking3-17track;为空，则无订阅');
            $table->tinyInteger('global_subscribe')->nullable()->comment('全球物流订阅1-快递1002-51tracking3-17track;为空，则无订阅');
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
        Schema::dropIfExists('dsp_api_tracking_config');
    }
};
