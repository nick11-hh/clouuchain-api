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
        Schema::create('dsp_system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->comment('配置项');
            $table->longText('config_value')->comment('配置值');
            $table->json('ext')->nullable()->comment('扩展数据');
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
        Schema::dropIfExists('dsp_system_settings');
    }
};
