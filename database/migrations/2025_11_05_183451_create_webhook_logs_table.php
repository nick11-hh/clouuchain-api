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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->string('platform', 50)->comment('平台标识');
            $table->string('external_id', 255)->comment('webhook外部平台的唯一标识符');
            $table->string('event_type', 50)->comment('事件类型');
            $table->json('request_headers')->nullable()->comment('请求头');
            $table->json('request_body')->nullable()->comment('完整数据');
            $table->tinyInteger('status')->comment('处理状态：1=待处理，2=成功，3=失败');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('updated_at')->comment('更新时间');
            $table->comment('webhook请求日志');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('webhook_logs');
    }
};
