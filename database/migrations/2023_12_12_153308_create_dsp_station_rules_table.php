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
        Schema::create('dsp_station_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191);
            $table->unsignedInteger('type')->default(1);
            $table->bigInteger('amount')->default(0);
            $table->json('rule')->nullable()->comment('首重续重规则');
            $table->unsignedTinyInteger('weight_type')->default(0)->comment('计重方式');
            $table->unsignedTinyInteger('multi_boxes')->default(0)->comment('多箱计佣金');
            $table->string('code', 191)->nullable()->default('')->comment('编号');
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
        Schema::dropIfExists('dsp_station_rules');
    }
};
