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
        Schema::create('dsp_third_party_system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('平台');
            $table->string('app_key')->default('')->comment('应用key');
            $table->string('app_secret')->default('')->comment('应用密钥');
            $table->json('extend')->nullable()->comment('扩展项');
            $table->tinyInteger('status')->default(0)->comment('是否开启');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('第三方系统配置');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_third_party_system_configs');
    }
};
