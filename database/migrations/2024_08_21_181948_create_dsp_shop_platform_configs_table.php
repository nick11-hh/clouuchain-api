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
        Schema::create('dsp_shop_platform_configs', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('平台');
            $table->string('app_key')->comment('平台key');
            $table->string('app_secret')->comment('平台密钥');
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
        Schema::dropIfExists('dsp_shop_platform_configs');
    }
};
