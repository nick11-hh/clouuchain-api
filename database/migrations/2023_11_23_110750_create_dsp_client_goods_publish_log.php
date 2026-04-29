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
        Schema::create('dsp_client_goods_publish_log', function (Blueprint $table) {
            $table->id();
            $table->integer('goods_id')->comment('商品id');
            $table->string('platform')->comment('刊登平台');
            $table->text('info')->comment('刊登结果信息');
            $table->tinyInteger('status')->default(1)->comment('刊登状态');
            $table->json('ext')->nullable()->comment('扩展数据');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_client_goods_publish_log');
    }
};
