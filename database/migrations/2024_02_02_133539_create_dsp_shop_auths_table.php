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
        Schema::create('dsp_shop_auths', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('平台');
            $table->bigInteger('custom_id')->comment('用户');
            $table->string('seller_id')->comment('销售账号唯一id');
            $table->string('access_token')->comment('授权token');
            $table->timestamp('access_token_expire_in')->nullable()->comment('授权token过期时间');
            $table->string('refresh_token')->nullable()->comment('刷新token');
            $table->timestamp('refresh_token_expire_in')->nullable()->comment('刷新token过期时间');
            $table->string('region')->nullable()->comment('售卖的国家地区');
            $table->json('origin_data')->nullable()->comment('接口返回的原始数据');
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
        Schema::dropIfExists('dsp_shop_auths');
    }
};
