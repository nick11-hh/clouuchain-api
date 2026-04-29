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
        Schema::create('dsp_promotion_channel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('channel_name', 191)->default('')->comment('渠道名称');
            $table->bigInteger('channel_price')->default(0)->comment('渠道单价');
            $table->tinyInteger('settlement_method')->default(0)->comment('结算方式 1 注册个数 -- 目前仅用于标记');
            $table->string('remark', 191)->default('')->comment('备注');
            $table->string('app_code', 191)->default('')->comment('对应的小程序码');
            $table->softDeletes();
            $table->timestamps();
            $table->bigInteger('category_id')->nullable()->comment('分类ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_promotion_channel');
    }
};
